import Drawer from "../ui/Drawer";
import Button from "../ui/Button";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function CartDrawer() {
  const { modal, closeModal, openModal } = useUI();
  const { items, updateQty, removeFromCart, subtotal, itemCount } = useCart();

  return (
    <Drawer
      open={modal === "cart"}
      onClose={closeModal}
      title={`Your Cart (${itemCount})`}
      footer={
        items.length > 0 && (
          <div className="space-y-3">
            <div className="flex items-center justify-between text-sm">
              <span className="text-brand-muted">Subtotal</span>
              <span className="font-bold text-brand-ink text-lg">{fmt(subtotal)}</span>
            </div>
            <Button variant="primary" size="lg" className="w-full" onClick={() => openModal("checkout")}>
              Proceed to Checkout
            </Button>
          </div>
        )
      }
    >
      {items.length === 0 ? (
        <div className="h-full flex flex-col items-center justify-center text-center py-20">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="1.5" className="mb-4">
            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 7m12-7l2 7M9 21a1 1 0 11-2 0 1 1 0 012 0zm10 0a1 1 0 11-2 0 1 1 0 012 0z" />
          </svg>
          <p className="text-brand-ink font-semibold mb-1">Your cart is empty</p>
          <p className="text-xs text-brand-muted">Add products from the catalog to get started.</p>
        </div>
      ) : (
        <ul className="divide-y divide-gray-200">
          {items.map((i) => (
            <li key={i.key} className="py-4 flex gap-3">
              <img src={i.image} alt={i.name} className="w-20 h-20 rounded-lg object-cover bg-gray-50 shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-brand-ink line-clamp-2">{i.name}</p>
                {i.variant && <p className="text-xs text-brand-muted mt-0.5">Variant: {i.variant}</p>}
                <p className="text-sm font-bold text-brand-ink mt-1">{fmt(i.price)}</p>
                <div className="mt-2 flex items-center justify-between">
                  <div className="inline-flex items-center border border-gray-300 rounded-md text-sm">
                    <button onClick={() => updateQty(i.key, i.qty - 1)} className="w-7 h-7 hover:bg-gray-50">−</button>
                    <span className="w-8 text-center">{i.qty}</span>
                    <button onClick={() => updateQty(i.key, i.qty + 1)} className="w-7 h-7 hover:bg-gray-50">+</button>
                  </div>
                  <button onClick={() => removeFromCart(i.key)} className="text-xs text-brand-muted hover:text-brand-orange">
                    Remove
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Drawer>
  );
}
