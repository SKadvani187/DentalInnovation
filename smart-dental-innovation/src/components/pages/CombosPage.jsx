import { useMemo, useState } from "react";
import { categoryFilters as CATEGORY_FILTERS } from "../../data/categories";
import { sortOptions as SORT_OPTIONS } from "../../data/site";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useCombos } from "../../hooks/useApiData";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function CombosPage() {
  const { addToCart } = useCart();
  const { openModal, navigate } = useUI();
  const openProduct = (p) => navigate("product", { id: p.id });
  const [sort, setSort] = useState("all");
  const [sortOpen, setSortOpen] = useState(false);
  const [selectedCat, setSelectedCat] = useState(null);
  const { data: combos } = useCombos();

  const list = useMemo(() => {
    const items = [...combos];
    switch (sort) {
      case "price-asc": items.sort((a, b) => a.price - b.price); break;
      case "price-desc": items.sort((a, b) => b.price - a.price); break;
      case "discount": items.sort((a, b) => b.discount - a.discount); break;
      default: break;
    }
    return items;
  }, [sort, combos]);

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
            {CATEGORY_FILTERS.map((c) => (
              <li key={c.id}>
                <label className={`flex items-center gap-3 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-50 ${selectedCat === c.id ? "bg-gray-100" : ""}`}>
                  <span className={`w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 ${selectedCat === c.id ? "border-[#3684bf]" : "border-gray-400"}`}>
                    {selectedCat === c.id && <span className="w-2 h-2 rounded-full bg-[#3684bf]" />}
                  </span>
                  <span className="text-sm text-brand-ink">{c.label}</span>
                  <input type="radio" checked={selectedCat === c.id} onChange={() => setSelectedCat(c.id)} className="hidden" />
                </label>
              </li>
            ))}
          </ul>
        </aside>

        <section className="flex-1 min-w-0">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            {list.map((p) => (
              <article key={p.id} className="border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col hover:shadow-md transition">
                <button
                  onClick={() => openProduct(p)}
                  className="w-full aspect-square bg-gray-50 flex items-center justify-center p-4 cursor-pointer"
                >
                  <img src={p.image} alt={p.name} className="max-w-full max-h-full object-contain" />
                </button>
                <div className="p-4 flex flex-col flex-1">
                  <h3
                    onClick={() => openProduct(p)}
                    className="text-sm font-bold text-brand-ink line-clamp-2 mb-3 cursor-pointer hover:text-[#3684bf]"
                  >
                    {p.name}
                  </h3>

                  <div className="flex items-center gap-2 flex-wrap mb-3">
                    <span className="text-xs text-brand-muted line-through">₹{p.mrp.toLocaleString("en-IN")}</span>
                    <span className="text-base font-bold text-brand-ink">{fmt(p.price)}</span>
                    {p.discount > 0 && (
                      <span className="text-xs font-bold text-green-600">| {p.discount}% OFF</span>
                    )}
                  </div>

                  <div className="mt-auto">
                    <button
                      onClick={() => { addToCart(p, 1); openModal("cart"); }}
                      className="w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm py-2.5 rounded-md uppercase tracking-wider transition"
                    >
                      Add to Cart
                    </button>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}
