import { useCart } from "../../context/CartContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useWishlist } from "../../context/WishlistContext";
import { useAuth } from "../../context/AuthContext";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function ProductCard({ product, onOpen }) {
  const { addToCart } = useCart();
  const { openModal, showToast } = useUI();
  const navigate = useAppNavigate();
  const { has, toggle } = useWishlist();
  const { user } = useAuth();
  // onOpen lets a host (e.g. the search overlay) close itself before navigating away.
  const openProduct = () => { onOpen?.(); navigate("product", { id: product.id, name: product.name }); };
  const wished = has(product.id);

  const onWish = (e) => {
    e.stopPropagation();
    if (!user) { openModal("auth"); return; }
    toggle(product.id);
  };

  const onAdd = (e) => {
    e?.stopPropagation();
    addToCart(product, 1);
    showToast?.("Added to cart!", "success");
    openModal("cart");
  };

  return (
    <article className="group relative flex flex-col bg-[#f8f9fa] border border-gray-100 rounded-2xl p-3 overflow-hidden shadow-sm hover:shadow-md transition duration-300">
      <div className="relative w-full aspect-square bg-white rounded-xl overflow-hidden border border-gray-50">
        <button onClick={() => openProduct()} className="w-full h-full block relative">
          {/* Primary image — fades out on hover */}
          <img
            src={product.image}
            alt={product.name}
            loading="lazy"
            className="absolute inset-0 w-full h-full object-contain p-2 transition-opacity duration-300 group-hover:opacity-0"
          />
          {/* Secondary (white-bg) image — fades in on hover; falls back to primary if not set */}
          <img
            src={product.hoverImage || product.image}
            alt={product.name}
            loading="lazy"
            className="absolute inset-0 w-full h-full object-contain p-2 bg-white opacity-0 transition-opacity duration-300 group-hover:opacity-100"
          />
        </button>
        <button
          onClick={onWish}
          aria-label="Wishlist"
          className="absolute top-2 right-2 w-8 h-8 rounded-full bg-white shadow flex items-center justify-center hover:bg-gray-50 z-10"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill={wished ? "#ef4444" : "none"} stroke={wished ? "#ef4444" : "#374151"} strokeWidth="2">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
          </svg>
        </button>

        <div className="absolute bottom-2 left-2 bg-white/95 backdrop-blur px-2 py-0.5 rounded-full shadow-sm flex items-center gap-1 text-[11px] sm:text-xs font-bold text-gray-800 border border-gray-100">
          <span className="text-amber-500">★</span>
          <span>{product.rating?.toFixed(1) || "5.0"}</span>
          <span className="text-gray-300 mx-0.5">|</span>
          <svg className="w-3.5 h-3.5 text-[#4a92cb]" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM10 14.5l-3-3 1.41-1.41L10 11.67l4.59-4.59L16 8.5l-6 6z" />
          </svg>
          <span className="text-gray-600 font-medium">{product.reviews || "0"}</span>
        </div>
      </div>

      <div className="flex flex-col flex-1 pt-3 pb-1 gap-2.5">
        <button
          onClick={() => openProduct()}
          className="text-sm sm:text-base font-semibold text-gray-900 text-left line-clamp-2 min-h-[44px] leading-tight hover:text-[#4a92cb] transition-colors"
        >
          {product.name}
        </button>

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

        <button
          onClick={onAdd}
          className="w-full py-2.5 sm:py-3 mt-1 rounded-xl bg-[#4a92cb] hover:bg-[#3b81b8] text-white text-xs sm:text-sm font-bold uppercase tracking-wider transition-all duration-200 active:scale-[0.98]"
        >
          Add to Cart
        </button>
      </div>
    </article>
  );
}
