import { useMemo, useState } from "react";
import { allProducts } from "../../data/products";
import { combos } from "../../data/combos";
import { categoryFilters as CATEGORY_FILTERS } from "../../data/categories";
import { sortOptions as SORT_OPTIONS, priceBounds } from "../../data/site";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";

const PRICE_MIN = priceBounds.min;
const PRICE_MAX = priceBounds.max;
const COLLAPSE_COUNT = 10;

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function CategoryPage() {
  const { view } = useUI();
  const isGvp = view?.name === "gvp";
  const initialCategory = view?.params?.category || null;
  const initialPriceMax = view?.params?.priceMax || PRICE_MAX;
  const [selectedCat, setSelectedCat] = useState(initialCategory);
  const [sort, setSort] = useState(isGvp ? "discount" : "all");
  const [sortOpen, setSortOpen] = useState(false);
  const [expanded, setExpanded] = useState(false);
  const [priceMin, setPriceMin] = useState(PRICE_MIN);
  const [priceMax, setPriceMax] = useState(initialPriceMax);

  const products = useMemo(() => {
    let list = [...combos, ...allProducts].filter((p) => p.id);
    if (isGvp) list = list.filter((p) => p.discount >= 10);
    if (selectedCat) list = list.filter((p) => p.category === selectedCat);
    list = list.filter((p) => p.price >= priceMin && p.price <= priceMax);
    switch (sort) {
      case "price-asc": list.sort((a, b) => a.price - b.price); break;
      case "price-desc": list.sort((a, b) => b.price - a.price); break;
      case "discount": list.sort((a, b) => b.discount - a.discount); break;
      case "rating": list.sort((a, b) => b.rating - a.rating); break;
      default: break;
    }
    return list;
  }, [selectedCat, sort, priceMin, priceMax, isGvp]);

  const visibleCats = expanded ? CATEGORY_FILTERS : CATEGORY_FILTERS.slice(0, COLLAPSE_COUNT);

  return (
    <div className="max-w-[1400px] mx-auto px-4 py-6">
      <div className="flex flex-col lg:flex-row gap-6">
        <aside className="w-full lg:w-[260px] shrink-0">
          <h2 className="text-xl font-bold text-brand-ink mb-4">Filters</h2>

          <div className="relative mb-6">
            <button
              onClick={() => setSortOpen((v) => !v)}
              className="w-full flex items-center justify-between gap-2 border border-gray-300 rounded-full px-4 py-2.5 text-sm hover:border-gray-400"
            >
              <span className="text-brand-ink">
                Sort By: <span className="font-bold">{SORT_OPTIONS.find((s) => s.id === sort)?.label}</span>
              </span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" className={`transition ${sortOpen ? "rotate-180" : ""}`}>
                <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
              </svg>
            </button>
            {sortOpen && (
              <div className="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                {SORT_OPTIONS.map((s) => (
                  <button
                    key={s.id}
                    onClick={() => { setSort(s.id); setSortOpen(false); }}
                    className={`w-full text-left px-4 py-2 text-sm hover:bg-gray-50 ${sort === s.id ? "font-bold text-[#3684bf]" : "text-brand-ink"}`}
                  >
                    {s.label}
                  </button>
                ))}
              </div>
            )}
          </div>

          <h3 className="text-base font-bold text-brand-ink mb-2">Categories</h3>
          <ul className="space-y-1">
            <li>
              <CatRadio
                label="All Categories"
                checked={selectedCat === null}
                onChange={() => setSelectedCat(null)}
              />
            </li>
            {visibleCats.map((c) => (
              <li key={c.id}>
                <CatRadio
                  label={c.label}
                  checked={selectedCat === c.id}
                  onChange={() => setSelectedCat(c.id)}
                />
              </li>
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
            <PriceRange
              min={PRICE_MIN}
              max={PRICE_MAX}
              valueMin={priceMin}
              valueMax={priceMax}
              onChange={(lo, hi) => { setPriceMin(lo); setPriceMax(hi); }}
            />
            <div className="flex items-center justify-between text-xs text-brand-muted mt-2">
              <span>{priceMin.toLocaleString("en-IN")}</span>
              <span>{priceMax.toLocaleString("en-IN")}</span>
            </div>
          </div>
        </aside>

        <section className="flex-1 min-w-0">
          {products.length === 0 ? (
            <div className="py-20 text-center text-brand-muted">No products found.</div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
              {products.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
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
  const { openProduct, openModal, closeModal } = useUI();
  const hasVariants = Array.isArray(product.variants) && product.variants.length >= 1;
  const oos = product.inStock === false;

  const onAdd = (e) => {
    e.preventDefault();
    e.stopPropagation();
    addToCart(product, 1);
    closeModal();
    requestAnimationFrame(() => openModal("cart"));
  };

  return (
    <article className="border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col hover:shadow-md transition">
      <div className="relative">
        <button
          onClick={() => openProduct(product)}
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
          onClick={() => openProduct(product)}
        >
          {product.name}
        </h3>

        {hasVariants && !oos && (
          <p className="text-xs text-[#3684bf] font-semibold mb-1">
            {product.variants.length} variants available <span className="text-brand-muted font-normal">from</span>
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
              onClick={onAdd}
              className="w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm py-2.5 rounded-md uppercase tracking-wider transition"
            >
              Add to Cart
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
    </article>
  );
}
