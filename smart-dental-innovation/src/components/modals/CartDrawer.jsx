import { useRef, useState, useEffect } from "react";
import { createPortal } from "react-dom";
import Drawer from "../ui/Drawer";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import { useAuth } from "../../context/AuthContext";
import { useSettings } from "../../context/SettingsContext";
import { tierFor } from "../../lib/pricing";
import api from "../../lib/api";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const cartIcon = (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="9" cy="21" r="1" />
    <circle cx="20" cy="21" r="1" />
    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
  </svg>
);

export default function CartDrawer() {
  const { modal, closeModal, openModal } = useUI();
  const { items, updateQty, removeFromCart, subtotal, itemCount, pricing, appliedCoupon, applyCoupon: applyCouponCtx, removeCoupon } = useCart();
  const { user } = useAuth();
  const { toggle: toggleWish } = useWishlist();
  const { coupons: COUPONS = [], bulkRule = {}, tierOffers = [] } = useSettings();
  const [priceOpen, setPriceOpen] = useState(true);
  const [confirmRemove, setConfirmRemove] = useState(null);
  const [view, setView] = useState("cart");
  const [couponCode, setCouponCode] = useState("");
  const [couponTab, setCouponTab] = useState("all");
  const [couponMsg, setCouponMsg] = useState("");

  const askRemove = (item) => setConfirmRemove(item);
  const closeConfirm = () => setConfirmRemove(null);
  const removeNow = () => {
    if (!confirmRemove) return;
    removeFromCart(confirmRemove.key);
    closeConfirm();
  };
  const saveToWishlist = () => {
    if (!confirmRemove) return;
    toggleWish(confirmRemove.id);
    removeFromCart(confirmRemove.key);
    closeConfirm();
  };

  const onCheckout = () => {
    if (!user) {
      openModal("auth");
      return;
    }
    openModal("checkout");
  };

  // All money comes from the shared, server-mirrored calculator (CartContext → lib/pricing.js).
  const { mrpTotal, bulkSavings, couponDiscount, deliveryCharges, tax, finalTotal, totalSaved } = pricing;
  const productDiscount = Math.max(0, mrpTotal - subtotal);
  const codCharges = 0;

  const eligibleCoupons = COUPONS.filter((c) => subtotal >= c.minSubtotal);
  const unavailableCoupons = COUPONS.filter((c) => subtotal < c.minSubtotal);
  const visibleCoupons = couponTab === "all" ? eligibleCoupons : unavailableCoupons;
  const codeFilter = couponCode.trim().toUpperCase();
  const filteredCoupons = codeFilter
    ? visibleCoupons.filter((c) => c.code.includes(codeFilter))
    : visibleCoupons;

  const applyCoupon = (c) => {
    if (subtotal < c.minSubtotal) {
      setCouponMsg(`Add ${fmt(c.minSubtotal - subtotal)} more to use ${c.code}.`);
      return;
    }
    applyCouponCtx(c);
    setCouponMsg(`${c.code} applied.`);
    setView("cart");
  };

  const onCouponInputSubmit = async () => {
    const code = couponCode.trim().toUpperCase();
    if (!code) return;
    // Validate against the backend (source of truth).
    try {
      const res = await api.validateCoupon(code, Math.round(subtotal));
      if (res.valid) {
        // Normalize to the shape the cart's discount calc expects.
        applyCouponCtx({
          code: res.code,
          minSubtotal: 0,
          discount: { type: res.type === "percent" ? "percent" : "flat", value: res.value },
          serverDiscount: res.discount,
        });
        setCouponMsg(res.message || `${code} applied.`);
        setView("cart");
        return;
      }
      setCouponMsg(res.message || `Invalid coupon "${code}".`);
    } catch (err) {
      // Fallback to static list if API down.
      console.warn("[coupon] API fallback:", err.message);
      const match = COUPONS.find((c) => c.code === code);
      if (match) return applyCoupon(match);
      setCouponMsg(`Invalid coupon "${code}".`);
    }
  };

  const closeAll = () => {
    setView("cart");
    closeModal();
  };

  const headerTitle = view === "coupons" ? (
    <div className="flex items-center gap-2 leading-tight">
      <button
        onClick={() => setView("cart")}
        aria-label="Back"
        className="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-ink"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <div>
        <div className="text-lg font-semibold text-brand-ink">Coupons</div>
        <div className="text-xs text-brand-muted font-normal">Enter code or choose an offer</div>
      </div>
    </div>
  ) : "Your Cart";

  return (
    <Drawer
      open={modal === "cart"}
      onClose={closeAll}
      title={headerTitle}
      titleIcon={view === "coupons" ? null : cartIcon}
      footer={
        view === "cart" && items.length > 0 && (
          <div className="space-y-3 -mx-5 -my-4 px-5 py-4 bg-white border-t border-gray-200">
            {/* Price summary bar */}
            <button
              onClick={() => setPriceOpen((v) => !v)}
              className="w-full flex items-center justify-between bg-green-600 text-white px-4 py-2.5 rounded-md text-sm font-bold"
            >
              <span>
                Price summary <span className="line-through opacity-80 font-normal ml-2">{fmt(mrpTotal)}</span>{" "}
                <span className="ml-2">{fmt(finalTotal)}</span>
              </span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" className={`transition ${priceOpen ? "" : "rotate-180"}`}>
                <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
              </svg>
            </button>

            {priceOpen && (
              <div className="bg-gray-50 border border-gray-100 rounded-md px-4 py-3 space-y-2 text-sm">
                <div className="flex items-center justify-between">
                  <span className="text-brand-ink">MRP Total</span>
                  <span className="font-semibold text-brand-ink">₹{mrpTotal.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>
                {productDiscount > 0 && (
                  <div className="flex items-center justify-between">
                    <span className="text-brand-ink">Product Discount</span>
                    <span className="font-semibold text-green-600">-₹{productDiscount.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                  </div>
                )}
                {couponDiscount > 0 && (
                  <div className="flex items-center justify-between">
                    <span className="text-brand-ink">Coupon ({appliedCoupon.code})</span>
                    <span className="font-semibold text-green-600">-₹{couponDiscount.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                  </div>
                )}
                {/* Delivery charge (pincode is collected later in checkout). */}
                <div className="pt-1 border-t border-gray-200">
                  <div className="flex items-center justify-between">
                    <span className="text-brand-ink">Delivery Charges</span>
                    {deliveryCharges > 0 ? (
                      <span className="font-semibold text-brand-ink">₹{deliveryCharges.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    ) : (
                      <span className="font-semibold text-green-600">FREE</span>
                    )}
                  </div>
                </div>
                <div className="flex items-start justify-between">
                  <div>
                    <div className="text-brand-ink">COD Charges</div>
                    <div className="text-[11px] text-brand-muted">Free on Prepaid</div>
                  </div>
                  <span className="font-semibold text-green-600">FREE</span>
                </div>
                {tax > 0 && (
                  <div className="flex items-center justify-between pt-1 border-t border-gray-200">
                    <span className="text-brand-ink">Tax (GST)</span>
                    <span className="font-semibold text-brand-ink">₹{tax.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                  </div>
                )}
                {totalSaved > 0 && (
                  <div className="bg-green-50 border border-green-100 rounded-md px-3 py-2 text-center text-sm text-green-800 font-semibold mt-2">
                    🎉 🎁 You saved {fmt(totalSaved)} on this order!
                  </div>
                )}
              </div>
            )}

            <div className="flex items-center justify-between pt-1">
              <div>
                <div className="text-lg font-bold text-brand-ink">Rs. {fmt(finalTotal)}</div>
                <button
                  onClick={() => setPriceOpen((v) => !v)}
                  className="text-xs text-[#3684bf] underline"
                >
                  View Price Details
                </button>
              </div>
              <div className="buy-now-btn" style={{ width: "180px", height: "50px" }}>
                <div className="buy-now-btn__shadow" />
                <button
                  type="button"
                  onClick={onCheckout}
                  className="buy-now-btn__face"
                  style={{ textTransform: "none", gap: 6, fontSize: 15 }}
                >
                  <span className="buy-now-btn__shimmer" />
                  <span style={{ userSelect: "none", pointerEvents: "none", display: "flex" }}>
                    {"Place Order".split("").map((c, i) => (
                      <span key={i}>{c === " " ? " " : c}</span>
                    ))}
                  </span>
                  <img
                    src="https://d1865wozhn5fw4.cloudfront.net/upi-icons.svg"
                    alt="UPI"
                    style={{ height: 23, userSelect: "none", pointerEvents: "none" }}
                  />
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        )
      }
    >
      {view === "coupons" ? (
        <CouponsPanel
          code={couponCode}
          setCode={setCouponCode}
          tab={couponTab}
          setTab={setCouponTab}
          allCount={eligibleCoupons.length}
          unavailableCount={unavailableCoupons.length}
          coupons={filteredCoupons}
          subtotal={subtotal}
          onSubmit={onCouponInputSubmit}
          onApply={applyCoupon}
          msg={couponMsg}
        />
      ) : items.length === 0 ? (
        <EmptyCart closeModal={closeModal} />
      ) : (
        <div className="-mx-5 -my-4">
          <div className="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <span className="text-sm font-bold uppercase tracking-wider text-brand-ink">
              {items[0]?.name?.split(" ").slice(0, 2).join(" ").toUpperCase() || "CART"}
              {items.length > 1 && (
                <span className="text-brand-muted font-normal"> +{items.length - 1} MORE</span>
              )}
            </span>
            <span className="text-xs text-brand-muted">({itemCount} item{itemCount !== 1 ? "s" : ""})</span>
          </div>

          <ul>
            {items.map((i) => (
              <li key={i.key} className="px-5 py-4 border-b border-gray-100 flex gap-3">
                <div className="w-20 h-20 bg-gray-50 rounded-lg shrink-0 flex items-center justify-center overflow-hidden">
                  <img src={i.image} alt={i.name} className="max-w-full max-h-full object-contain" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-semibold text-brand-ink line-clamp-2">
                      {i.name}
                      {i.type === "gift" && (
                        <span className="ml-2 align-middle text-[10px] font-bold text-green-700 bg-green-50 border border-green-200 rounded px-1.5 py-0.5">FREE GIFT</span>
                      )}
                    </p>
                    {/* Gift lines are bound to their offer's main line — not independently removable. */}
                    {i.type !== "gift" && (
                      <button
                        onClick={() => askRemove(i)}
                        className="text-brand-muted hover:text-red-500 shrink-0"
                        aria-label="Remove"
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <polyline points="3 6 5 6 21 6" /><path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                        </svg>
                      </button>
                    )}
                  </div>

                  {i.variant && (
                    <span className="inline-block mt-1 text-[11px] font-semibold text-[#3684bf] bg-blue-50 border border-blue-100 rounded px-2 py-0.5">
                      {i.variant}
                    </span>
                  )}

                  <div className="mt-2 flex items-center justify-between">
                    <div className="flex items-baseline gap-2">
                      {i.type === "gift" ? (
                        <span className="text-base font-bold text-green-600">FREE</span>
                      ) : (
                        <span className="text-base font-bold text-brand-ink">{fmt(i.price)}</span>
                      )}
                      {i.mrp && i.mrp > i.price && (
                        <span className="text-xs text-brand-muted line-through">₹{i.mrp.toLocaleString("en-IN")}.00</span>
                      )}
                    </div>
                    {i.type === "gift" ? (
                      // Free gifts scale with their offer line; show a static count, no controls.
                      <span className="text-xs text-brand-muted bg-gray-100 rounded-full px-2.5 py-1">x{i.qty}</span>
                    ) : (
                      <div className="inline-flex items-center border border-[#3684bf] rounded-md text-sm bg-white overflow-hidden">
                        {i.qty <= 1 ? (
                          <button
                            onClick={() => askRemove(i)}
                            className="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-50"
                            aria-label="Remove"
                          >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                              <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                          </button>
                        ) : (
                          <button
                            onClick={() => updateQty(i.key, i.qty - 1)}
                            className="w-8 h-8 hover:bg-gray-50 text-[#3684bf] font-bold"
                          >−</button>
                        )}
                        <span className="w-8 text-center font-semibold text-brand-ink">{i.qty}</span>
                        <button
                          onClick={() => updateQty(i.key, i.qty + 1)}
                          className="w-8 h-8 hover:bg-gray-50 text-[#3684bf] font-bold"
                        >+</button>
                      </div>
                    )}
                  </div>

                  {i.type !== "gift" && (() => {
                    const tier = tierFor(i.qty, tierOffers, bulkRule);
                    if (!tier || !tier.rate) return null;
                    const saved = fmt(i.price * tier.rate * i.qty);
                    const label = tier.label || `${Math.round(tier.rate * 100)}% quantity discount`;
                    return (
                      <div className="mt-2 bg-green-50 border border-green-100 rounded text-xs text-green-800 font-semibold px-2 py-1.5 flex items-center gap-1.5">
                        <span>🔥</span> You saved {saved} — {label}
                      </div>
                    );
                  })()}
                </div>
              </li>
            ))}
          </ul>


          <div className="px-5 py-2 bg-gray-50 border-b border-gray-200">
            <span className="text-xs text-brand-muted">Offers & Rewards</span>
          </div>

          <div className="px-5 py-3 bg-white border-b border-gray-100">
            {appliedCoupon ? (
              <div className="w-full flex items-center justify-between border border-green-500 bg-green-50 rounded-lg px-4 py-3">
                <span className="flex items-center gap-2 text-green-700 font-semibold">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
                  {appliedCoupon.code} applied
                </span>
                <button
                  onClick={() => { removeCoupon(); setCouponMsg(""); }}
                  className="text-xs text-red-600 font-semibold hover:underline"
                >
                  Remove
                </button>
              </div>
            ) : (
              <button
                onClick={() => { setView("coupons"); setCouponMsg(""); }}
                className="w-full flex items-center justify-between border border-gray-200 rounded-lg px-4 py-3 hover:border-green-500 hover:bg-green-50 transition"
              >
                <span className="flex items-center gap-2 text-green-700 font-semibold">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
                  View Coupons
                </span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5"><path d="M9 6l6 6-6 6" /></svg>
              </button>
            )}
          </div>

          <FrequentlyBought />

          <TrustSeal />
        </div>
      )}

      {confirmRemove && (
        <RemoveConfirmDialog
          item={confirmRemove}
          onCancel={closeConfirm}
          onRemove={removeNow}
          onWishlist={saveToWishlist}
        />
      )}
    </Drawer>
  );
}

function RemoveConfirmDialog({ item, onCancel, onRemove, onWishlist }) {
  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-center justify-center p-4"
      onClick={onCancel}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-3 mb-4">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="#dc2626">
            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
          </svg>
          <h3 className="text-xl font-bold text-brand-ink">Remove from Cart?</h3>
        </div>

        <div className="flex items-center gap-3 bg-gray-50 rounded-lg p-3 mb-4">
          <div className="w-14 h-14 bg-white rounded shrink-0 flex items-center justify-center overflow-hidden">
            <img src={item.image} alt={item.name} className="max-w-full max-h-full object-contain" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-brand-ink line-clamp-1">{item.name}</p>
            <p className="text-xs text-brand-muted mt-0.5">
              {item.variant || "2 Year Warranty"} • Qty: {item.qty}
            </p>
          </div>
        </div>

        <p className="text-sm text-brand-muted mb-4">Save for later or remove completely?</p>

        <button
          onClick={onWishlist}
          className="w-full flex items-center justify-center gap-2 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold py-3 rounded-lg transition mb-2"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
          </svg>
          Save to Wishlist
        </button>

        <button
          onClick={onRemove}
          className="w-full border border-gray-300 hover:border-red-500 hover:text-red-600 text-brand-ink font-bold py-3 rounded-lg transition mb-2"
        >
          Remove Completely
        </button>

        <button
          onClick={onCancel}
          className="w-full text-brand-muted hover:text-brand-ink font-semibold py-2"
        >
          Cancel
        </button>
      </div>
    </div>,
    document.body
  );
}

function FrequentlyBought() {
  const { addToCart, items } = useCart();
  const [fbt, setFbt] = useState([]);

  // Per-product FBT: ask the server for products frequently bought with what's in the cart.
  const cartSlugs = items.filter((i) => i.type !== "gift").map((i) => i.id).join(",");
  useEffect(() => {
    const slugs = cartSlugs ? cartSlugs.split(",") : [];
    if (slugs.length === 0) { setFbt([]); return; }
    let alive = true;
    api.fbt(slugs)
      .then((list) => { if (alive) setFbt(Array.isArray(list) ? list : []); })
      .catch(() => { if (alive) setFbt([]); });
    return () => { alive = false; };
  }, [cartSlugs]);

  const onAdd = (item) => {
    addToCart(item, 1);
  };

  // Transform-based carousel: one index, translateX, CSS transition. No native scroll/snap,
  // so there's no scroll-vs-snap fight and the slide is perfectly smooth (no flicker).
  const [idx, setIdx] = useState(0);
  const paused = useRef(false);
  const go = (dir) => setIdx((i) => (i + dir + fbt.length) % fbt.length); // wraps both ways

  // Touch/drag swipe for manual control (no arrows). A horizontal flick past 40px advances.
  const swipe = useRef({ x: 0, active: false });
  const onStart = (x) => { swipe.current = { x, active: true }; paused.current = true; };
  const onEnd = (x) => {
    if (!swipe.current.active) return;
    const dx = x - swipe.current.x;
    if (Math.abs(dx) > 40) go(dx < 0 ? 1 : -1);
    swipe.current.active = false; paused.current = false;
  };

  // Keep idx valid if the FBT list changes (e.g. items added/removed).
  useEffect(() => { setIdx(0); }, [cartSlugs]);

  // Auto-advance one card every 3s. Pauses on hover.
  useEffect(() => {
    if (fbt.length <= 1) return;
    const id = setInterval(() => { if (!paused.current) go(1); }, 3000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fbt.length]);

  // Nothing suggested for this cart -> hide the whole strip.
  if (fbt.length === 0) return null;

  return (
    <div className="px-5 py-3 bg-white border-b border-gray-100">
      <div className="flex items-center justify-between mb-2">
        <h4 className="font-bold text-brand-ink flex items-center gap-2">
          <span>✨</span> Frequently Bought Together
        </h4>
      </div>
      <div
        className="overflow-hidden cursor-grab active:cursor-grabbing select-none"
        onMouseEnter={() => { paused.current = true; }}
        onMouseLeave={() => { if (swipe.current.active) onEnd(swipe.current.x); paused.current = false; }}
        onMouseDown={(e) => onStart(e.clientX)}
        onMouseUp={(e) => onEnd(e.clientX)}
        onTouchStart={(e) => onStart(e.touches[0].clientX)}
        onTouchEnd={(e) => onEnd(e.changedTouches[0].clientX)}
      >
        <div
          className="flex"
          style={{ transform: `translateX(-${idx * 100}%)`, transition: "transform 450ms cubic-bezier(0.4,0,0.2,1)" }}
        >
        {fbt.map((p) => (
          <div key={p.id} className="shrink-0 w-full px-0.5">
          <div className="border border-gray-200 rounded-xl bg-white p-3">
            <div className="flex gap-3">
              <div className="relative w-20 h-20 bg-gray-50 rounded shrink-0 flex items-center justify-center overflow-hidden">
                <img src={p.image} alt={p.name} className="max-w-full max-h-full object-contain" />
                {p.discount > 0 && (
                  <span className="absolute top-1 left-1 bg-orange-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">
                    {p.discount}% OFF
                  </span>
                )}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-brand-ink line-clamp-2">{p.name}</p>
                <p className="text-xs text-brand-muted mt-0.5">{p.warranty}</p>
                <div className="flex items-baseline gap-1.5 mt-1">
                  <span className="text-sm font-bold text-brand-ink">{fmt(p.price)}</span>
                  <span className="text-[10px] text-brand-muted line-through">₹{p.mrp.toLocaleString("en-IN")}.00</span>
                </div>
              </div>
            </div>
            <button
              onClick={() => onAdd(p)}
              className="mt-3 w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold text-sm py-2 rounded-md flex items-center justify-center gap-2 transition"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z" /></svg>
              Add
            </button>
          </div>
          </div>
        ))}
        </div>
      </div>
    </div>
  );
}

function TrustSeal() {
  return (
    <div className="px-5 py-5 bg-white border-b border-gray-100 flex flex-col items-center gap-3">
      <div className="flex flex-col items-center">
        <div className="relative w-[52px] h-[52px] flex items-center justify-center">
          <svg viewBox="0 0 100 100" className="absolute inset-0 w-full h-full" fill="none">
            <defs>
              <path id="cartTrustPath" d="M 50,50 m -38,0 a 38,38 0 1,1 76,0 a 38,38 0 1,1 -76,0" />
            </defs>
            <text fill="#6b7280" fontSize="13" fontWeight="700" letterSpacing="1">
              <textPath href="#cartTrustPath" startOffset="0">POWERED BY • POWERED BY • </textPath>
            </text>
          </svg>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#374151" strokeWidth="2">
            <path d="M12 2L3 7v6c0 5 4 9 9 10 5-1 9-5 9-10V7l-9-5z" />
          </svg>
        </div>
        <div className="flex items-center gap-1 mt-1">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" className="text-gray-800">
            <path d="M12 2L2 22h20L12 2z" />
          </svg>
          <span className="text-[11px] font-bold text-gray-800 tracking-tight">Storedum</span>
        </div>
      </div>

      <div className="flex items-center justify-center gap-5">
        {[
          { l1: "Verified", l2: "Merchant", p: "M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-4-4 1.41-1.41L11 12.17l5.59-5.59L18 8l-7 7z" },
          { l1: "Secure", l2: "Payments", p: "M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z" },
          { l1: "Buyer", l2: "Protection", p: "M19 7h-3V5.5C16 3.57 14.43 2 12.5 2h-1C9.57 2 8 3.57 8 5.5V7H5c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM10 5.5c0-.83.67-1.5 1.5-1.5h1c.83 0 1.5.67 1.5 1.5V7h-4V5.5zm6.5 9.8l-5 4.7-3-2.8 1.4-1.4 1.6 1.5 3.6-3.4 1.4 1.4z" },
        ].map((b) => (
          <div key={b.l1} className="flex items-center gap-1.5 text-gray-900">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d={b.p} /></svg>
            <div className="leading-tight">
              <div className="text-[11px] font-bold">{b.l1}</div>
              <div className="text-[10px] text-gray-600 font-medium">{b.l2}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function CouponsPanel({ code, setCode, tab, setTab, allCount, unavailableCount, coupons, subtotal, onSubmit, onApply, msg }) {
  return (
    <div className="-mx-5 -my-4 bg-gray-50 min-h-full">
      <div className="px-5 py-4 bg-white border-b border-gray-100">
        <div className="flex items-center gap-2 border border-gray-300 rounded-md px-3 py-2 bg-white">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" strokeWidth="2" className="shrink-0">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" />
            <line x1="7" y1="7" x2="7.01" y2="7" />
          </svg>
          <input
            type="text"
            value={code}
            onChange={(e) => setCode(e.target.value.toUpperCase())}
            onKeyDown={(e) => e.key === "Enter" && onSubmit()}
            placeholder="ENTER COUPON CODE"
            className="flex-1 min-w-0 text-sm text-brand-ink placeholder:text-gray-400 focus:outline-none bg-transparent uppercase tracking-wide"
          />
          {code && (
            <button
              onClick={onSubmit}
              className="text-[#3684bf] font-semibold text-sm hover:underline"
            >
              Apply
            </button>
          )}
        </div>
        {msg && (
          <p className={`mt-2 text-xs ${msg.includes("applied") ? "text-green-600" : "text-red-600"}`}>{msg}</p>
        )}
      </div>

      <div className="px-5 py-3 bg-white border-b border-gray-100 flex items-center gap-2">
        <button
          onClick={() => setTab("all")}
          className={`px-3 py-1.5 rounded-md text-sm font-semibold border transition ${
            tab === "all"
              ? "border-[#3684bf] text-[#3684bf] bg-blue-50"
              : "border-gray-200 text-brand-muted hover:bg-gray-50"
          }`}
        >
          All{allCount > 0 ? ` (${allCount})` : ""}
        </button>
        <button
          onClick={() => setTab("unavailable")}
          className={`px-3 py-1.5 rounded-md text-sm font-semibold border transition ${
            tab === "unavailable"
              ? "border-[#3684bf] text-[#3684bf] bg-blue-50"
              : "border-gray-200 text-brand-muted hover:bg-gray-50"
          }`}
        >
          Unavailable{unavailableCount > 0 ? ` (${unavailableCount})` : ""}
        </button>
      </div>

      <div className="px-5 py-5">
        {coupons.length === 0 ? (
          <div className="text-center text-sm text-brand-muted py-16">No coupons available</div>
        ) : (
          <ul className="space-y-3">
            {coupons.map((c) => {
              const eligible = subtotal >= c.minSubtotal;
              return (
                <li
                  key={c.code}
                  className={`relative bg-white border rounded-lg p-4 flex items-start gap-3 transition ${
                    eligible ? "border-gray-200 hover:border-[#3684bf]" : "border-gray-200 opacity-70"
                  }`}
                >
                  <div className="w-10 h-10 rounded-md bg-[#3684bf]/10 text-[#3684bf] flex items-center justify-center shrink-0">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" />
                      <line x1="7" y1="7" x2="7.01" y2="7" />
                    </svg>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-brand-ink text-sm">{c.code}</span>
                      <span className="text-[10px] font-bold text-green-700 bg-green-50 border border-green-100 rounded px-1.5 py-0.5 uppercase">
                        {c.title}
                      </span>
                    </div>
                    <p className="text-xs text-brand-muted mt-1">{c.desc}</p>
                    {!eligible && (
                      <p className="text-[11px] text-red-600 mt-1">
                        Add {`₹${(c.minSubtotal - subtotal).toLocaleString("en-IN")}`} more to use this coupon.
                      </p>
                    )}
                  </div>
                  <button
                    onClick={() => onApply(c)}
                    disabled={!eligible}
                    className={`shrink-0 text-xs font-bold uppercase px-3 py-1.5 rounded transition ${
                      eligible
                        ? "text-[#3684bf] hover:bg-blue-50"
                        : "text-gray-400 cursor-not-allowed"
                    }`}
                  >
                    Apply
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}

function EmptyCart({ closeModal }) {
  return (
    <div className="h-full flex flex-col bg-gray-50 -mx-5 -my-4 px-5 py-8">
      <div className="flex-1 flex flex-col items-center justify-center text-center">
        <div className="w-32 h-32 rounded-full bg-blue-100/60 flex items-center justify-center mb-6">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
          </svg>
        </div>
        <h3 className="text-2xl font-bold text-brand-ink mb-2">Your cart is empty</h3>
        <p className="text-sm text-brand-muted max-w-xs mb-6">
          Looks like you haven't added anything to your cart yet. Start shopping now!
        </p>
        <button
          onClick={closeModal}
          className="inline-flex items-center gap-2 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold px-6 py-3 rounded-lg shadow-md transition"
        >
          Continue Shopping
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M5 12h14M13 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
  );
}
