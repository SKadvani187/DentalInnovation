import { featured } from "../../data/featured";
import { findProductById } from "../../data/products";
import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function FeaturedCards() {
  const { addToCart } = useCart();
  const { openProduct } = useUI();

  return (
    <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">
      <div className="mb-6">
        <p className="text-[11px] sm:text-xs uppercase tracking-[0.18em] font-semibold text-brand-orange mb-1">
          Featured Products
        </p>
        <h2 className="text-lg sm:text-2xl font-bold text-brand-ink">Built for Clinical Excellence</h2>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
        {featured.map((f, i) => {
          const product = findProductById(f.productId);
          return (
            <div
              key={f.id}
              className={`relative overflow-hidden rounded-2xl ${i % 2 === 0 ? "bg-brand-navy text-white" : "bg-brand-cream text-brand-ink"} p-5 sm:p-7 flex flex-col sm:flex-row gap-5 sm:gap-6`}
            >
              <div className="flex-1">
                <p className={`text-[11px] uppercase tracking-[0.18em] font-semibold mb-2 ${i % 2 === 0 ? "text-brand-orange" : "text-brand-navy"}`}>
                  {f.tagline}
                </p>
                <h3 className="text-xl sm:text-2xl font-bold mb-2">{f.title}</h3>
                <p className={`text-sm mb-4 ${i % 2 === 0 ? "text-white/80" : "text-brand-muted"}`}>
                  {f.description}
                </p>
                <ul className="space-y-1 mb-4">
                  {f.bullets.map((b) => (
                    <li key={b} className="flex items-center gap-2 text-sm">
                      <span className="w-1.5 h-1.5 rounded-full bg-brand-orange" />
                      {b}
                    </li>
                  ))}
                </ul>
                <div className="flex items-baseline gap-2 mb-4">
                  <span className="text-xl font-bold">{fmt(f.price)}</span>
                  <span className={`text-sm line-through ${i % 2 === 0 ? "text-white/50" : "text-brand-muted"}`}>{fmt(f.mrp)}</span>
                </div>
                <div className="flex gap-2 flex-wrap">
                  <button
                    onClick={() => product && addToCart(product, 1)}
                    className="px-4 py-2 rounded-md bg-brand-orange text-white text-xs font-bold uppercase tracking-wide hover:bg-brand-orange-light"
                  >
                    Add to Cart
                  </button>
                  <button
                    onClick={() => product && openProduct(product)}
                    className={`px-4 py-2 rounded-md text-xs font-bold uppercase tracking-wide border ${i % 2 === 0 ? "border-white/40 text-white hover:bg-white/10" : "border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white"}`}
                  >
                    View Details
                  </button>
                </div>
              </div>
              <div className="w-full sm:w-48 lg:w-56 h-40 sm:h-auto rounded-xl overflow-hidden bg-white/10 shrink-0">
                <img src={f.image} alt={f.title} loading="lazy" className="w-full h-full object-cover" />
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
