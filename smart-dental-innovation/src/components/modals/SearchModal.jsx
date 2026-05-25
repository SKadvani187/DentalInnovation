import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";
import { allProducts } from "../../data/products";
import ProductCard from "../home/ProductCard";
import Footer from "../layout/Footer";

export default function SearchModal() {
  const { modal, closeModal } = useUI();
  const open = modal === "search";
  const [query, setQuery] = useState("");
  const inputRef = useRef(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e) => e.key === "Escape" && closeModal();
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    const t = setTimeout(() => inputRef.current?.focus(), 50);
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
      clearTimeout(t);
    };
  }, [open, closeModal]);

  useEffect(() => {
    if (!open) setQuery("");
  }, [open]);

  const results = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return allProducts;
    return allProducts.filter((p) => {
      const hay = `${p.name} ${p.category || ""} ${p.warranty || ""}`.toLowerCase();
      return q.split(/\s+/).every((tok) => hay.includes(tok));
    });
  }, [query]);

  const showInitial = !query.trim();
  const count = results.length;

  return createPortal(
    <div
      className={`fixed inset-0 z-[1200] bg-white flex flex-col transition-opacity duration-200 ${
        open ? "opacity-100" : "opacity-0 pointer-events-none"
      }`}
      role="dialog"
      aria-modal="true"
    >
      <header className="flex items-center gap-3 px-4 sm:px-6 py-3 border-b border-gray-200 bg-white sticky top-0 z-10">
        <button
          onClick={closeModal}
          aria-label="Back"
          className="w-9 h-9 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-ink shrink-0"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 6l-6 6 6 6" />
          </svg>
        </button>
        <div className="flex-1 flex items-center gap-2 bg-gray-100 rounded-full px-4 h-11">
          <svg width="18" height="18" viewBox="0 0 15 15" fill="none" stroke="#6b7280" strokeWidth="1.4" className="shrink-0">
            <path d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z" />
          </svg>
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search products..."
            className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none text-brand-ink placeholder:text-gray-400"
          />
          {!showInitial && (
            <span className="text-xs text-brand-muted whitespace-nowrap">
              {count} result{count === 1 ? "" : "s"}
            </span>
          )}
          {query && (
            <button
              onClick={() => { setQuery(""); inputRef.current?.focus(); }}
              aria-label="Clear"
              className="w-7 h-7 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-500"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                <path d="M6 6l12 12M18 6L6 18" />
              </svg>
            </button>
          )}
        </div>
      </header>

      <div className="flex-1 overflow-y-auto bg-white">
        <div className="px-4 sm:px-6 py-5">
          {count === 0 ? (
            <div className="bg-gray-100 rounded-2xl py-16 px-6 text-center mt-4">
              <h3 className="text-2xl font-bold text-brand-ink mb-2">No Results Found</h3>
              <p className="text-sm text-brand-muted">No products found for "{query}"</p>
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
              {results.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          )}
        </div>
        <Footer />
      </div>
    </div>,
    document.body
  );
}
