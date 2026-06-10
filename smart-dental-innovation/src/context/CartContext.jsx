import { createContext, useContext, useMemo, useCallback } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import { useSettings } from "./SettingsContext";
import { computeCartPricing } from "../lib/pricing";

const CartContext = createContext(null);

export function CartProvider({ children }) {
  const [items, setItems] = useLocalStorage("sdi:cart", []);
  // Applied coupon is shared (cart + checkout) and survives reloads.
  const [appliedCoupon, setAppliedCoupon] = useLocalStorage("sdi:coupon", null);
  const { bulkRule, shippingConfig, taxConfig } = useSettings();

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
        next[idx] = { ...next[idx], qty: next[idx].qty + qty };
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
          variant,
          qty,
          type,
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
      const newQty = Math.max(1, qty);
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
  const pricing = useMemo(
    () => computeCartPricing(items, {
      bulkRule,
      shipping: shippingConfig,
      tax: taxConfig,
      coupon: appliedCoupon,
    }),
    [items, bulkRule, shippingConfig, taxConfig, appliedCoupon]
  );

  const itemCount = useMemo(() => items.reduce((c, i) => c + i.qty, 0), [items]);
  const subtotal = pricing.subtotal;

  const value = useMemo(
    () => ({
      items, addToCart, removeFromCart, updateQty, clearCart,
      subtotal, itemCount, pricing,
      appliedCoupon, applyCoupon, removeCoupon,
    }),
    [items, addToCart, removeFromCart, updateQty, clearCart, subtotal, itemCount, pricing, appliedCoupon, applyCoupon, removeCoupon]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used inside CartProvider");
  return ctx;
};
