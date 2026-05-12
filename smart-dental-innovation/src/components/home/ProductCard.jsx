import StarRating from "../ui/StarRating";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import { useUI } from "../../context/UIContext";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function ProductCard({ product }) {
  const { addToCart } = useCart();
  const { has, toggle } = useWishlist();
  const { openProduct } = useUI();
  const wished = has(product.id);

  return (
    <article className="group relative flex flex-col bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg hover:border-brand-navy/30 transition">
      <button
        onClick={(e) => { e.stopPropagation(); toggle(product.id); }}
        aria-label="Toggle wishlist"
        className="absolute top-2 right-2 z-10 w-8 h-8 rounded-full bg-white/90 backdrop-blur shadow flex items-center justify-center hover:scale-110 transition"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill={wished ? "#ff6b1a" : "none"} stroke={wished ? "#ff6b1a" : "#1a1a1a"} strokeWidth="1.8">
          <path d="M12 21s-7-4.534-9.5-9C.5 7.5 4 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 3 0 6.5 3.5 4.5 8-2.5 4.466-9.5 9-9.5 9z" />
        </svg>
      </button>

      {product.discount > 0 && (
        <span className="absolute top-2 left-2 z-10 px-2 py-0.5 rounded bg-brand-orange text-white text-[10px] font-bold">
          {product.discount}% OFF
        </span>
      )}

      <button onClick={() => openProduct(product)} className="block aspect-square bg-gray-50 overflow-hidden">
        <img
          src={product.image}
          alt={product.name}
          loading="lazy"
          className="w-full h-full object-cover group-hover:scale-105 transition duration-300"
        />
      </button>

      <div className="flex flex-col flex-1 p-3 gap-2">
        <button
          onClick={() => openProduct(product)}
          className="text-sm font-medium text-brand-ink text-left line-clamp-2 min-h-[40px] hover:text-brand-navy"
        >
          {product.name}
        </button>

        <StarRating value={product.rating} reviews={product.reviews} />

        <div className="flex items-baseline gap-2">
          <span className="text-base font-bold text-brand-ink">{fmt(product.price)}</span>
          {product.mrp > product.price && (
            <span className="text-xs text-brand-muted line-through">{fmt(product.mrp)}</span>
          )}
        </div>

        <button
          onClick={() => addToCart(product, 1)}
          className="mt-auto w-full py-2 rounded-md bg-brand-navy text-white text-xs font-bold uppercase tracking-wide hover:bg-brand-navy-light transition"
        >
          Add to Cart
        </button>
      </div>
    </article>
  );
}
