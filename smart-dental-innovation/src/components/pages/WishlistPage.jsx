import { useMemo } from "react";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";
import { useWishlist } from "../../context/WishlistContext";
import { useCart } from "../../context/CartContext";
import { useProducts, useCombos } from "../../hooks/useApiData";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

export default function WishlistPage() {
  const { user } = useAuth();
  const { navigate, openModal } = useUI();
  const { ids, remove } = useWishlist();
  const { addToCart } = useCart();
  const { data: allProducts } = useProducts();
  const { data: combos } = useCombos();

  const items = useMemo(
    () => ids.map((id) => allProducts.find((p) => p.id === id) || combos.find((c) => c.id === id)).filter(Boolean),
    [ids, allProducts, combos]
  );

  if (!user) {
    return (
      <div className="max-w-[1200px] mx-auto px-4 py-12 text-center">
        <p className="text-brand-muted mb-4">Please sign in to view wishlist.</p>
        <button
          onClick={() => openModal("auth")}
          className="bg-[#3684bf] text-white font-bold px-6 py-2.5 rounded-md hover:bg-[#1f5f96]"
        >
          Sign In
        </button>
      </div>
    );
  }

  return (
    <div className="max-w-[1200px] mx-auto px-4 py-6">
      <nav className="text-sm text-brand-muted mb-4 flex items-center gap-2">
        <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
        <span>/</span>
        <button onClick={() => navigate("account")} className="hover:text-[#3684bf]">Account</button>
      </nav>

      <h1 className="text-3xl font-bold text-brand-ink mb-6">Wishlist</h1>

      {items.length === 0 ? (
        <div className="bg-gray-100 rounded-2xl py-16 px-4 flex flex-col items-center text-center">
          <div className="w-32 h-32 rounded-full bg-gray-700 flex items-center justify-center mb-6">
            <svg width="60" height="60" viewBox="0 0 24 24" fill="white">
              <path d="M19 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-3 11H9v-1h7v1zm0-3H9V9h7v1zm0-3H9V6h7v1z" />
            </svg>
          </div>
          <h3 className="text-xl font-bold text-brand-ink mb-1">No items in your wishlist</h3>
          <p className="text-sm text-brand-muted mb-6">
            Your wishlist is empty. Add items to your wishlist to view them here.
          </p>
          <button
            onClick={() => navigate("category")}
            className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold px-10 py-3 rounded-full"
          >
            Start Shopping
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
          {items.map((p) => (
            <article key={p.id} className="border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col hover:shadow-md transition">
              <div className="relative">
                <button
                  onClick={() => navigate("product", { id: p.id })}
                  className="w-full aspect-square bg-gray-50 flex items-center justify-center p-4"
                >
                  <img src={p.image} alt={p.name} className="max-w-full max-h-full object-contain" />
                </button>
                <button
                  onClick={() => remove(p.id)}
                  aria-label="Remove from wishlist"
                  className="absolute top-2 right-2 w-8 h-8 rounded-full bg-white shadow flex items-center justify-center text-red-500 hover:bg-red-50"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                  </svg>
                </button>
              </div>
              <div className="p-4 flex flex-col flex-1">
                <h3
                  className="text-sm font-bold text-brand-ink line-clamp-2 mb-2 cursor-pointer hover:text-[#3684bf]"
                  onClick={() => navigate("product", { id: p.id })}
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
                <button
                  onClick={() => { addToCart(p, 1); openModal("cart"); }}
                  className="mt-auto w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm py-2.5 rounded-md uppercase tracking-wider"
                >
                  Add to Cart
                </button>
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
