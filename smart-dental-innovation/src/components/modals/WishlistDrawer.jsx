import Drawer from "../ui/Drawer";
import Button from "../ui/Button";
import { useUI } from "../../context/UIContext";
import { useWishlist } from "../../context/WishlistContext";
import { useCart } from "../../context/CartContext";
import { findProductById } from "../../data/products";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function WishlistDrawer() {
  const { modal, closeModal } = useUI();
  const { ids, remove } = useWishlist();
  const { addToCart } = useCart();

  const items = ids.map(findProductById).filter(Boolean);

  return (
    <Drawer open={modal === "wishlist"} onClose={closeModal} title={`Your Wishlist (${items.length})`}>
      {items.length === 0 ? (
        <div className="h-full flex flex-col items-center justify-center text-center py-20">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="1.5" className="mb-4">
            <path d="M12 21s-7-4.534-9.5-9C.5 7.5 4 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 3 0 6.5 3.5 4.5 8-2.5 4.466-9.5 9-9.5 9z" />
          </svg>
          <p className="text-brand-ink font-semibold mb-1">Your wishlist is empty</p>
          <p className="text-xs text-brand-muted">Tap the heart on any product to save it.</p>
        </div>
      ) : (
        <ul className="divide-y divide-gray-200">
          {items.map((p) => (
            <li key={p.id} className="py-4 flex gap-3">
              <img src={p.image} alt={p.name} className="w-20 h-20 rounded-lg object-cover bg-gray-50 shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-brand-ink line-clamp-2">{p.name}</p>
                <p className="text-sm font-bold text-brand-ink mt-1">{fmt(p.price)}</p>
                <div className="mt-2 flex gap-2">
                  <Button
                    size="sm"
                    variant="navy"
                    onClick={() => {
                      addToCart(p, 1);
                      remove(p.id);
                    }}
                  >
                    Move to Cart
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => remove(p.id)}>Remove</Button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Drawer>
  );
}
