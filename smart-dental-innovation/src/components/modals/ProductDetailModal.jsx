import { useEffect, useState } from "react";
import Modal from "../ui/Modal";
import StarRating from "../ui/StarRating";
import Button from "../ui/Button";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function ProductDetailModal() {
  const { modal, closeModal, selectedProduct } = useUI();
  const { addToCart } = useCart();
  const { has, toggle } = useWishlist();
  const [qty, setQty] = useState(1);
  const [variant, setVariant] = useState(null);

  useEffect(() => {
    setQty(1);
    setVariant(selectedProduct?.variants?.[0] || null);
  }, [selectedProduct]);

  if (!selectedProduct) return null;
  const p = selectedProduct;
  const wished = has(p.id);

  const handleAdd = () => {
    addToCart(p, qty, variant);
    closeModal();
  };

  return (
    <Modal open={modal === "product"} onClose={closeModal} maxWidth="max-w-4xl">
      <div className="grid grid-cols-1 md:grid-cols-2">
        <div className="bg-gray-50 aspect-square">
          <img src={p.image} alt={p.name} className="w-full h-full object-cover" />
        </div>
        <div className="p-5 sm:p-6 flex flex-col">
          <div className="flex items-start justify-between gap-3">
            <h2 className="text-lg sm:text-xl font-bold text-brand-ink">{p.name}</h2>
            <button onClick={closeModal} aria-label="Close" className="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center shrink-0">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6l12 12M6 18L18 6" /></svg>
            </button>
          </div>

          <div className="mt-2"><StarRating value={p.rating} reviews={p.reviews} /></div>

          <div className="mt-3 flex items-baseline gap-3">
            <span className="text-2xl font-bold text-brand-ink">{fmt(p.price)}</span>
            {p.mrp > p.price && (
              <>
                <span className="text-sm text-brand-muted line-through">{fmt(p.mrp)}</span>
                <span className="text-xs font-bold text-brand-orange">{p.discount}% OFF</span>
              </>
            )}
          </div>

          <p className="mt-4 text-sm text-brand-muted leading-relaxed">{p.description}</p>

          {p.variants && (
            <div className="mt-5">
              <p className="text-xs font-semibold uppercase tracking-wider text-brand-ink mb-2">Variant</p>
              <div className="flex gap-2 flex-wrap">
                {p.variants.map((v) => (
                  <button
                    key={v}
                    onClick={() => setVariant(v)}
                    className={`px-3 py-1.5 rounded-md text-xs font-semibold border transition ${variant === v ? "bg-brand-navy text-white border-brand-navy" : "bg-white text-brand-ink border-gray-300 hover:border-brand-navy"}`}
                  >
                    {v}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className="mt-5">
            <p className="text-xs font-semibold uppercase tracking-wider text-brand-ink mb-2">Quantity</p>
            <div className="inline-flex items-center border border-gray-300 rounded-lg">
              <button onClick={() => setQty((q) => Math.max(1, q - 1))} className="w-9 h-9 hover:bg-gray-50">−</button>
              <span className="w-10 text-center text-sm font-semibold">{qty}</span>
              <button onClick={() => setQty((q) => q + 1)} className="w-9 h-9 hover:bg-gray-50">+</button>
            </div>
          </div>

          <div className="mt-auto pt-6 flex flex-col sm:flex-row gap-2">
            <Button variant="primary" size="lg" className="flex-1" onClick={handleAdd}>
              Add to Cart
            </Button>
            <Button variant={wished ? "navy" : "outline"} size="lg" onClick={() => toggle(p.id)}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill={wished ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8">
                <path d="M12 21s-7-4.534-9.5-9C.5 7.5 4 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 3 0 6.5 3.5 4.5 8-2.5 4.466-9.5 9-9.5 9z" />
              </svg>
              {wished ? "Saved" : "Wishlist"}
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  );
}
