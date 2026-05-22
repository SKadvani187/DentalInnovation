import { useRef, useState } from "react";
import Drawer from "../ui/Drawer";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useAuth } from "../../context/AuthContext";
import { fbtItems as FBT_ITEMS, freeGifts, bulkRule } from "../../data/site";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const cartIcon = (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="9" cy="21" r="1" />
    <circle cx="20" cy="21" r="1" />
    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
  </svg>
);

const FREE_GIFTS = freeGifts.items;

export default function CartDrawer() {
  const { modal, closeModal, openModal } = useUI();
  const { items, updateQty, removeFromCart, subtotal, itemCount } = useCart();
  const { user } = useAuth();
  const [priceOpen, setPriceOpen] = useState(true);

  const onCheckout = () => {
    if (!user) {
      openModal("auth");
      return;
    }
    openModal("checkout");
  };

  const bulkSavings = items.reduce(
    (s, i) => (i.qty >= bulkRule.minQty ? s + i.price * bulkRule.rate * i.qty : s),
    0
  );
  const mrpTotal = items.reduce((s, i) => s + (i.mrp || i.price) * i.qty, 0);
  const finalTotal = Math.max(0, subtotal - bulkSavings);
  const totalSaved = Math.max(0, mrpTotal - finalTotal);
  const showFreeGifts = subtotal >= freeGifts.threshold;

  return (
    <Drawer
      open={modal === "cart"}
      onClose={closeModal}
      title="Your Cart"
      titleIcon={cartIcon}
      footer={
        items.length > 0 && (
          <div className="space-y-3 -mx-5 -my-4 px-5 py-4 bg-white border-t border-gray-200">
            {/* Price summary bar */}
            <button
              onClick={() => setPriceOpen((v) => !v)}
              className="w-full flex items-center justify-between bg-green-600 text-white px-4 py-2.5 rounded-md text-sm font-bold"
            >
              <span>
                Price summary <span className="line-through opacity-80 font-normal ml-2">Rs. {mrpTotal.toFixed(0)}</span>{" "}
                <span className="ml-2">{fmt(finalTotal)}</span>
              </span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" className={`transition ${priceOpen ? "" : "rotate-180"}`}>
                <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
              </svg>
            </button>

            {priceOpen && totalSaved > 0 && (
              <div className="bg-green-50 border border-green-100 rounded-md px-3 py-2 text-center text-sm text-green-800 font-semibold">
                🎉 🎁 You saved {fmt(totalSaved)} on this order!
              </div>
            )}

            <div className="flex items-center justify-between pt-1">
              <div>
                <div className="text-lg font-bold text-brand-ink">Rs. {fmt(finalTotal)}</div>
                <button className="text-xs text-[#3684bf] underline">View Price Details</button>
              </div>
              <button
                onClick={onCheckout}
                className="place-order-btn inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-black font-bold px-5 py-3 rounded-md transition"
              >
                Place Order
                <span className="flex items-center gap-1 ml-2 bg-white rounded px-1.5 py-0.5">
                  <span className="text-[8px] font-bold text-blue-700">P</span>
                  <span className="w-3 h-3 rounded-full bg-purple-600" />
                  <span className="text-[8px] font-bold text-orange-600">G</span>
                </span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M9 6l6 6-6 6" /></svg>
              </button>
            </div>
          </div>
        )
      }
    >
      {items.length === 0 ? (
        <EmptyCart closeModal={closeModal} />
      ) : (
        <div className="-mx-5 -my-4">
          <div className="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <span className="text-sm font-bold uppercase tracking-wider text-brand-ink">
              {items[0]?.name?.split(" ").slice(0, 2).join(" ").toUpperCase() || "CART"}{" "}
              <span className="text-brand-muted font-normal">+{Math.max(0, items.length - 1)} MORE</span>
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
                    <p className="text-sm font-semibold text-brand-ink line-clamp-2">{i.name}</p>
                    <button
                      onClick={() => removeFromCart(i.key)}
                      className="text-brand-muted hover:text-red-500 shrink-0"
                      aria-label="Remove"
                    >
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="3 6 5 6 21 6" /><path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
                      </svg>
                    </button>
                  </div>

                  {i.variant && (
                    <span className="inline-block mt-1 text-[11px] font-semibold text-[#3684bf] bg-blue-50 border border-blue-100 rounded px-2 py-0.5">
                      {i.variant}
                    </span>
                  )}

                  <div className="mt-2 flex items-center justify-between">
                    <div className="flex items-baseline gap-2">
                      <span className="text-base font-bold text-brand-ink">{fmt(i.price)}</span>
                      {i.mrp && i.mrp > i.price && (
                        <span className="text-xs text-brand-muted line-through">₹{i.mrp.toLocaleString("en-IN")}.00</span>
                      )}
                    </div>
                    <div className="inline-flex items-center border border-gray-300 rounded-md text-sm bg-white">
                      <button onClick={() => updateQty(i.key, i.qty - 1)} className="w-7 h-7 hover:bg-gray-50 text-brand-ink">−</button>
                      <span className="w-8 text-center font-semibold">{i.qty}</span>
                      <button onClick={() => updateQty(i.key, i.qty + 1)} className="w-7 h-7 hover:bg-gray-50 text-brand-ink">+</button>
                    </div>
                  </div>

                  {i.qty >= bulkRule.minQty && (
                    <div className="mt-2 bg-green-50 border border-green-100 rounded text-xs text-green-800 font-semibold px-2 py-1.5 flex items-center gap-1.5">
                      <span>🔥</span> You got {fmt(i.price * bulkRule.rate * i.qty)} saving due to bulk buying
                    </div>
                  )}
                </div>
              </li>
            ))}
          </ul>

          {showFreeGifts && (
            <div className="px-5 py-4 bg-white border-b border-gray-100">
              <div className="flex items-center gap-2 mb-3">
                <span className="text-lg">🎁</span>
                <h4 className="font-bold text-brand-ink">Free Gifts</h4>
                <span className="bg-green-100 text-green-800 text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">{FREE_GIFTS.length}</span>
              </div>
              <div className="space-y-2">
                {FREE_GIFTS.map((g) => (
                  <div key={g.id} className="flex items-center gap-3 bg-white border border-gray-100 rounded-lg p-2">
                    <div className="w-14 h-14 bg-gray-50 rounded shrink-0 flex items-center justify-center overflow-hidden">
                      <img src={g.image} alt={g.name} className="max-w-full max-h-full object-contain" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-semibold text-brand-ink line-clamp-1">{g.name}</p>
                      <div className="flex items-baseline gap-2 mt-0.5">
                        <span className="text-xs text-brand-muted line-through">Rs. {g.mrp}</span>
                        <span className="text-xs font-bold text-green-600">FREE</span>
                      </div>
                    </div>
                    <span className="text-xs text-brand-muted bg-gray-100 rounded-full px-2 py-0.5">x1</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="px-5 py-2 bg-gray-50 border-b border-gray-200">
            <span className="text-xs text-brand-muted">Offers & Rewards</span>
          </div>

          <div className="px-5 py-3 bg-white border-b border-gray-100">
            <button className="w-full flex items-center justify-between border border-gray-200 rounded-lg px-4 py-3 hover:border-green-500 hover:bg-green-50 transition">
              <span className="flex items-center gap-2 text-green-700 font-semibold">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
                View Coupons
              </span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5"><path d="M9 6l6 6-6 6" /></svg>
            </button>
          </div>

          <FrequentlyBought />

          <TrustSeal />
        </div>
      )}
    </Drawer>
  );
}

function FrequentlyBought() {
  const { addToCart } = useCart();
  const scroller = useRef(null);

  const onAdd = (item) => {
    addToCart(item, 1);
  };

  return (
    <div className="px-5 py-3 bg-white border-b border-gray-100">
      <div className="flex items-center justify-between mb-2">
        <h4 className="font-bold text-brand-ink flex items-center gap-2">
          <span>✨</span> Frequently Bought Together
        </h4>
        <span className="text-xs text-brand-muted">Swipe →</span>
      </div>
      <div ref={scroller} className="flex gap-3 overflow-x-auto no-scrollbar -mx-1 px-1 pb-1">
        {FBT_ITEMS.map((p) => (
          <div key={p.id} className="shrink-0 w-[260px] border border-gray-200 rounded-xl bg-white p-3">
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
        ))}
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
