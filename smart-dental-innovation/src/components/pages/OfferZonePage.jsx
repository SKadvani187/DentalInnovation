import { useEffect, useMemo, useState } from "react";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useSettings } from "../../context/SettingsContext";
import { useOffers } from "../../hooks/useApiData";

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
  return { days, hrs, mins, ended: end - now <= 0 };
}

const FILTERS = [
  { id: "all", label: "All Offers" },
  { id: "free-gift", label: "With Free Gift" },
  { id: "ending-soon", label: "Ending Soon" },
  { id: "best-value", label: "Best Value" },
];

const SORTS = [
  { id: "trending", label: "Trending" },
  { id: "biggest-save", label: "Biggest Savings" },
  { id: "ending-soon", label: "Ending Soon" },
  { id: "price-low", label: "Price: Low to High" },
  { id: "price-high", label: "Price: High to Low" },
];

export default function OfferZonePage() {
  const [filter, setFilter] = useState("all");
  const [sort, setSort] = useState("trending");
  const { data: offerZone } = useOffers();

  const stats = useMemo(() => {
    const totalSaved = offerZone.reduce((s, o) => s + (o.youSave || 0), 0);
    const endingToday = offerZone.filter((o) => {
      const { days, ended } = computeRemaining(o.validTill);
      return !ended && days === 0;
    }).length;
    const withFreeGift = offerZone.filter((o) => o.freeItems?.length > 0).length;
    return { count: offerZone.length, totalSaved, endingToday, withFreeGift };
  }, [offerZone]);

  const heroDeadline = useMemo(() => {
    const future = offerZone
      .map((o) => new Date(o.validTill).getTime())
      .filter((t) => t > Date.now())
      .sort((a, b) => a - b)[0];
    return future ? new Date(future).toISOString() : null;
  }, [offerZone]);

  const filtered = useMemo(() => {
    let list = [...offerZone];
    if (filter === "free-gift") list = list.filter((o) => o.freeItems?.length > 0);
    if (filter === "ending-soon") {
      list = list.filter((o) => {
        const { days, ended } = computeRemaining(o.validTill);
        return !ended && days <= 2;
      });
    }
    if (filter === "best-value") {
      const med = list.map((o) => o.youSave || 0).sort((a, b) => a - b)[Math.floor(list.length / 2)];
      list = list.filter((o) => (o.youSave || 0) >= med);
    }

    switch (sort) {
      case "biggest-save":
        list.sort((a, b) => (b.youSave || 0) - (a.youSave || 0));
        break;
      case "ending-soon":
        list.sort((a, b) => new Date(a.validTill) - new Date(b.validTill));
        break;
      case "price-low":
        list.sort((a, b) => a.specialPrice - b.specialPrice);
        break;
      case "price-high":
        list.sort((a, b) => b.specialPrice - a.specialPrice);
        break;
      default:
        break;
    }
    return list;
  }, [filter, sort, offerZone]);

  return (
    <div className="bg-gradient-to-b from-[#fff9f3] via-white to-white min-h-screen">
      <HeroBanner stats={stats} deadline={heroDeadline} />

      <div className="max-w-[1400px] mx-auto px-3 sm:px-6 -mt-8 relative z-10">
        <FilterBar
          filter={filter}
          setFilter={setFilter}
          sort={sort}
          setSort={setSort}
          count={filtered.length}
          stats={stats}
        />

        {filtered.length === 0 ? (
          <EmptyState onReset={() => { setFilter("all"); setSort("trending"); }} />
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 pb-12">
            {filtered.map((offer, i) => (
              <OfferCard key={offer.id} offer={offer} rank={i} />
            ))}
          </div>
        )}

        <ValueProps />
      </div>
    </div>
  );
}

function HeroBanner({ stats, deadline }) {
  const { offerZoneHero: hero = {} } = useSettings();
  const { days, hrs, mins } = useCountdown(deadline || new Date().toISOString());
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-[#ff6b6b] via-[#ee5253] to-[#c0392b] text-white">
      <div className="absolute inset-0 opacity-20 pointer-events-none">
        <div className="absolute -top-20 -left-20 w-72 h-72 rounded-full bg-white blur-3xl" />
        <div className="absolute top-10 right-10 w-56 h-56 rounded-full bg-yellow-300 blur-3xl opacity-60" />
        <div className="absolute -bottom-20 left-1/3 w-80 h-80 rounded-full bg-pink-200 blur-3xl opacity-40" />
      </div>

      <div className="relative max-w-[1400px] mx-auto px-4 sm:px-6 py-10 sm:py-14 flex flex-col lg:flex-row items-center gap-6">
        <div className="flex-1 text-center lg:text-left">
          <span className="inline-flex items-center gap-2 bg-white/20 backdrop-blur rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider mb-3">
            <span className="w-2 h-2 rounded-full bg-yellow-300 animate-pulse" />
            {hero.badge}
          </span>
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">
            {hero.title}
            <span className="block text-yellow-200 text-2xl sm:text-3xl mt-1 font-bold">
              {hero.savePrefix} {fmt(stats.totalSaved)} {hero.saveSuffix}
            </span>
          </h1>
          <p className="text-sm sm:text-base text-white/90 mt-3 max-w-xl mx-auto lg:mx-0">
            {hero.subtitle}
          </p>

          <div className="flex flex-wrap items-center justify-center lg:justify-start gap-4 mt-5">
            <Stat label="Active Deals" value={stats.count} />
            <Stat label="Free Gifts" value={stats.withFreeGift} />
            <Stat label="Ending Today" value={stats.endingToday} highlight />
          </div>
        </div>

        <div className="shrink-0 bg-white/15 backdrop-blur-md border border-white/25 rounded-2xl px-5 py-4 text-center min-w-[280px]">
          <p className="text-[11px] font-bold uppercase tracking-wider text-yellow-200 mb-2">{hero.expiryLabel}</p>
          <div className="flex items-center justify-center gap-2">
            {[
              { v: String(days).padStart(2, "0"), l: "Days" },
              { v: String(hrs).padStart(2, "0"), l: "Hours" },
              { v: String(mins).padStart(2, "0"), l: "Mins" },
            ].map((t) => (
              <div key={t.l} className="bg-black/30 rounded-lg px-3 py-2 min-w-[60px]">
                <div className="text-2xl font-extrabold tabular-nums">{t.v}</div>
                <div className="text-[10px] uppercase tracking-wider text-white/80">{t.l}</div>
              </div>
            ))}
          </div>
          <p className="text-[11px] text-white/80 mt-3">{hero.restockNote}</p>
        </div>
      </div>
    </section>
  );
}

function Stat({ label, value, highlight }) {
  return (
    <div className="flex items-center gap-2">
      <span
        className={`text-2xl sm:text-3xl font-extrabold ${highlight ? "text-yellow-200" : "text-white"}`}
      >
        {value}
      </span>
      <span className="text-xs text-white/80 leading-tight">{label}</span>
    </div>
  );
}

function FilterBar({ filter, setFilter, sort, setSort, count, stats }) {
  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm px-3 sm:px-5 py-3 mb-6 flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
      <div className="flex-1 flex items-center gap-2 overflow-x-auto no-scrollbar">
        {FILTERS.map((f) => {
          const active = filter === f.id;
          return (
            <button
              key={f.id}
              onClick={() => setFilter(f.id)}
              className={`shrink-0 px-3 sm:px-4 py-1.5 rounded-full text-xs sm:text-sm font-semibold transition-all ${
                active
                  ? "bg-[#3684bf] text-white shadow-sm"
                  : "bg-gray-100 text-brand-ink hover:bg-gray-200"
              }`}
            >
              {f.label}
              {f.id === "ending-soon" && stats.endingToday > 0 && (
                <span className={`ml-1.5 text-[10px] font-bold rounded-full px-1.5 py-0.5 ${active ? "bg-white/25" : "bg-red-500 text-white"}`}>
                  {stats.endingToday}
                </span>
              )}
              {f.id === "free-gift" && stats.withFreeGift > 0 && (
                <span className={`ml-1.5 text-[10px] font-bold rounded-full px-1.5 py-0.5 ${active ? "bg-white/25" : "bg-green-500 text-white"}`}>
                  {stats.withFreeGift}
                </span>
              )}
            </button>
          );
        })}
      </div>

      <div className="flex items-center justify-between gap-3 shrink-0">
        <span className="hidden sm:block text-xs text-brand-muted">
          Showing <span className="font-bold text-brand-ink">{count}</span> offer{count !== 1 ? "s" : ""}
        </span>
        <div className="flex items-center gap-2">
          <span className="text-xs text-brand-muted hidden sm:block">Sort:</span>
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="bg-gray-50 border border-gray-200 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#3684bf]"
          >
            {SORTS.map((s) => (
              <option key={s.id} value={s.id}>{s.label}</option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
}

function EmptyState({ onReset }) {
  return (
    <div className="bg-white border border-gray-100 rounded-2xl py-16 px-6 text-center mb-8">
      <div className="text-5xl mb-3">🎁</div>
      <h3 className="text-xl font-bold text-brand-ink mb-1">No offers match this filter</h3>
      <p className="text-sm text-brand-muted mb-4">Try a different filter — fresh deals drop every week.</p>
      <button
        onClick={onReset}
        className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold text-sm px-5 py-2 rounded-md transition"
      >
        Show all offers
      </button>
    </div>
  );
}

function OfferCard({ offer, rank }) {
  const { addToCart, items, updateQty, removeFromCart } = useCart();
  const { openModal, navigate } = useUI();
  const { days, hrs, mins, ended } = useCountdown(offer.validTill);
  const validDate = new Date(offer.validTill).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });

  const cartItem = items.find((i) => i.id === offer.mainProduct.productId);
  const inCart = !!cartItem;

  const discountPct = offer.totalMrp > 0 ? Math.round(((offer.totalMrp - offer.specialPrice) / offer.totalMrp) * 100) : 0;
  const urgent = !ended && days === 0 && hrs <= 12;
  const isTopDeal = rank === 0;

  const social = useMemo(() => {
    const seed = offer.id.split("").reduce((s, c) => s + c.charCodeAt(0), 0);
    return 12 + (seed % 38);
  }, [offer.id]);

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
      className="group relative rounded-2xl border-2 overflow-hidden flex flex-col bg-white transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl"
      style={{ background: offer.gradient, borderColor: offer.accent + "55" }}
    >
      {discountPct > 0 && (
        <div
          className="absolute top-3 left-3 z-10 text-white font-extrabold text-xs px-2.5 py-1 rounded-lg shadow-md"
          style={{ background: offer.cta }}
        >
          {discountPct}% OFF
        </div>
      )}

      {isTopDeal && (
        <div className="absolute top-3 right-3 z-10 bg-yellow-400 text-yellow-900 font-extrabold text-[10px] px-2 py-1 rounded-md shadow-md uppercase flex items-center gap-1">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" /></svg>
          Top Deal
        </div>
      )}

      <div className="pt-12 pb-3 px-5 flex flex-col items-center text-center">
        <span
          className="text-[11px] font-bold text-white uppercase tracking-wider px-4 py-1 rounded-full mb-2"
          style={{ background: offer.cta }}
        >
          <span className="inline-block w-1.5 h-1.5 bg-white rounded-full mr-1.5 align-middle" />
          LIMITED TIME OFFER
        </span>
        <h3 className="text-2xl sm:text-3xl font-bold text-brand-ink leading-tight">{offer.title}</h3>
        {offer.subtitle && (
          <p className="text-xs text-brand-muted mt-1 px-2">{offer.subtitle}</p>
        )}
      </div>

      <div className="mx-4 bg-white rounded-xl p-3 flex items-center gap-3 cursor-pointer hover:shadow-md transition" onClick={goProduct}>
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

      {offer.freeItems?.length > 0 && (
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
        <div className="text-4xl font-bold tabular-nums" style={{ color: offer.accent }}>{fmt(offer.specialPrice)}</div>
        <p className="text-xs text-brand-muted mt-1">
          Total <span className="line-through">₹{offer.totalMrp.toLocaleString("en-IN")}</span>
        </p>
        <span className="inline-block mt-2 text-[11px] font-bold text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
          ★ You Save ₹{offer.youSave.toLocaleString("en-IN")}{offer.saveExtra ? ` + ${offer.saveExtra}` : ""}
        </span>
      </div>

      {urgent && (
        <div className="mx-4 mb-2 bg-red-50 border border-red-200 rounded-lg px-3 py-1.5 text-center">
          <span className="text-[11px] font-bold text-red-600 uppercase tracking-wider inline-flex items-center gap-1.5">
            <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />
            Hurry! Less than 12 hours left
          </span>
        </div>
      )}

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
              <div className="text-xs font-bold text-brand-ink tabular-nums">{t.v}</div>
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
          className="mx-4 mb-3 text-white font-bold py-3 rounded-xl flex items-center justify-center gap-2 shadow-md hover:opacity-95 active:scale-[0.98] transition"
          style={{ background: offer.cta }}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z" />
          </svg>
          Grab This Deal
        </button>
      )}

      <div className="px-5 pb-4 flex items-center justify-between gap-3 text-[11px] text-brand-muted">
        <span className="inline-flex items-center gap-1">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" /></svg>
          {social} clinics bought today
        </span>
        <span>*T&C Apply</span>
      </div>
    </article>
  );
}

function ValueProps() {
  const items = [
    {
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#3684bf"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-4-4 1.41-1.41L11 12.17l5.59-5.59L18 8l-7 7z" /></svg>
      ),
      title: "100% Genuine",
      desc: "Manufacturer-sourced, batch-tested",
    },
    {
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#16a34a"><path d="M19 7c0-1.1-.9-2-2-2h-3V3H10v2H7c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V7zm-7 3.5L9 13l3 2.5L9 18l3-2.5L15 18l-3-2.5L15 13l-3-2.5z" /></svg>
      ),
      title: "Pan-India Shipping",
      desc: "5–7 day delivery to most pincodes",
    },
    {
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#f97316"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2z" /></svg>
      ),
      title: "Doctor-Loved",
      desc: "Trusted by 1000+ clinics across India",
    },
    {
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#ec4899"><path d="M21 6h-3.17L16 4h-6v2h5.12l1.83 2H21v12H5v-9H3v9c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM8 14c0 2.76 2.24 5 5 5s5-2.24 5-5-2.24-5-5-5-5 2.24-5 5zm5-3c1.65 0 3 1.35 3 3s-1.35 3-3 3-3-1.35-3-3 1.35-3 3-3zM5 6h3V4H5V1H3v3H0v2h3v3h2z" /></svg>
      ),
      title: "Easy Returns",
      desc: "7-day no-questions-asked returns",
    },
  ];
  return (
    <div className="mt-2 mb-10 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      {items.map((it) => (
        <div key={it.title} className="bg-white border border-gray-100 rounded-xl p-4 flex items-start gap-3 hover:border-[#3684bf] hover:shadow-md transition">
          <div className="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">{it.icon}</div>
          <div className="min-w-0">
            <p className="text-sm font-bold text-brand-ink leading-tight">{it.title}</p>
            <p className="text-xs text-brand-muted mt-0.5">{it.desc}</p>
          </div>
        </div>
      ))}
    </div>
  );
}
