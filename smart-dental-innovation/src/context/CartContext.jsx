import { createContext, useContext, useMemo, useCallback, useState, useEffect, useRef } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import { useSettings } from "./SettingsContext";
import { useAuth } from "./AuthContext";
import { computeCartPricing } from "../lib/pricing";
import api from "../lib/api";

const CartContext = createContext(null);

// Cap a quantity at the line's known stock — only for real product lines with a finite
// stock > 0. Unknown stock (undefined) or non-product lines (gifts/offers) pass through
// unchanged. Prevents building a cart that can never clear the checkout stock check.
function capToStock(qty, stock) {
  return (typeof stock === "number" && stock > 0) ? Math.min(qty, stock) : qty;
}

export function CartProvider({ children }) {
  const [items, setItems] = useLocalStorage("sdi:cart", []);
  const { token } = useAuth();
  // cartSynced  = the one-time login merge has STARTED (prevents duplicate merges).
  // cartReady    = the merge has COMPLETED. The per-change "replace" sync is gated on THIS,
  // not on cartSynced — otherwise replace fires with the still-empty local cart before the
  // merge resolves and wipes the saved server cart (the multi-browser "empty cart" bug).
  const cartSynced = useRef(false);
  const cartReady = useRef(false);
  // Applied coupon is shared (cart + checkout) and survives reloads. Declared up here so the
  // login/logout sync effects below can reference setAppliedCoupon (avoids a TDZ ReferenceError).
  const [appliedCoupon, setAppliedCoupon] = useLocalStorage("sdi:coupon", null);

  // On login: merge the guest (local) cart with the customer's saved server cart (union by
  // line key, max qty), then adopt the merged result. Server cart now follows the account.
  useEffect(() => {
    if (!token || cartSynced.current) return;
    cartSynced.current = true;
    api.syncCart(items, "merge")
      .then((merged) => { if (Array.isArray(merged)) setItems(merged); })
      // A cart sync failure must never log the user out or block checkout — stay local-only.
      .catch((err) => console.warn("[cart] sync failed:", err.message))
      // Only now is it safe to push local changes back (merge is reconciled).
      .finally(() => { cartReady.current = true; });
  }, [token, items, setItems]);

  // On logout, clear the local cart (and coupon) so one customer's items can't leak into
  // the next session on a shared device. The cart stays saved on the server under the
  // account and restores on the next login. We detect the genuine logged-in -> logged-out
  // transition (not just "no token", which is also the initial guest state); the server
  // cart is NOT wiped because the "replace" sync above is gated on `token` being present.
  const prevToken = useRef(token);
  useEffect(() => {
    const wasLoggedIn = !!prevToken.current;
    prevToken.current = token;
    if (!token) {
      cartSynced.current = false;   // next login re-merges
      cartReady.current = false;    // and must re-complete before pushing again
      if (wasLoggedIn) {            // genuine logout, not the initial guest page load
        setItems([]);
        setAppliedCoupon(null);
      }
    }
  }, [token, setItems, setAppliedCoupon]);

  // While logged in, persist every cart change to the server (replace = authoritative, so
  // removals and clears propagate). Fire-and-forget; failures are non-fatal.
  useEffect(() => {
    if (!token || !cartReady.current) return;
    // Auto-gift lines are derived from the cart's products, so don't persist them — they're
    // re-added on load. Persisting would double them up after the gift effect re-runs.
    api.syncCart(items.filter((i) => !i.autoGift), "replace").catch(() => {});
  }, [items, token]);
  // Delivery pincode drives the server shipping quote (zone + weight aware). Persisted.
  const [deliveryPincode, setDeliveryPincode] = useLocalStorage("sdi:pincode", "");
  const { tierOffers, bulkRule, shippingConfig, taxConfig } = useSettings();

  // Server-authoritative shipping quote (shipping_methods/rules/zones). null until fetched;
  // until then the cart shows the flat shippingConfig estimate from computeCartPricing.
  const [shippingQuote, setShippingQuote] = useState(null);
  useEffect(() => {
    const lines = items
      .filter((i) => i.type !== "gift")
      .map((i) => ({ slug: i.id, qty: i.qty }));
    if (lines.length === 0) { setShippingQuote(null); return; }
    let alive = true;
    api.shippingQuote({ items: lines, pincode: deliveryPincode })
      .then((q) => { if (alive && q && typeof q.shipping === "number") setShippingQuote(q); })
      .catch(() => { if (alive) setShippingQuote(null); });   // fall back to flat estimate
    return () => { alive = false; };
  }, [items, deliveryPincode]);

  // Per-product free gifts: auto-add a ₹0 gift line for each product that grants one, and
  // drop gift lines whose granting product has left the cart. Keyed on the set of real
  // product slugs so it only re-runs when products change (not on gift/qty churn).
  const productSlugs = items.filter((i) => i.type === "product").map((i) => i.id).sort().join(",");
  useEffect(() => {
    const slugs = productSlugs ? productSlugs.split(",") : [];
    let alive = true;
    if (slugs.length === 0) {
      // No products -> remove any auto gift lines.
      setItems((prev) => prev.some((i) => i.autoGift) ? prev.filter((i) => !i.autoGift) : prev);
      return;
    }
    api.gifts(slugs)
      .then((gifts) => {
        if (!alive) return;
        const valid = Array.isArray(gifts) ? gifts : [];
        setItems((prev) => {
          const cartSlugs = new Set(prev.filter((i) => i.type === "product").map((i) => i.id));
          // Keep non-auto-gift lines as-is; rebuild the auto-gift set from the server.
          const base = prev.filter((i) => !i.autoGift);
          const giftLines = valid
            .filter((g) => g.parentSlug && cartSlugs.has(g.parentSlug)) // parent still in cart
            .map((g) => ({
              key: `autogift:${g.id}`,
              id: g.id,
              name: g.name,
              image: g.image,
              price: 0,
              mrp: g.mrp,
              category: g.category || "unique",
              variant: null,
              qty: 1,
              type: "gift",
              autoGift: true,
              parentSlug: g.parentSlug,
            }));
          // Dedupe gift lines by key (a gift granted by 2 products appears once).
          const seen = new Set();
          const uniqGifts = giftLines.filter((g) => !seen.has(g.key) && seen.add(g.key));
          const next = [...base, ...uniqGifts];
          // Avoid a state churn loop: only update if the auto-gift set actually changed.
          const prevKeys = prev.filter((i) => i.autoGift).map((i) => i.key).sort().join(",");
          const nextKeys = uniqGifts.map((i) => i.key).sort().join(",");
          return prevKeys === nextKeys ? prev : next;
        });
      })
      .catch(() => {});
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [productSlugs]);

  const addToCart = useCallback((product, qty = 1, variant = null) => {
    setItems((prev) => {
      // Offer/gift lines are keyed by type+offer so they never merge with a normal line
      // for the same product, or with the same product across two different offers.
      const type = product.type || "product";
      const isOfferLine = type === "offer" || type === "gift";
      const key = isOfferLine
        ? `${type}:${product.offerId}:${product.id}`
        : (variant ? `${product.id}::${variant}` : product.id);
      const idx = prev.findIndex((i) => i.key === key);
      if (idx >= 0) {
        const next = [...prev];
        const merged = next[idx].qty + qty;
        next[idx] = { ...next[idx], qty: type === "product" ? capToStock(merged, next[idx].stock) : merged };
        return next;
      }
      return [
        ...prev,
        {
          key,
          id: product.id,
          name: product.name,
          image: product.image,
          price: product.price,
          mrp: product.mrp,
          category: product.category || "unique",
          // Per-product quantity tiers (override the global table in cart savings + checkout).
          bulkOffers: Array.isArray(product.bulkOffers) && product.bulkOffers.length ? product.bulkOffers : null,
          variant,
          qty: type === "product" ? capToStock(qty, product.stock) : qty,
          type,
          // Remember available stock so later increments can't exceed it.
          stock: typeof product.stock === "number" ? product.stock : undefined,
          offerId: product.offerId || null,
          parentId: product.parentId || null,
          // For gift lines, remember the per-offer-unit qty so it can scale with the offer line.
          baseQty: type === "gift" ? qty : undefined,
        },
      ];
    });
  }, [setItems]);

  const removeFromCart = useCallback((key) => {
    setItems((prev) => {
      const line = prev.find((i) => i.key === key);
      // Removing an offer's main line also removes its bound free-gift lines.
      if (line && line.type === "offer") {
        return prev.filter((i) => i.key !== key && !(i.type === "gift" && i.offerId === line.offerId));
      }
      return prev.filter((i) => i.key !== key);
    });
  }, [setItems]);

  const updateQty = useCallback((key, qty) => {
    setItems((prev) => {
      const line = prev.find((i) => i.key === key);
      if (!line) return prev;
      // Clamp to [1, stock] for product lines (stock known); gifts/offers just clamp ≥1.
      const newQty = line.type === "product" ? capToStock(Math.max(1, qty), line.stock) : Math.max(1, qty);
      return prev
        .map((i) => {
          if (i.key === key) return { ...i, qty: newQty };
          // Free gifts scale 1:1 with their offer's main line.
          if (line.type === "offer" && i.type === "gift" && i.offerId === line.offerId) {
            return { ...i, qty: Math.max(1, (i.baseQty || 1) * newQty) };
          }
          return i;
        })
        .filter((i) => i.qty > 0);
    });
  }, [setItems]);

  const clearCart = useCallback(() => { setItems([]); setAppliedCoupon(null); }, [setItems, setAppliedCoupon]);

  const applyCoupon = useCallback((coupon) => setAppliedCoupon(coupon), [setAppliedCoupon]);
  const removeCoupon = useCallback(() => setAppliedCoupon(null), [setAppliedCoupon]);

  // Single source of truth for all cart money (mirrors the server: lib/pricing.js).
  // Shipping: prefer the server quote (DB shipping engine); fall back to the flat
  // computeCartPricing estimate until/if the quote is unavailable. Re-derive the total
  // so deliveryCharges and finalTotal stay consistent with whichever shipping we use.
  const pricing = useMemo(() => {
    const base = computeCartPricing(items, {
      tierOffers,
      bulkRule,
      shipping: shippingConfig,
      tax: taxConfig,
      coupon: appliedCoupon,
    });
    if (!shippingQuote || typeof shippingQuote.shipping !== "number") return base;
    const deliveryCharges = items.length === 0 ? 0 : shippingQuote.shipping;
    const finalTotal = Math.max(0, Math.round((base.finalTotal - base.deliveryCharges + deliveryCharges) * 100) / 100);
    return { ...base, deliveryCharges, finalTotal };
  }, [items, tierOffers, bulkRule, shippingConfig, taxConfig, appliedCoupon, shippingQuote]);

  // Count only purchasable lines — free gifts (auto + offer-bound) are bonuses, not items
  // the customer added, so they must not inflate the cart badge / "N items" label.
  const itemCount = useMemo(() => items.reduce((c, i) => i.type === "gift" ? c : c + i.qty, 0), [items]);
  const subtotal = pricing.subtotal;

  const value = useMemo(
    () => ({
      items, addToCart, removeFromCart, updateQty, clearCart,
      subtotal, itemCount, pricing,
      appliedCoupon, applyCoupon, removeCoupon,
      deliveryPincode, setDeliveryPincode, shippingQuote,
    }),
    [items, addToCart, removeFromCart, updateQty, clearCart, subtotal, itemCount, pricing, appliedCoupon, applyCoupon, removeCoupon, deliveryPincode, setDeliveryPincode, shippingQuote]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used inside CartProvider");
  return ctx;
};
