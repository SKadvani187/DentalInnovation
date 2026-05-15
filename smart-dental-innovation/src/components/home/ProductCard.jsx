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
    <article className="group relative flex flex-col bg-[#f8f9fa] border border-gray-100 rounded-2xl p-3 overflow-hidden shadow-sm hover:shadow-md transition duration-300">
      
      {/* Wishlist Top Right Button */}
      <button
        onClick={(e) => { e.stopPropagation(); toggle(product.id); }}
        aria-label="Toggle wishlist"
        className="absolute top-5 right-5 z-10 w-8 h-8 rounded-full bg-white/90 backdrop-blur shadow-sm flex items-center justify-center hover:scale-110 transition"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill={wished ? "#ff6b1a" : "none"} stroke={wished ? "#ff6b1a" : "#1a1a1a"} strokeWidth="1.8">
          <path d="M12 21s-7-4.534-9.5-9C.5 7.5 4 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 3 0 6.5 3.5 4.5 8-2.5 4.466-9.5 9-9.5 9z" />
        </svg>
      </button>

      {/* Main Image Container Area */}
      <div className="relative w-full aspect-square bg-white rounded-xl overflow-hidden border border-gray-50">
        <button onClick={() => openProduct(product)} className="w-full h-full block">
          <img
            src={product.image}
            alt={product.name}
            loading="lazy"
            className="w-full h-full object-contain p-2 group-hover:scale-102 transition duration-300"
          />
        </button>

        {/* Screenshot Style: Embedded Star Rating Badge Overlay */}
        <div className="absolute bottom-2 left-2 bg-white/95 backdrop-blur px-2 py-0.5 rounded-full shadow-sm flex items-center gap-1 text-[11px] sm:text-xs font-bold text-gray-800 border border-gray-100">
          <span className="text-amber-500">★</span>
          <span>{product.rating?.toFixed(1) || "5.0"}</span>
          <span className="text-gray-300 mx-0.5">|</span>
          {/* Blue Shield/Check Icon */}
          <svg className="w-3.5 h-3.5 text-[#4a92cb]" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM10 14.5l-3-3 1.41-1.41L10 11.67l4.59-4.59L16 8.5l-6 6z" />
          </svg>
          <span className="text-gray-600 font-medium">{product.reviews || "0"}</span>
        </div>
      </div>

      {/* Product Information Details Stack */}
      <div className="flex flex-col flex-1 pt-3 pb-1 gap-2.5">
        <button
          onClick={() => openProduct(product)}
          className="text-sm sm:text-base font-semibold text-gray-900 text-left line-clamp-2 min-h-[44px] leading-tight hover:text-[#4a92cb] transition-colors"
        >
          {product.name}
        </button>

        {/* Horizontal Inline Pricing Layout */}
        <div className="flex flex-wrap items-center gap-2 mt-auto">
          {product.mrp > product.price && (
            <span className="text-xs sm:text-sm text-gray-400 line-through font-normal">
              {fmt(product.mrp)}
            </span>
          )}
          <span className="text-base sm:text-lg font-black text-gray-900">
            {fmt(product.price)}
          </span>
          {product.discount > 0 && (
            <>
              <span className="text-gray-300 text-xs">|</span>
              <span className="text-xs sm:text-sm font-bold text-green-600 whitespace-nowrap">
                {product.discount}% OFF
              </span>
            </>
          )}
        </div>

        {/* Action Trigger Button */}
        <button
          onClick={() => addToCart(product, 1)}
          className="w-full py-2.5 sm:py-3 mt-1 rounded-xl bg-[#4a92cb] hover:bg-[#3b81b8] text-white text-xs sm:text-sm font-bold uppercase tracking-wider transition-all duration-200 active:scale-[0.98]"
        >
          Add to Cart
        </button>
      </div>
    </article>
  );
}