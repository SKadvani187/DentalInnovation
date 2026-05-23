import { useEffect, useState } from "react";
import { offerZone } from "../../data/offers";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

function useCountdown(target) {
  const [time, setTime] = useState(() => computeRemaining(target));
  useEffect(() => {
    const t = setInterval(() => setTime(computeRemaining(target)), 60000);
    return () => clearInterval(t);
  }, [target]);
  return time;
}

function computeRemaining(targetDate) {
  const end = new Date(targetDate).getTime();
  const now = Date.now();
  let diff = Math.max(0, end - now);
  const days = Math.floor(diff / 86400000);
  diff -= days * 86400000;
  const hrs = Math.floor(diff / 3600000);
  diff -= hrs * 3600000;
  const mins = Math.floor(diff / 60000);
  return { days, hrs, mins };
}

export default function OfferZonePage() {
  return (
    <div className="max-w-[1400px] mx-auto px-4 py-6">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {offerZone.map((offer) => (
          <OfferCard key={offer.id} offer={offer} />
        ))}
      </div>
    </div>
  );
}

function OfferCard({ offer }) {
  const { addToCart, items, updateQty, removeFromCart } = useCart();
  const { openModal, navigate } = useUI();
  const { days, hrs, mins } = useCountdown(offer.validTill);
  const validDate = new Date(offer.validTill).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });

  const cartItem = items.find((i) => i.id === offer.mainProduct.productId);
  const inCart = !!cartItem;

  const onAdd = () => {
    addToCart(
      {
        id: offer.mainProduct.productId,
        name: offer.mainProduct.name,
        image: offer.mainProduct.image,
        price: offer.specialPrice,
        mrp: offer.totalMrp,
        category: "offer",
      },
      1
    );
    openModal("cart");
  };

  const onDec = () => {
    if (!cartItem) return;
    if (cartItem.qty <= 1) removeFromCart(cartItem.key);
    else updateQty(cartItem.key, cartItem.qty - 1);
  };
  const onInc = () => cartItem && updateQty(cartItem.key, cartItem.qty + 1);
  const onView = () => openModal("cart");

  const goProduct = () => offer.mainProduct.productId && navigate("product", { id: offer.mainProduct.productId });

  return (
    <article
      className="rounded-2xl border-2 overflow-hidden flex flex-col"
      style={{ background: offer.gradient, borderColor: offer.accent + "55" }}
    >
      <div className="pt-5 pb-3 px-5 flex flex-col items-center text-center">
        <span
          className="text-[11px] font-bold text-white uppercase tracking-wider px-4 py-1 rounded-full mb-2"
          style={{ background: offer.cta }}
        >
          <span className="inline-block w-1.5 h-1.5 bg-white rounded-full mr-1.5 align-middle" />
          LIMITED TIME OFFER
        </span>
        <h3 className="text-3xl font-bold text-brand-ink">{offer.title}</h3>
        {offer.subtitle && (
          <p className="text-xs text-brand-muted mt-1 px-4">{offer.subtitle}</p>
        )}
      </div>

      <div className="mx-4 bg-white rounded-xl p-3 flex items-center gap-3 cursor-pointer" onClick={goProduct}>
        <div className="w-16 h-16 bg-gray-50 rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
          <img src={offer.mainProduct.image} alt={offer.mainProduct.name} className="max-w-full max-h-full object-contain" />
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-bold text-brand-ink line-clamp-2">{offer.mainProduct.name}</p>
          {offer.mainProduct.variant && (
            <p className="text-xs text-brand-muted">{offer.mainProduct.variant}</p>
          )}
          {offer.mainProduct.rating != null && (
            <p className="text-xs text-amber-500 font-semibold">
              ★ {offer.mainProduct.rating} <span className="text-brand-muted font-normal">({offer.mainProduct.reviews} reviews)</span>
            </p>
          )}
          <div className="flex items-baseline gap-2 mt-0.5">
            <span className="text-sm font-bold" style={{ color: offer.accent }}>{fmt(offer.mainProduct.price)}</span>
            <span className="text-xs text-brand-muted line-through">₹{offer.mainProduct.mrp.toLocaleString("en-IN")}</span>
          </div>
        </div>
      </div>

      {offer.freeItems.length > 0 && (
        <>
          <div className="px-4 my-3 flex items-center gap-2">
            <div className="flex-1 border-t border-dashed border-gray-300" />
            <span className="text-[10px] font-bold text-[#3684bf] uppercase tracking-wider bg-white px-3 py-1 rounded-full border border-gray-200 inline-flex items-center gap-1">
              🎁 FREE ITEMS INCLUDED
            </span>
            <div className="flex-1 border-t border-dashed border-gray-300" />
          </div>
          <div className="px-4 space-y-2">
            {offer.freeItems.map((f, i) => (
              <div key={i} className="relative bg-white rounded-lg p-2 flex items-center gap-3 border border-gray-100">
                <span className="absolute -top-2 left-2 bg-green-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">FREE</span>
                <div className="w-12 h-12 bg-gray-50 rounded shrink-0 overflow-hidden flex items-center justify-center">
                  <img src={f.image} alt={f.name} className="max-w-full max-h-full object-contain" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-semibold text-brand-ink line-clamp-1">{f.name}</p>
                  {f.variant && <p className="text-[10px] text-brand-muted">{f.variant}</p>}
                  <span className="text-xs text-brand-muted line-through">₹{f.mrp.toLocaleString("en-IN")}</span>
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      <div className="px-5 pt-5 pb-3 text-center mt-auto">
        <p className="text-[10px] font-bold uppercase tracking-wider text-brand-muted mb-1">SPECIAL OFFER PRICE</p>
        <div className="text-4xl font-bold" style={{ color: offer.accent }}>{fmt(offer.specialPrice)}</div>
        <p className="text-xs text-brand-muted mt-1">
          Total <span className="line-through">₹{offer.totalMrp.toLocaleString("en-IN")}</span>
        </p>
        <span className="inline-block mt-2 text-[11px] font-bold text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
          ★ You Save ₹{offer.youSave.toLocaleString("en-IN")}{offer.saveExtra ? ` + ${offer.saveExtra}` : ""}
        </span>
      </div>

      <div className="mx-4 mb-3 bg-white rounded-xl p-3 flex items-center justify-between gap-2 border border-gray-100">
        <div className="flex items-center gap-2">
          <div className="w-9 h-9 rounded-full flex items-center justify-center text-white" style={{ background: offer.accent }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zM5 8V6h14v2H5z" />
            </svg>
          </div>
          <div className="leading-tight">
            <p className="text-[9px] font-bold uppercase tracking-wider text-brand-muted">VALID TILL</p>
            <p className="text-sm font-bold text-brand-ink">{validDate}</p>
          </div>
        </div>
        <div className="flex items-center gap-1">
          {[
            { v: String(days).padStart(2, "0"), l: "DAYS" },
            { v: String(hrs).padStart(2, "0"), l: "HRS" },
            { v: String(mins).padStart(2, "0"), l: "MIN" },
          ].map((t) => (
            <div key={t.l} className="bg-white border border-gray-200 rounded px-1.5 py-1 text-center leading-tight">
              <div className="text-xs font-bold text-brand-ink">{t.v}</div>
              <div className="text-[8px] text-brand-muted">{t.l}</div>
            </div>
          ))}
        </div>
      </div>

      {inCart ? (
        <div className="mx-4 mb-3 flex items-stretch gap-2">
          <div className="flex-1 flex items-center justify-between bg-white border border-gray-200 rounded-xl px-2 py-1">
            <button
              onClick={onDec}
              aria-label="Decrease quantity"
              className="w-9 h-9 rounded-lg hover:bg-gray-100 flex items-center justify-center text-brand-ink"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 6v13a2 2 0 002 2h14a2 2 0 002-2V6H3zm5 4h8v2H8v-2zM15 4l-1-1h-4l-1 1H5v2h14V4z" />
              </svg>
            </button>
            <span className="text-base font-bold text-brand-ink select-none">{cartItem.qty}</span>
            <button
              onClick={onInc}
              aria-label="Increase quantity"
              className="w-9 h-9 rounded-lg hover:bg-gray-100 flex items-center justify-center text-brand-ink text-xl font-bold"
            >
              +
            </button>
          </div>
          <button
            onClick={onView}
            className="flex-1 text-white font-bold rounded-xl flex items-center justify-center gap-2 shadow-md hover:opacity-90 transition px-3"
            style={{ background: offer.cta }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z" />
            </svg>
            View Cart
          </button>
        </div>
      ) : (
        <button
          onClick={onAdd}
          className="mx-4 mb-3 text-white font-bold py-3 rounded-xl flex items-center justify-center gap-2 shadow-md hover:opacity-90 transition"
          style={{ background: offer.cta }}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z" />
          </svg>
          Add to Cart
        </button>
      )}

      <div className="px-5 pb-4 flex items-center justify-center gap-3 text-[11px] text-brand-muted">
        <span className="inline-flex items-center gap-1">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5 5H11v6l5.25 3.15.75-1.23-4.5-2.67z" /></svg>
          Offer Ends Soon
        </span>
        <span>*T&C Apply</span>
      </div>
    </article>
  );
}
