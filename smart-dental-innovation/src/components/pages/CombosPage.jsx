import { useMemo, useState } from "react";
import { sortOptions as SORT_OPTIONS } from "../../data/site";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useSettings } from "../../context/SettingsContext";
import { useCombos } from "../../hooks/useApiData";

const fmt = (n) => `₹${Number(n || 0).toLocaleString("en-IN")}`;

export default function CombosPage() {
  const { items, addToCart, updateQty, removeFromCart } = useCart();
  const { openModal, navigate } = useUI();
  const { company = {}, combosPage: cfg = {}, lowStockThreshold = 10 } = useSettings();
  const { data: combos } = useCombos();
  const [sort, setSort] = useState("all");

  const openProduct = (p) => navigate("product", { id: p.id });

  const list = useMemo(() => {
    const items = [...(combos || [])];
    switch (sort) {
      case "price-asc": items.sort((a, b) => (a.price || 0) - (b.price || 0)); break;
      case "price-desc": items.sort((a, b) => (b.price || 0) - (a.price || 0)); break;
      case "discount": items.sort((a, b) => (b.discount || 0) - (a.discount || 0)); break;
      default: break;
    }
    return items;
  }, [sort, combos]);

  const totalSaved = useMemo(
    () => (combos || []).reduce((s, c) => s + Math.max(0, (c.mrp || 0) - (c.price || 0)), 0),
    [combos]
  );

  return (
    <div className="bg-gradient-to-b from-[#f6f9fc] via-white to-white min-h-screen">
      {/* Hero */}
      <section className="relative overflow-hidden bg-gradient-to-br from-[#0b1d3a] via-[#13335f] to-[#3684bf] text-white">
        <div className="absolute inset-0 opacity-20 pointer-events-none">
          <div className="absolute -top-16 -left-16 w-64 h-64 rounded-full bg-[#5fb6ff] blur-3xl" />
          <div className="absolute -bottom-16 right-10 w-72 h-72 rounded-full bg-white blur-3xl opacity-40" />
        </div>
        <div className="relative max-w-[1400px] mx-auto px-4 sm:px-6 py-10 sm:py-12">
          <span className="inline-flex items-center gap-2 bg-white/15 backdrop-blur rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider mb-3">
            <span className="w-2 h-2 rounded-full bg-[#5fb6ff] animate-pulse" />
            {cfg.heroBadge || "Bundle & Save"}
          </span>
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">
            {cfg.heroTitle || "Combo Packs"}
            {totalSaved > 0 && (
              <span className="block text-[#9fd5ff] text-xl sm:text-2xl mt-1 font-bold">
                {cfg.savePrefix || "Save up to"} {fmt(totalSaved)} {cfg.saveSuffix || "across"} {list.length} bundle{list.length !== 1 ? "s" : ""}
              </span>
            )}
          </h1>
          <p className="text-sm sm:text-base text-white/85 mt-3 max-w-xl">
            {cfg.subtitle || "Hand-picked product bundles — clinic essentials grouped together at a better price than buying separately."}
          </p>
        </div>
      </section>

      <div className="max-w-[1400px] mx-auto px-3 sm:px-6 -mt-6 relative z-10">
        {/* Toolbar */}
        <div className="bg-white border border-gray-100 rounded-2xl shadow-sm px-4 sm:px-5 py-3 mb-6 flex items-center justify-between gap-3 flex-wrap">
          <span className="text-sm text-brand-muted">
            Showing <span className="font-bold text-brand-ink">{list.length}</span> combo{list.length !== 1 ? "s" : ""}
          </span>
          <div className="flex items-center gap-2">
            <span className="text-xs text-brand-muted hidden sm:block">Sort:</span>
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value)}
              className="bg-gray-50 border border-gray-200 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#3684bf]"
            >
              {SORT_OPTIONS.map((s) => (
                <option key={s.id} value={s.id}>{s.label}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Grid */}
        {list.length === 0 ? (
          <div className="bg-white border border-gray-100 rounded-2xl py-16 px-6 text-center mb-10">
            <div className="text-5xl mb-3">📦</div>
            <h3 className="text-xl font-bold text-brand-ink mb-1">No combos available</h3>
            <p className="text-sm text-brand-muted">Check back soon — new bundles drop regularly.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5 pb-10">
            {list.map((c) => (
              <ComboCard
                key={c.id}
                combo={c}
                bundleNote={cfg.bundleNote}
                lowStockThreshold={lowStockThreshold}
                cartItem={items.find((i) => i.id === c.id && !i.variant)}
                onOpen={() => openProduct(c)}
                onAdd={() => { addToCart(c, 1); openModal("cart"); }}
                onInc={(item) => updateQty(item.key, item.qty + 1)}
                onDec={(item) => (item.qty <= 1 ? removeFromCart(item.key) : updateQty(item.key, item.qty - 1))}
                onView={() => openModal("cart")}
              />
            ))}
          </div>
        )}

        {/* Trust strip */}
        <TrustStrip items={cfg.trust} phone={company.phone} />
      </div>
    </div>
  );
}

function ComboCard({ combo: c, bundleNote, lowStockThreshold = 10, cartItem, onOpen, onAdd, onInc, onDec, onView }) {
  const inCart = !!cartItem;
  const save = Math.max(0, (c.mrp || 0) - (c.price || 0));
  const disc = c.discount || (c.mrp > 0 ? Math.round((save / c.mrp) * 100) : 0);
  const out = c.inStock === false;
  // Low-stock urgency: stock known, in stock, and at/under the admin threshold.
  const lowStock = !out && typeof c.stock === "number" && c.stock > 0 && c.stock <= lowStockThreshold;

  // Real bundled products (admin-picked). Count + thumbnails come from these.
  const items = Array.isArray(c.items) ? c.items : [];
  const itemCount = items.length || null;
  const gallery = (items.length ? items.map((it) => it.image) : [c.image]).filter(Boolean);

  // Show up to 3 item thumbnails as "A + B + C"; rest folds into "+N more".
  const thumbItems = items.slice(0, 3);
  const moreCount = Math.max(0, items.length - 3);

  return (
    <article className="group relative border border-gray-200 rounded-2xl bg-white overflow-hidden flex flex-col hover:shadow-md hover:border-[#3684bf]/40 transition-all duration-200">
      {/* Low Stock urgency ribbon (centered, top) — like the reference site */}
      {lowStock && (
        <div className="absolute top-0 left-0 right-0 z-20 flex justify-center">
          <span className="bg-gradient-to-r from-[#f97316] to-[#ea580c] text-white text-[11px] font-extrabold px-4 py-1 rounded-b-xl shadow-md inline-flex items-center gap-1.5 animate-pulse">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z" /></svg>
            Low Stock! Hurry
          </span>
        </div>
      )}
      {/* % OFF badge */}
      {disc > 0 && (
        <span className="absolute top-3 left-3 z-10 bg-[#16a34a] text-white text-[11px] font-extrabold px-2 py-0.5 rounded-md shadow-sm">{disc}% OFF</span>
      )}
      {out && (
        <span className="absolute top-3 right-3 z-10 bg-gray-700 text-white text-[10px] font-bold px-2 py-0.5 rounded-md uppercase">Out of stock</span>
      )}

      {/* "A + B + C" product strip — clean bundle visual like the reference site */}
      <button onClick={onOpen} className="w-full bg-gradient-to-br from-[#f6f9fc] to-gray-50 px-4 pt-7 pb-4 cursor-pointer">
        {itemCount > 1 ? (
          <div className="flex items-center justify-center gap-1.5">
            {thumbItems.map((it, i) => (
              <div key={i} className="flex items-center gap-1.5">
                {i > 0 && <span className="text-gray-400 text-lg font-bold">+</span>}
                <span className="w-[68px] h-[68px] sm:w-[74px] sm:h-[74px] rounded-xl bg-white border border-gray-100 shadow-sm flex items-center justify-center p-1.5 overflow-hidden">
                  <img src={it.image || ""} alt={it.name || ""} loading="lazy" className="max-w-full max-h-full object-contain" />
                </span>
              </div>
            ))}
            {moreCount > 0 && (
              <span className="text-gray-400 text-lg font-bold">+{moreCount}</span>
            )}
          </div>
        ) : (
          <div className="aspect-square max-h-[180px] flex items-center justify-center">
            <img src={gallery[0] || c.image || ""} alt={c.name || "Combo"} loading="lazy" className="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-200" />
          </div>
        )}
      </button>

      {/* Body */}
      <div className="p-4 flex flex-col flex-1">
        <h3 onClick={onOpen} role="button" tabIndex={0} className="text-sm font-bold text-brand-ink line-clamp-2 mb-1 cursor-pointer hover:text-[#3684bf] min-h-[2.5rem]">
          {c.name || "Combo Pack"}
        </h3>
        <p className="text-[11px] font-semibold text-[#3684bf] inline-flex items-center gap-1 mb-2">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H4c-1.1 0-1.99.9-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4z" /></svg>
          {itemCount > 1 ? `${itemCount} products bundled` : (bundleNote || "Multi-product bundle")}
        </p>
        {c.description && (
          <p className="text-xs text-brand-muted line-clamp-2 mb-3">{c.description}</p>
        )}

        {/* Price block */}
        <div className="mt-auto">
          <div className="flex items-baseline gap-2 flex-wrap">
            <span className="text-lg font-extrabold text-brand-ink">{fmt(c.price)}</span>
            {save > 0 && <span className="text-xs text-brand-muted line-through">{fmt(c.mrp)}</span>}
          </div>
          {save > 0 && (
            <span className="inline-block mt-1 text-[11px] font-bold text-green-700 bg-green-50 border border-green-200 rounded-full px-2 py-0.5">
              You save {fmt(save)}
            </span>
          )}

          {/* CTA */}
          {out ? (
            <button disabled className="w-full mt-3 bg-gray-200 text-gray-500 font-bold text-sm py-2.5 rounded-lg cursor-not-allowed">
              Out of Stock
            </button>
          ) : inCart ? (
            <div className="mt-3 flex items-stretch gap-2">
              <div className="flex-1 flex items-center justify-between bg-white border border-gray-200 rounded-lg px-1">
                <button onClick={() => onDec(cartItem)} aria-label="Decrease" className="w-8 h-9 rounded hover:bg-gray-100 flex items-center justify-center text-brand-ink text-lg font-bold">−</button>
                <span className="text-sm font-bold text-brand-ink select-none">{cartItem.qty}</span>
                <button onClick={() => onInc(cartItem)} aria-label="Increase" className="w-8 h-9 rounded hover:bg-gray-100 flex items-center justify-center text-brand-ink text-lg font-bold">+</button>
              </div>
              <button onClick={onView} className="flex-1 bg-[#0b1d3a] hover:bg-[#13294f] text-white font-bold text-xs rounded-lg flex items-center justify-center gap-1.5 transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45A2 2 0 007 17h12v-2H7.42a.25.25 0 01-.25-.25l.03-.12.9-1.63h7.45a2 2 0 001.75-1.03l3.58-6.49A1 1 0 0021.79 4H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" /></svg>
                View Cart
              </button>
            </div>
          ) : (
            <button
              onClick={onAdd}
              className="w-full mt-3 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm py-2.5 rounded-lg uppercase tracking-wider transition active:scale-[0.98] flex items-center justify-center gap-2"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45A2 2 0 007 17h12v-2H7.42a.25.25 0 01-.25-.25l.03-.12.9-1.63h7.45a2 2 0 001.75-1.03l3.58-6.49A1 1 0 0021.79 4H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z" /></svg>
              Add to Cart
            </button>
          )}
        </div>
      </div>
    </article>
  );
}

const TRUST_ICONS = {
  shield: "M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-4-4 1.41-1.41L11 12.17l5.59-5.59L18 8l-7 7z",
  save: "M19 7c0-1.1-.9-2-2-2h-3V3H10v2H7c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V7z",
  ship: "M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z",
  help: "M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z",
};

function TrustStrip({ items = [], phone }) {
  if (!items.length) return null;
  return (
    <div className="mb-12 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      {items.map((it, i) => (
        <div key={i} className="bg-white border border-gray-100 rounded-xl p-4 flex items-start gap-3 hover:border-[#3684bf] hover:shadow-md transition">
          <div className="w-10 h-10 rounded-lg bg-[#eef5fb] flex items-center justify-center shrink-0">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#3684bf"><path d={TRUST_ICONS[it.icon] || TRUST_ICONS.shield} /></svg>
          </div>
          <div className="min-w-0">
            <p className="text-sm font-bold text-brand-ink leading-tight">{it.title}</p>
            <p className="text-xs text-brand-muted mt-0.5">
              {it.icon === "help" && phone ? `Call ${phone}` : it.desc}
            </p>
          </div>
        </div>
      ))}
    </div>
  );
}
