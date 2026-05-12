import { createContext, useContext, useMemo, useCallback } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";

const CartContext = createContext(null);

export function CartProvider({ children }) {
  const [items, setItems] = useLocalStorage("sdi:cart", []);

  const addToCart = useCallback((product, qty = 1, variant = null) => {
    setItems((prev) => {
      const key = variant ? `${product.id}::${variant}` : product.id;
      const idx = prev.findIndex((i) => i.key === key);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = { ...next[idx], qty: next[idx].qty + qty };
        return next;
      }
      return [
        ...prev,
        {
          key,
          id: product.id,
          name: product.name,
          image: product.image,
          price: product.price,
          mrp: product.mrp,
          variant,
          qty,
        },
      ];
    });
  }, [setItems]);

  const removeFromCart = useCallback((key) => {
    setItems((prev) => prev.filter((i) => i.key !== key));
  }, [setItems]);

  const updateQty = useCallback((key, qty) => {
    setItems((prev) =>
      prev
        .map((i) => (i.key === key ? { ...i, qty: Math.max(1, qty) } : i))
        .filter((i) => i.qty > 0)
    );
  }, [setItems]);

  const clearCart = useCallback(() => setItems([]), [setItems]);

  const { subtotal, itemCount } = useMemo(() => {
    let s = 0;
    let c = 0;
    for (const i of items) {
      s += i.price * i.qty;
      c += i.qty;
    }
    return { subtotal: s, itemCount: c };
  }, [items]);

  const value = useMemo(
    () => ({ items, addToCart, removeFromCart, updateQty, clearCart, subtotal, itemCount }),
    [items, addToCart, removeFromCart, updateQty, clearCart, subtotal, itemCount]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export const useCart = () => {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used inside CartProvider");
  return ctx;
};
