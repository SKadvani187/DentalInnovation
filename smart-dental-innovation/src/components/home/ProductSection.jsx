import ProductCard from "./ProductCard";

export default function ProductSection({ title, eyebrow, products, accent = "navy" }) {
  return (
    <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">
      <div className="flex items-end justify-between mb-4 sm:mb-6">
        <div>
          {eyebrow && (
            <p className="text-[11px] sm:text-xs uppercase tracking-[0.18em] font-semibold text-brand-orange mb-1">
              {eyebrow}
            </p>
          )}
          <h2 className={`text-lg sm:text-2xl font-bold ${accent === "orange" ? "text-brand-orange" : "text-brand-ink"}`}>
            {title}
          </h2>
        </div>
        <a href="#" className="text-xs sm:text-sm font-semibold text-brand-navy hover:text-brand-orange whitespace-nowrap">
          View All →
        </a>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 md:gap-6">
        {products.map((p) => (
          <ProductCard key={p.id} product={p} />
        ))}
      </div>
    </section>
  );
}
