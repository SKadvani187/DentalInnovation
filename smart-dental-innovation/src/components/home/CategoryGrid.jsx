import { useRef } from "react";
import { categories } from "../../data/categories";

export default function CategoryGrid() {
  const scroller = useRef(null);

  const scroll = (dir) => {
    const el = scroller.current;
    if (!el) return;
    // Increased scroll step from 320 to 420 to account for wider elements
    el.scrollBy({ left: dir * 420, behavior: "smooth" });
  };

  return (
    <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">

      <div className="relative">
        <div
          ref={scroller}
          className="flex gap-4 sm:gap-6 overflow-x-auto no-scrollbar py-2 -mx-1 px-1 scroll-smooth"
        >
          {categories.map((c) => (
            <button
              key={c.id}
              // Scaled outer container width: w-[80px] -> w-[95px] | sm:w-[100px] -> sm:w-[130px]
              className="flex flex-col items-center shrink-0 w-[95px] sm:w-[130px] group cursor-pointer"
            >
              {/* Scaled circle container: w-16/h-16 -> w-20/h-20 | sm:w-20/sm:h-20 -> sm:w-28/sm:h-28 */}
              <div className="w-20 h-20 sm:w-28 sm:h-28 rounded-full bg-gray-50 ring-1 ring-gray-200 group-hover:ring-brand-orange overflow-hidden flex items-center justify-center transition">
                <img 
                  src={c.img} 
                  alt={c.title} 
                  loading="lazy" 
                  className="w-full h-full object-contain p-2 sm:p-3" 
                />
              </div>
              {/* Scaled text font size and margin top slightly to balance the larger circles */}
              <p className="mt-3 text-xs sm:text-sm text-center font-medium text-brand-ink leading-tight line-clamp-2">
                {c.title}
              </p>
            </button>
          ))}
        </div>

        <button
          onClick={() => scroll(-1)}
          aria-label="Scroll left"
          className="hidden sm:flex absolute -left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white shadow-md ring-1 ring-gray-200 hover:bg-brand-navy hover:text-white items-center justify-center transition"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14 6 8 12l6 6 1.41-1.41L10.83 12l4.58-4.59z" /></svg>
        </button>
        <button
          onClick={() => scroll(1)}
          aria-label="Scroll right"
          className="hidden sm:flex absolute -right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white shadow-md ring-1 ring-gray-200 hover:bg-brand-navy hover:text-white items-center justify-center transition"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" /></svg>
        </button>
      </div>
    </section>
  );
}