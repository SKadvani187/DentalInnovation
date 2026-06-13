import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useParams, useSearchParams } from "react-router-dom";
import { categoryFilters as STATIC_FILTERS } from "../../data/categories";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useSettings } from "../../context/SettingsContext";
import { useProducts, useCombos, useCategories } from "../../hooks/useApiData";
import Seo from "../Seo";

// Bounds fallback only for the first render before settings resolve; admin value (DB) wins.
const FALLBACK_BOUNDS = { min: 10, max: 500000 };
const COLLAPSE_COUNT = 10;

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function CategoryPage() {
  const navigate = useAppNavigate();
  const { category: categoryParam } = useParams();
  const [searchParams] = useSearchParams();
  const priceMaxParam = searchParams.has("priceMax") ? Number(searchParams.get("priceMax")) : null;
  // Price bounds are admin-managed (DB via settings API). Sort options read in <SortSelect>.
  const { priceBounds = FALLBACK_BOUNDS } = useSettings();
  const PRICE_MIN = priceBounds.min ?? FALLBACK_BOUNDS.min;
  const PRICE_MAX = priceBounds.max ?? FALLBACK_BOUNDS.max;
  const initialCategory = categoryParam || null;
  const initialPriceMax = priceMaxParam || PRICE_MAX;
  const [selectedCat, setSelectedCat] = useState(initialCategory);
  const [sort, setSort] = useState("all");
  const [expanded, setExpanded] = useState(false);
  const [priceMin, setPriceMin] = useState(PRICE_MIN);
  const [priceMax, setPriceMax] = useState(initialPriceMax);
  const [mobileFilters, setMobileFilters] = useState(false);

  const { data: allProducts } = useProducts();
  const { data: combos } = useCombos();
  const { data: catData } = useCategories();
  // API categories -> {id,label}; fall back to static filters.
  const CATEGORY_FILTERS = useMemo(
    () => (catData?.length ? catData.map((c) => ({ id: c.id, label: c.title })) : STATIC_FILTERS),
    [catData]
  );

  // Keep local filter state in sync with the URL (path category + ?priceMax) so deep
  // links and back/forward navigation reflect the right filters.
  useEffect(() => {
    if (priceMaxParam) {
      setPriceMax(priceMaxParam);
      setPriceMin(PRICE_MIN);
    }
    setSelectedCat(categoryParam || null);
  }, [priceMaxParam, categoryParam]);

  const priceFiltered = priceMin > PRICE_MIN || priceMax < PRICE_MAX;
  const products = useMemo(() => {
    let list = [...(combos || []), ...(allProducts || [])].filter((p) => p && p.id);
    if (selectedCat) list = list.filter((p) => p.category === selectedCat);
    list = list.filter((p) => (p.price ?? 0) >= priceMin && (p.price ?? 0) <= priceMax);
    switch (sort) {
      case "price-asc": list.sort((a, b) => (a.price || 0) - (b.price || 0)); break;
      case "price-desc": list.sort((a, b) => (b.price || 0) - (a.price || 0)); break;
      case "discount": list.sort((a, b) => (b.discount || 0) - (a.discount || 0)); break;
      case "rating": list.sort((a, b) => (b.rating || 0) - (a.rating || 0)); break;
      default: break;
    }
    return list;
  }, [selectedCat, sort, priceMin, priceMax, allProducts, combos]);

  const visibleCats = expanded ? CATEGORY_FILTERS : CATEGORY_FILTERS.slice(0, COLLAPSE_COUNT);
  const catLabel = selectedCat ? (CATEGORY_FILTERS.find((c) => c.id === selectedCat)?.label || searchParams.get("title") || "Products") : "All Products";
  const resetAll = () => { setSelectedCat(null); setPriceMin(PRICE_MIN); setPriceMax(PRICE_MAX); setSort("all"); };

  const SidebarFilters = (
    <>
      <div className="mb-2 flex items-center justify-between">
        <h3 className="text-base font-bold text-brand-ink">Categories</h3>
        {(selectedCat || priceFiltered) && (
          <button onClick={resetAll} className="text-xs font-semibold text-[#3684bf] hover:underline">Clear all</button>
        )}
      </div>
      <ul className="space-y-1">
        <li><CatRadio label="All Categories" checked={selectedCat === null} onChange={() => setSelectedCat(null)} /></li>
        {visibleCats.map((c) => (
          <li key={c.id}><CatRadio label={c.label} checked={selectedCat === c.id} onChange={() => setSelectedCat(c.id)} /></li>
        ))}
      </ul>
      {CATEGORY_FILTERS.length > COLLAPSE_COUNT && (
        <button
          onClick={() => setExpanded((v) => !v)}
          className="mt-3 w-full py-2 border-t border-b border-gray-200 text-[#3684bf] font-semibold text-sm flex items-center justify-center gap-1 hover:bg-gray-50"
        >
          {expanded ? "View Less" : "View All"}
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d={expanded ? "M19 12H5M12 19l-7-7 7-7" : "M5 12h14M13 5l7 7-7 7"} />
          </svg>
        </button>
      )}
      <div className="mt-6">
        <h3 className="text-base font-bold text-brand-ink mb-3">Price Range</h3>
        <PriceRange min={PRICE_MIN} max={PRICE_MAX} valueMin={priceMin} valueMax={priceMax} onChange={(lo, hi) => { setPriceMin(lo); setPriceMax(hi); }} />
        <div className="flex items-center justify-between text-xs text-brand-muted mt-2">
          <span>₹{priceMin.toLocaleString("en-IN")}</span>
          <span>₹{priceMax.toLocaleString("en-IN")}</span>
        </div>
      </div>
    </>
  );

  const seoCatLabel = (CATEGORY_FILTERS.find((c) => c.id === selectedCat) || {}).label;
  return (
    <div className="bg-[#f6f9fc] min-h-screen">
      <Seo
        title={seoCatLabel ? `${seoCatLabel} — Dental Products` : "Shop Dental Products"}
        description={seoCatLabel
          ? `Browse ${seoCatLabel} at DentInno — quality dental products at great prices.`
          : "Browse dental products, equipment and consumables at DentInno."}
      />
      {/* Header */}
      <div className="bg-white border-b border-gray-100">
        <div className="max-w-[1400px] mx-auto px-4 py-5">
          <nav className="flex items-center gap-2 text-xs text-brand-muted mb-2">
            <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
            <span>/</span>
            <button onClick={() => navigate("category")} className="hover:text-[#3684bf]">Category</button>
            {selectedCat && (<><span>/</span><span className="text-brand-ink font-semibold">{catLabel}</span></>)}
          </nav>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-brand-ink">{catLabel}</h1>
          <p className="text-sm text-brand-muted mt-0.5">
            <span className="font-bold text-brand-ink">{products.length}</span> product{products.length !== 1 ? "s" : ""} found
          </p>
        </div>
      </div>

      <div className="max-w-[1400px] mx-auto px-4 py-5">
        {/* Mobile filter button + sort */}
        <div className="flex items-center justify-between gap-3 mb-4 lg:hidden">
          <button onClick={() => setMobileFilters(true)} className="inline-flex items-center gap-2 border border-gray-300 rounded-full px-4 py-2 text-sm font-semibold text-brand-ink">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z" /></svg>
            Filters{(selectedCat || priceFiltered) ? " •" : ""}
          </button>
          <SortSelect sort={sort} setSort={setSort} />
        </div>

        <div className="flex flex-col lg:flex-row gap-6">
          {/* Desktop sidebar */}
          <aside className="hidden lg:block w-[260px] shrink-0">
            <div className="bg-white border border-gray-100 rounded-2xl p-5 sticky top-4">
              <h2 className="text-lg font-bold text-brand-ink mb-4">Filters</h2>
              {SidebarFilters}
            </div>
          </aside>

          <section className="flex-1 min-w-0">
            {/* Desktop sort + active chips */}
            <div className="hidden lg:flex items-center justify-between gap-3 mb-4">
              <div className="flex items-center gap-2 flex-wrap">
                {selectedCat && (
                  <FilterChip label={catLabel} onClear={() => setSelectedCat(null)} />
                )}
                {priceFiltered && (
                  <FilterChip label={`₹${priceMin.toLocaleString("en-IN")} – ₹${priceMax.toLocaleString("en-IN")}`} onClear={() => { setPriceMin(PRICE_MIN); setPriceMax(PRICE_MAX); }} />
                )}
              </div>
              <SortSelect sort={sort} setSort={setSort} />
            </div>

            {products.length === 0 ? (
              <div className="bg-white border border-gray-100 rounded-2xl py-16 px-6 text-center">
                <div className="text-5xl mb-3">🔍</div>
                <h3 className="text-xl font-bold text-brand-ink mb-1">No products found</h3>
                <p className="text-sm text-brand-muted mb-4">Try a different category or widen the price range.</p>
                <button onClick={resetAll} className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold text-sm px-5 py-2 rounded-md transition">Clear filters</button>
              </div>
            ) : (
              <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-5">
                {products.map((p) => (
                  <ProductCard key={p.id} product={p} />
                ))}
              </div>
            )}
          </section>
        </div>
      </div>

      {/* Mobile filter drawer */}
      {mobileFilters && (
        <div className="lg:hidden fixed inset-0 z-[1100]">
          <div className="absolute inset-0 bg-black/50" onClick={() => setMobileFilters(false)} />
          <div className="absolute left-0 top-0 bottom-0 w-[300px] max-w-[85vw] bg-white shadow-2xl flex flex-col">
            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
              <h2 className="text-lg font-bold text-brand-ink">Filters</h2>
              <button onClick={() => setMobileFilters(false)} aria-label="Close" className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6l12 12M18 6L6 18" /></svg>
              </button>
            </div>
            <div className="flex-1 overflow-y-auto p-5">{SidebarFilters}</div>
            <div className="p-4 border-t border-gray-100">
              <button onClick={() => setMobileFilters(false)} className="w-full bg-[#3684bf] text-white font-bold py-2.5 rounded-lg">Show {products.length} products</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function SortSelect({ sort, setSort }) {
  const { sortOptions: SORT_OPTIONS = [] } = useSettings();
  return (
    <div className="flex items-center gap-2 shrink-0">
      <span className="text-xs text-brand-muted hidden sm:block">Sort:</span>
      <select value={sort} onChange={(e) => setSort(e.target.value)} className="bg-white border border-gray-200 rounded-md px-2.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-ink focus:outline-none focus:border-[#3684bf]">
        {SORT_OPTIONS.map((s) => (<option key={s.id} value={s.id}>{s.label}</option>))}
      </select>
    </div>
  );
}

function FilterChip({ label, onClear }) {
  return (
    <span className="inline-flex items-center gap-1.5 bg-[#eef5fb] text-[#3684bf] text-xs font-semibold rounded-full pl-3 pr-1.5 py-1">
      {label}
      <button onClick={onClear} aria-label="Remove filter" className="w-4 h-4 rounded-full bg-white/70 hover:bg-white flex items-center justify-center">
        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </span>
  );
}

function CatRadio({ label, checked, onChange }) {
  return (
    <label className={`flex items-center gap-3 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-50 ${checked ? "bg-gray-100" : ""}`}>
      <span className={`w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 ${checked ? "border-[#3684bf]" : "border-gray-400"}`}>
        {checked && <span className="w-2 h-2 rounded-full bg-[#3684bf]" />}
      </span>
      <span className="text-sm text-brand-ink">{label}</span>
      <input type="radio" checked={checked} onChange={onChange} className="hidden" />
    </label>
  );
}

function PriceRange({ min, max, valueMin, valueMax, onChange }) {
  const pctMin = ((valueMin - min) / (max - min)) * 100;
  const pctMax = ((valueMax - min) / (max - min)) * 100;

  const onMinChange = (e) => {
    const v = Math.min(Number(e.target.value), valueMax - 1);
    onChange(v, valueMax);
  };
  const onMaxChange = (e) => {
    const v = Math.max(Number(e.target.value), valueMin + 1);
    onChange(valueMin, v);
  };

  return (
    <div className="relative h-6 flex items-center">
      <div className="absolute left-0 right-0 h-1 bg-gray-200 rounded-full" />
      <div
        className="absolute h-1 bg-[#3684bf] rounded-full"
        style={{ left: `${pctMin}%`, right: `${100 - pctMax}%` }}
      />
      <input
        type="range"
        min={min}
        max={max}
        value={valueMin}
        onChange={onMinChange}
        className="price-range-thumb absolute w-full appearance-none bg-transparent pointer-events-none"
        style={{ zIndex: valueMin > max - (max - min) / 4 ? 5 : 3 }}
      />
      <input
        type="range"
        min={min}
        max={max}
        value={valueMax}
        onChange={onMaxChange}
        className="price-range-thumb absolute w-full appearance-none bg-transparent pointer-events-none"
        style={{ zIndex: 4 }}
      />
    </div>
  );
}

function ProductCard({ product }) {
  const { addToCart } = useCart();
  const { openModal } = useUI();
  const navigate = useAppNavigate();
  const { category: categoryParam } = useParams();
  const openProduct = () => navigate("product", { id: product.id, name: product.name, fromCategory: categoryParam });
  const variants = Array.isArray(product.variants)
    ? product.variants.filter((v) => typeof v === "object")
    : [];
  const hasVariants = variants.length >= 1;
  const oos = product.inStock === false;
  const [variantsOpen, setVariantsOpen] = useState(false);
  const cardRef = useRef(null);

  const variantPrices = variants.map((v) => v.price);
  const minVariantPrice = variantPrices.length ? Math.min(...variantPrices) : product.price;
  const maxVariantPrice = variantPrices.length ? Math.max(...variantPrices) : product.price;

  const onAdd = (e) => {
    e.preventDefault();
    e.stopPropagation();
    addToCart(product, 1);
    openModal("cart");
  };

  const onViewVariants = (e) => {
    e.preventDefault();
    e.stopPropagation();
    setVariantsOpen(true);
  };

  return (
    <article ref={cardRef} className="border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col hover:shadow-md transition">
      <div className="relative">
        <button
          onClick={() => openProduct()}
          className="w-full aspect-square bg-gray-50 flex items-center justify-center p-4 cursor-pointer"
        >
          <img src={product.image} alt={product.name} className={`max-w-full max-h-full object-contain ${oos ? "opacity-70" : ""}`} />
        </button>
        {oos && (
          <div className="absolute top-3 left-0 right-0 flex justify-center pointer-events-none">
            <span className="bg-[#e57373] text-white font-bold text-xs tracking-wider px-6 py-2 uppercase shadow-sm">
              Out of Stock
            </span>
          </div>
        )}
      </div>

      <div className="p-4 flex flex-col flex-1">
        <h3
          className="text-sm font-bold text-brand-ink line-clamp-2 mb-2 cursor-pointer hover:text-[#3684bf]"
          onClick={() => openProduct()}
        >
          {product.name}
        </h3>

        {hasVariants && !oos && (
          <p className="text-xs text-[#3684bf] font-semibold mb-1">
            {variants.length} variants available <span className="text-brand-muted font-normal">from</span>
          </p>
        )}

        <div className="flex items-center gap-2 flex-wrap mb-3">
          <span className="text-xs text-brand-muted line-through">₹{product.mrp.toLocaleString("en-IN")}</span>
          <span className="text-base font-bold text-brand-ink">{fmt(product.price)}</span>
          {product.discount > 0 && (
            <span className="text-xs font-bold text-green-600">| {product.discount}% OFF</span>
          )}
        </div>

        <div className="mt-auto">
          {oos ? (
            <button
              disabled
              className="w-full bg-gray-300 text-white font-bold text-sm py-2.5 rounded-md uppercase tracking-wider cursor-not-allowed"
            >
              Out of Stock
            </button>
          ) : hasVariants ? (
            <button
              onClick={onViewVariants}
              className="w-full border-2 border-[#3684bf] text-[#3684bf] hover:bg-[#3684bf] hover:text-white font-bold text-sm py-2.5 rounded-md uppercase tracking-wider transition"
            >
              View Variants
            </button>
          ) : (
            <button
              onClick={onAdd}
              className="w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm py-2.5 rounded-md uppercase tracking-wider transition"
            >
              Add to Cart
            </button>
          )}
        </div>
      </div>

      {variantsOpen && (
        <VariantsModal
          product={product}
          variants={variants}
          priceRange={[minVariantPrice, maxVariantPrice]}
          anchorRef={cardRef}
          onClose={() => setVariantsOpen(false)}
        />
      )}
    </article>
  );
}

function VariantsModal({ product, variants, priceRange, anchorRef, onClose }) {
  const { addToCart, items, updateQty, removeFromCart } = useCart();
  const { openModal, modal } = useUI();
  const cartOpen = modal === "cart";
  const [pos, setPos] = useState(null);

  useEffect(() => {
    const compute = () => {
      const modalW = 480;
      const margin = 16;
      const left = Math.max(margin, (window.innerWidth - modalW) / 2);
      const top = Math.max(margin, window.innerHeight * 0.12);
      setPos({ left, top, width: modalW });
    };
    compute();
    window.addEventListener("resize", compute);
    return () => window.removeEventListener("resize", compute);
  }, []);

  const getCartItem = (label) =>
    items.find((i) => i.id === product.id && i.variant === label);

  const onAdd = (v) => {
    addToCart(
      { ...product, price: v.price, mrp: v.mrp },
      1,
      v.label
    );
    openModal("cart");
  };
  const inc = (v) => {
    const ci = getCartItem(v.label);
    if (ci) updateQty(ci.key, ci.qty + 1);
    else onAdd(v);
  };
  const dec = (v) => {
    const ci = getCartItem(v.label);
    if (!ci) return;
    if (ci.qty <= 1) removeFromCart(ci.key);
    else updateQty(ci.key, ci.qty - 1);
  };

  const [lo, hi] = priceRange;
  const rangeLabel = lo === hi ? fmt(lo) : `${fmt(lo)} - ${fmt(hi)}`;

  return createPortal(
    <div>
      {!cartOpen && (
        <div
          className="fixed inset-0 z-[1095] bg-black/50"
          onClick={onClose}
        />
      )}
      <div
        className="fixed z-[1099] bg-white rounded-2xl shadow-2xl overflow-hidden"
        style={pos ? { left: pos.left, top: pos.top, width: pos.width } : { visibility: "hidden" }}
        role="dialog"
        aria-modal="true"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start justify-between px-5 pt-5 pb-3">
          <div>
            <h3 className="font-bold text-brand-ink text-lg">{product.name}</h3>
            <p className="text-xs text-brand-muted mt-1">
              {variants.length} variants <span className="mx-1">|</span> {rangeLabel}
            </p>
          </div>
          <button
            onClick={onClose}
            aria-label="Close"
            className="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>

        <ul className="divide-y divide-gray-100 max-h-[60vh] overflow-y-auto">
          {variants.map((v) => {
            const ci = getCartItem(v.label);
            const qty = ci?.qty || 0;
            return (
              <li key={v.label} className="flex items-center justify-between px-5 py-3 gap-3">
                <div className="min-w-0">
                  <p className="font-semibold text-brand-ink text-sm">{v.label}</p>
                  <div className="flex items-center gap-2 flex-wrap mt-0.5">
                    <span className="text-sm font-bold text-brand-ink">{fmt(v.price)}</span>
                    <span className="text-xs text-brand-muted line-through">₹{v.mrp.toLocaleString("en-IN")}</span>
                    {v.discount > 0 && (
                      <span className="text-xs font-bold text-green-600">{v.discount}% OFF</span>
                    )}
                  </div>
                </div>
                {qty > 0 ? (
                  <div className="inline-flex items-center border-2 border-[#3684bf] rounded overflow-hidden">
                    <button onClick={() => dec(v)} className="w-8 h-8 text-[#3684bf] font-bold hover:bg-blue-50">−</button>
                    <span className="w-8 text-center font-bold text-brand-ink">{qty}</span>
                    <button onClick={() => inc(v)} className="w-8 h-8 text-[#3684bf] font-bold hover:bg-blue-50">+</button>
                  </div>
                ) : (
                  <button
                    onClick={() => onAdd(v)}
                    className="inline-flex items-center gap-1 border-2 border-[#3684bf] text-[#3684bf] hover:bg-[#3684bf] hover:text-white font-bold text-xs px-4 py-1.5 rounded uppercase tracking-wider transition"
                  >
                    + Add
                  </button>
                )}
              </li>
            );
          })}
        </ul>

        <CartFooter onClose={onClose} />
      </div>
    </div>,
    document.body
  );
}

function CartFooter({ onClose }) {
  const { itemCount } = useCart();
  const { openModal } = useUI();
  if (itemCount <= 0) return null;
  return (
    <div className="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50">
      <span className="text-sm text-brand-muted flex items-center gap-2">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M6 6h15l-1.5 9h-13z" />
          <circle cx="9" cy="20" r="1.5" />
          <circle cx="18" cy="20" r="1.5" />
        </svg>
        {itemCount} item{itemCount > 1 ? "s" : ""} added
      </span>
      <button
        onClick={() => { onClose(); openModal("cart"); }}
        className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm px-5 py-2 rounded uppercase tracking-wider"
      >
        View Cart
      </button>
    </div>
  );
}
