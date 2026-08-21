import { useMemo, useState, useEffect } from "react";
import { useSearchParams } from "react-router-dom";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useSettings } from "../../context/SettingsContext";
import { useProducts, useCombos } from "../../hooks/useApiData";

const fmt = (n) => `₹${Number(n || 0).toLocaleString("en-IN")}`;

// Build budget buckets from admin price presets (each = "Under ₹max", with a min from the prior preset).
function buildBuckets(presets) {
  const sorted = [...(presets || [])].filter((p) => p.max).sort((a, b) => a.max - b.max);
  let prevMax = 0;
  const buckets = sorted.map((p) => {
    const b = { label: p.label || `Under ${fmt(p.max)}`, min: 0, max: p.max };
    prevMax = p.max;
    return b;
  });
  // top open-ended bucket: "Above ₹{last}"
  if (sorted.length) buckets.push({ label: `Above ${fmt(prevMax)}`, min: prevMax, max: Infinity, open: true });
  return buckets;
}

export default function ShopByPricePage() {
  const { addToCart } = useCart();
  const { openModal } = useUI();
  const navigate = useAppNavigate();
  const [searchParams] = useSearchParams();
  const incomingMax = searchParams.has("priceMax") ? Number(searchParams.get("priceMax")) : undefined;
  const { pricePresets = [], priceBounds = { min: 0, max: 500000 }, shopByPricePage: cfg = {} } = useSettings();
  const { data: allProducts } = useProducts();
  const { data: combos } = useCombos();

  const buckets = useMemo(() => buildBuckets(pricePresets), [pricePresets]);
  const items = useMemo(
    () => [...(combos || []), ...(allProducts || [])].filter((p) => p && p.id),
    [allProducts, combos]
  );

  // selection: a bucket index, or custom min/max
  const [bucketIdx, setBucketIdx] = useState(null);
  const [range, setRange] = useState({ min: priceBounds.min || 0, max: priceBounds.max || 500000 });
  const [custom, setCustom] = useState(false);
  const [sort, setSort] = useState("low");

  // Pick the bucket matching an incoming ?priceMax (from navbar preset), else default to first.
  useEffect(() => {
    if (!buckets.length) return;
    if (incomingMax != null) {
      const i = buckets.findIndex((b) => b.max === incomingMax);
      setCustom(false);
      setBucketIdx(i >= 0 ? i : 0);
    } else if (bucketIdx === null && !custom) {
      setBucketIdx(0);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [buckets, incomingMax]);

  const activeRange = useMemo(() => {
    if (custom) return { min: range.min, max: range.max };
    const b = buckets[bucketIdx];
    return b ? { min: b.min, max: b.max } : { min: 0, max: Infinity };
  }, [custom, range, buckets, bucketIdx]);

  const filtered = useMemo(() => {
    const list = items.filter((p) => {
      const price = p.price ?? 0;
      return price >= activeRange.min && price <= activeRange.max;
    });
    list.sort((a, b) => (sort === "low" ? (a.price || 0) - (b.price || 0) : (b.price || 0) - (a.price || 0)));
    return list;
  }, [items, activeRange, sort]);

  const pickBucket = (i) => { setCustom(false); setBucketIdx(i); };
  const onCustom = () => { setCustom(true); setBucketIdx(null); };

  return (
    <div className="bg-gradient-to-b from-[#f6f9fc] via-white to-white min-h-screen">
      {/* Hero */}
      <section className="bg-gradient-to-br from-[#0b1d3a] via-[#13335f] to-[#3684bf] text-white">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 py-9 sm:py-11">
          <span className="inline-flex items-center gap-2 bg-white/15 backdrop-blur rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider mb-3">
            <span className="font-extrabold">₹</span> {cfg.heroBadge || "Shop by Budget"}
          </span>
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">{cfg.heroTitle || "Shop by Price"}</h1>
          <p className="text-sm sm:text-base text-white/85 mt-2 max-w-xl">
            {cfg.subtitle || "Pick a budget — we'll show every product that fits, from quick buys to clinic essentials."}
          </p>
        </div>
      </section>

      <div className="max-w-[1400px] mx-auto px-3 sm:px-6 -mt-6 relative z-10">
        {/* Budget cards */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-5">
          {buckets.map((b, i) => {
            const active = !custom && bucketIdx === i;
            const count = items.filter((p) => (p.price ?? 0) >= b.min && (p.price ?? 0) <= b.max).length;
            return (
              <button
                key={i}
                onClick={() => pickBucket(i)}
                className={`relative rounded-2xl border-2 p-4 text-left transition-all ${
                  active ? "border-[#3684bf] bg-[#eef5fb] shadow-md" : "border-gray-200 bg-white hover:border-[#3684bf]/50 hover:shadow-sm"
                }`}
              >
                <div className={`w-9 h-9 rounded-lg flex items-center justify-center mb-2 ${active ? "bg-[#3684bf] text-white" : "bg-[#eef5fb] text-[#3684bf]"}`}>
                  <span className="font-extrabold">₹</span>
                </div>
                <p className="text-sm font-bold text-brand-ink leading-tight">{b.label}</p>
                <p className="text-[11px] text-brand-muted mt-0.5">{count} product{count !== 1 ? "s" : ""}</p>
                {active && <span className="absolute top-3 right-3 w-2.5 h-2.5 rounded-full bg-[#3684bf]" />}
              </button>
            );
          })}
          {/* Custom range card */}
          <button
            onClick={onCustom}
            className={`rounded-2xl border-2 p-4 text-left transition-all ${
              custom ? "border-[#3684bf] bg-[#eef5fb] shadow-md" : "border-dashed border-gray-300 bg-white hover:border-[#3684bf]/50"
            }`}
          >
            <div className={`w-9 h-9 rounded-lg flex items-center justify-center mb-2 ${custom ? "bg-[#3684bf] text-white" : "bg-gray-100 text-gray-500"}`}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z" /></svg>
            </div>
            <p className="text-sm font-bold text-brand-ink leading-tight">{cfg.customLabel || "Custom Range"}</p>
            <p className="text-[11px] text-brand-muted mt-0.5">{cfg.customDesc || "Set your own budget"}</p>
          </button>
        </div>

        {/* Custom range slider */}
        {custom && (
          <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 sm:p-5 mb-5">
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm font-bold text-brand-ink">Price range</span>
              <span className="text-sm font-bold text-[#3684bf]">{fmt(range.min)} — {fmt(range.max)}</span>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <label className="text-xs text-brand-muted">
                Min
                <input
                  type="number" min={priceBounds.min} max={range.max}
                  value={range.min}
                  onChange={(e) => setRange((r) => ({ ...r, min: Math.min(Number(e.target.value) || 0, r.max) }))}
                  className="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#3684bf]"
                />
              </label>
              <label className="text-xs text-brand-muted">
                Max
                <input
                  type="number" min={range.min} max={priceBounds.max}
                  value={range.max === Infinity ? "" : range.max}
                  onChange={(e) => setRange((r) => ({ ...r, max: Math.max(Number(e.target.value) || 0, r.min) }))}
                  className="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#3684bf]"
                />
              </label>
            </div>
            <input
              type="range" min={priceBounds.min} max={priceBounds.max} step="10"
              value={Math.min(range.max, priceBounds.max)}
              onChange={(e) => setRange((r) => ({ ...r, max: Number(e.target.value) }))}
              className="w-full mt-4 accent-[#3684bf]"
            />
          </div>
        )}

        {/* Toolbar */}
        <div className="flex items-center justify-between gap-3 mb-4 flex-wrap">
          <span className="text-sm text-brand-muted">
            <span className="font-bold text-brand-ink">{filtered.length}</span> product{filtered.length !== 1 ? "s" : ""} in this budget
          </span>
          <div className="flex items-center gap-2">
            <span className="text-xs text-brand-muted hidden sm:block">Sort:</span>
            <select value={sort} onChange={(e) => setSort(e.target.value)} className="bg-white border border-gray-200 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#3684bf]">
              <option value="low">Price: Low to High</option>
              <option value="high">Price: High to Low</option>
            </select>
          </div>
        </div>

        {/* Grid */}
        {filtered.length === 0 ? (
          <div className="bg-white border border-gray-100 rounded-2xl py-16 px-6 text-center mb-10">
            <div className="text-5xl mb-3">🔍</div>
            <h3 className="text-xl font-bold text-brand-ink mb-1">No products in this budget</h3>
            <p className="text-sm text-brand-muted">Try a higher budget or a custom range.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 pb-12">
            {filtered.map((p) => (
              <PriceCard
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

function PriceCard({ product: p, onOpen, onAdd }) {
  const save = Math.max(0, (p.mrp || 0) - (p.price || 0));
  const disc = p.discount || (p.mrp > 0 ? Math.round((save / p.mrp) * 100) : 0);
  const out = p.inStock === false;
  return (
    <article className="group relative border border-gray-200 rounded-2xl bg-white overflow-hidden flex flex-col hover:shadow-md hover:border-[#3684bf]/40 transition-all">
      {disc > 0 && (
        <span className="absolute top-2.5 left-2.5 z-10 bg-[#16a34a] text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md shadow-sm">{disc}% OFF</span>
      )}
      {out && <span className="absolute top-2.5 right-2.5 z-10 bg-gray-700 text-white text-[9px] font-bold px-2 py-0.5 rounded-md uppercase">Out</span>}
      <button onClick={onOpen} className="w-full aspect-square bg-gradient-to-br from-[#f6f9fc] to-gray-50 flex items-center justify-center p-4 cursor-pointer">
        <img src={p.image || ""} alt={p.name || "Product"} loading="lazy" className="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform" />
      </button>
      <div className="p-3 flex flex-col flex-1">
        <h3 onClick={onOpen} className="text-xs sm:text-sm font-semibold text-brand-ink line-clamp-2 mb-1.5 cursor-pointer hover:text-[#3684bf] min-h-[2.3rem]">{p.name || "Product"}</h3>
        <div className="mt-auto">
          <div className="flex items-baseline gap-1.5 flex-wrap">
            <span className="text-base font-extrabold text-brand-ink">{fmt(p.price)}</span>
            {save > 0 && <span className="text-[11px] text-brand-muted line-through">{fmt(p.mrp)}</span>}
          </div>
          {out ? (
            <button disabled className="w-full mt-2 bg-gray-200 text-gray-500 font-bold text-xs py-2 rounded-lg cursor-not-allowed">Out of Stock</button>
          ) : (
            <button onClick={onAdd} className="w-full mt-2 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-xs py-2 rounded-lg uppercase tracking-wider transition active:scale-[0.98]">Add to Cart</button>
          )}
        </div>
      </div>
    </article>
  );
}
