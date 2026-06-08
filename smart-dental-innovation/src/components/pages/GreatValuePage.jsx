import { useMemo, useState } from "react";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useSettings } from "../../context/SettingsContext";
import { useProducts, useCombos, useCategories } from "../../hooks/useApiData";
import { discountPct } from "../../lib/pricing";

const fmt = (n) => `₹${Number(n || 0).toLocaleString("en-IN")}`;

export default function GreatValuePage() {
  const { addToCart } = useCart();
  const { openModal } = useUI();
  const navigate = useAppNavigate();
  const { gvpThreshold = 10, gvpPage: cfg = {} } = useSettings();
  const { data: allProducts } = useProducts();
  const { data: combos } = useCombos();
  const { data: catData } = useCategories();

  const [cat, setCat] = useState(null);
  const [sort, setSort] = useState("discount");

  // All deal items = products + combos with discount >= threshold.
  const deals = useMemo(
    () => [...(combos || []), ...(allProducts || [])].filter((p) => p && p.id && (p.discount || 0) >= gvpThreshold),
    [allProducts, combos, gvpThreshold]
  );

  // Category chips that actually have deals.
  const chips = useMemo(() => {
    const counts = {};
    deals.forEach((d) => { if (d.category) counts[d.category] = (counts[d.category] || 0) + 1; });
    return (catData || []).filter((c) => counts[c.id]).map((c) => ({ id: c.id, label: c.title, count: counts[c.id] }));
  }, [deals, catData]);

  const filtered = useMemo(() => {
    let list = cat ? deals.filter((d) => d.category === cat) : [...deals];
    switch (sort) {
      case "discount": list.sort((a, b) => (b.discount || 0) - (a.discount || 0)); break;
      case "save": list.sort((a, b) => ((b.mrp - b.price) || 0) - ((a.mrp - a.price) || 0)); break;
      case "price-low": list.sort((a, b) => (a.price || 0) - (b.price || 0)); break;
      case "price-high": list.sort((a, b) => (b.price || 0) - (a.price || 0)); break;
      default: break;
    }
    return list;
  }, [deals, cat, sort]);

  const totalSaved = useMemo(
    () => deals.reduce((s, d) => s + Math.max(0, (d.mrp || 0) - (d.price || 0)), 0),
    [deals]
  );
  const maxDisc = useMemo(() => deals.reduce((m, d) => Math.max(m, d.discount || 0), 0), [deals]);

  return (
    <div className="bg-gradient-to-b from-[#fff7ed] via-white to-white min-h-screen">
      {/* Hero */}
      <section className="relative overflow-hidden bg-gradient-to-br from-[#b45309] via-[#ea580c] to-[#f97316] text-white">
        <div className="absolute inset-0 opacity-20 pointer-events-none">
          <div className="absolute -top-16 -left-16 w-64 h-64 rounded-full bg-yellow-300 blur-3xl" />
          <div className="absolute -bottom-16 right-10 w-72 h-72 rounded-full bg-white blur-3xl opacity-40" />
        </div>
        <div className="relative max-w-[1400px] mx-auto px-4 sm:px-6 py-9 sm:py-12">
          <span className="inline-flex items-center gap-2 bg-white/15 backdrop-blur rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider mb-3">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z" /></svg>
            {cfg.heroBadge || "Great Value Deals"}
          </span>
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">
            {cfg.heroTitle || "Best Value Products"}
            {totalSaved > 0 && (
              <span className="block text-yellow-100 text-xl sm:text-2xl mt-1 font-bold">
                {cfg.savePrefix || "Save up to"} {fmt(totalSaved)} {cfg.saveSuffix || "across"} {deals.length} deal{deals.length !== 1 ? "s" : ""}
              </span>
            )}
          </h1>
          <p className="text-sm sm:text-base text-white/90 mt-2 max-w-xl">
            {cfg.subtitle || "Hand-picked products with the biggest discounts — clinic essentials at unbeatable prices."}
          </p>
          <div className="flex flex-wrap gap-4 mt-5">
            <Stat value={deals.length} label={cfg.statDeals || "Live deals"} />
            <Stat value={`${maxDisc}%`} label={cfg.statDiscount || "Max discount"} highlight />
            <Stat value={fmt(totalSaved)} label={cfg.statSavings || "Total savings"} />
          </div>
        </div>
      </section>

      <div className="max-w-[1400px] mx-auto px-3 sm:px-6 -mt-6 relative z-10">
        {/* Toolbar — Category + Sort dropdowns (clean, no chip clutter) */}
        <div className="bg-white border border-gray-100 rounded-2xl shadow-sm px-4 py-3 mb-5 flex items-center justify-between gap-3 flex-wrap">
          <span className="text-sm text-brand-muted">
            <span className="font-bold text-brand-ink">{filtered.length}</span> deal{filtered.length !== 1 ? "s" : ""}
          </span>
          <div className="flex items-center gap-2 sm:gap-3 flex-wrap">
            {chips.length > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-xs text-brand-muted hidden sm:block">Category:</span>
                <select
                  value={cat || ""}
                  onChange={(e) => setCat(e.target.value || null)}
                  className="bg-gray-50 border border-gray-200 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#ea580c] max-w-[180px]"
                >
                  <option value="">All Deals ({deals.length})</option>
                  {chips.map((c) => (
                    <option key={c.id} value={c.id}>{c.label} ({c.count})</option>
                  ))}
                </select>
              </div>
            )}
            <div className="flex items-center gap-2">
              <span className="text-xs text-brand-muted hidden sm:block">Sort:</span>
              <select value={sort} onChange={(e) => setSort(e.target.value)} className="bg-gray-50 border border-gray-200 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#ea580c]">
                <option value="discount">Top Discount</option>
                <option value="save">Biggest Savings</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
              </select>
            </div>
          </div>
        </div>

        {/* Grid */}
        {filtered.length === 0 ? (
          <div className="bg-white border border-gray-100 rounded-2xl py-16 px-6 text-center mb-10">
            <div className="text-5xl mb-3">🔥</div>
            <h3 className="text-xl font-bold text-brand-ink mb-1">No deals here right now</h3>
            <p className="text-sm text-brand-muted">Try another category — fresh deals drop regularly.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 pb-12">
            {filtered.map((p) => (
              <DealCard
                key={p.id}
                product={p}
                onOpen={() => navigate("product", { id: p.id, name: p.name })}
                onAdd={() => { addToCart(p, 1); openModal("cart"); }}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function Stat({ value, label, highlight }) {
  return (
    <div className="flex items-center gap-2">
      <span className={`text-2xl sm:text-3xl font-extrabold ${highlight ? "text-yellow-200" : "text-white"}`}>{value}</span>
      <span className="text-xs text-white/80 leading-tight">{label}</span>
    </div>
  );
}

function DealCard({ product: p, onOpen, onAdd }) {
  const save = Math.max(0, (p.mrp || 0) - (p.price || 0));
  const disc = p.discount || discountPct(p.mrp, p.price);
  const out = p.inStock === false;
  return (
    <article className="group relative border border-gray-200 rounded-2xl bg-white overflow-hidden flex flex-col hover:shadow-md hover:border-[#ea580c]/40 transition-all">
      {disc > 0 && (
        <span className="absolute top-2.5 left-2.5 z-10 bg-gradient-to-r from-[#ea580c] to-[#f97316] text-white text-[11px] font-extrabold px-2 py-0.5 rounded-md shadow-sm">{disc}% OFF</span>
      )}
      {out && <span className="absolute top-2.5 right-2.5 z-10 bg-gray-700 text-white text-[9px] font-bold px-2 py-0.5 rounded-md uppercase">Out</span>}
      <button onClick={onOpen} className="w-full aspect-square bg-gradient-to-br from-[#fff7ed] to-gray-50 flex items-center justify-center p-4 cursor-pointer">
        <img src={p.image || ""} alt={p.name || "Product"} loading="lazy" className="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform" />
      </button>
      <div className="p-3 flex flex-col flex-1">
        <h3 onClick={onOpen} className="text-xs sm:text-sm font-semibold text-brand-ink line-clamp-2 mb-1.5 cursor-pointer hover:text-[#ea580c] min-h-[2.3rem]">{p.name || "Product"}</h3>
        <div className="mt-auto">
          <div className="flex items-baseline gap-1.5 flex-wrap">
            <span className="text-base font-extrabold text-brand-ink">{fmt(p.price)}</span>
            {save > 0 && <span className="text-[11px] text-brand-muted line-through">{fmt(p.mrp)}</span>}
          </div>
          {save > 0 && (
            <span className="inline-block mt-1 text-[10px] font-bold text-[#ea580c] bg-orange-50 border border-orange-200 rounded-full px-1.5 py-0.5">Save {fmt(save)}</span>
          )}
          {out ? (
            <button disabled className="w-full mt-2 bg-gray-200 text-gray-500 font-bold text-xs py-2 rounded-lg cursor-not-allowed">Out of Stock</button>
          ) : (
            <button onClick={onAdd} className="w-full mt-2 bg-[#ea580c] hover:bg-[#c2410c] text-white font-bold text-xs py-2 rounded-lg uppercase tracking-wider transition active:scale-[0.98]">Add to Cart</button>
          )}
        </div>
      </div>
    </article>
  );
}
