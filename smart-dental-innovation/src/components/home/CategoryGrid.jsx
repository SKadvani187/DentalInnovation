import { useRef } from "react";
import { categoryTiles as staticTiles } from "../../data/categories";
import { useAppNavigate } from "../../hooks/useAppNavigate";

export default function CategoryGrid({ items }) {
  const scroller = useRef(null);
  const navigate = useAppNavigate();

  // Use API-provided categories when given; show only those with images (home grid).
  const categories = items ? items.filter((c) => c.img) : staticTiles;
  const isReqCategorySection = categories != null && categories.length > 0 ? true : false;

  const scroll = (dir) => {
    const el = scroller.current;
    if (!el) return;
    el.scrollBy({ left: dir * 520, behavior: "smooth" });
  };

  return (
    isReqCategorySection ? 
    (<section className="mx-auto px-3 sm:px-6 py-6 sm:py-10">
      <div className="relative">
        <div
          ref={scroller}
          className="flex gap-5 sm:gap-8 overflow-x-auto no-scrollbar py-3 -mx-1 px-1 scroll-smooth"
        >
          {categories.map((c) => (
            <button
              key={c.id}
              onClick={() => navigate("category", { category: c.id })}
              className="category-tile flex flex-col items-center shrink-0 w-[110px] sm:w-[150px] group cursor-pointer"
            >
              <div className="category-tile__circle w-24 h-24 sm:w-36 sm:h-36 rounded-full bg-white ring-1 ring-gray-200 overflow-hidden flex items-center justify-center">
                <img
                  src={c.img}
                  alt={c.title}
                  loading="lazy"
                  className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                />
              </div>
              <p className="mt-4 text-sm sm:text-base text-center font-semibold text-brand-ink leading-tight line-clamp-2 transition-colors duration-200 group-hover:text-[#3684bf]">
                {c.title}
              </p>
            </button>
          ))}
        </div>

        {categories != null && categories.length > 0 && (
          <>
            <button
              onClick={() => scroll(-1)}
              aria-label="Scroll left"
              className="hidden sm:flex absolute -left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white shadow-md ring-1 ring-gray-200 hover:bg-brand-navy hover:text-white items-center justify-center transition"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M14 6 8 12l6 6 1.41-1.41L10.83 12l4.58-4.59z" />
              </svg>
            </button>

            <button
              onClick={() => scroll(1)}
              aria-label="Scroll right"
              className="hidden sm:flex absolute -right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white shadow-md ring-1 ring-gray-200 hover:bg-brand-navy hover:text-white items-center justify-center transition"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
              </svg>
            </button>
          </>
        )}
      </div>
    </section>) : null
  );
}
