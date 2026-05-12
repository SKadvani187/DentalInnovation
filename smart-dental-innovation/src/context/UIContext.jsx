import { createContext, useContext, useMemo, useState, useCallback } from "react";

const UIContext = createContext(null);

export function UIProvider({ children }) {
  const [modal, setModal] = useState(null); // 'cart'|'wishlist'|'product'|'checkout'|'auth'|null
  const [selectedProduct, setSelectedProduct] = useState(null);

  const openModal = useCallback((name) => setModal(name), []);
  const closeModal = useCallback(() => setModal(null), []);

  const openProduct = useCallback((product) => {
    setSelectedProduct(product);
    setModal("product");
  }, []);

  const value = useMemo(
    () => ({ modal, openModal, closeModal, selectedProduct, setSelectedProduct, openProduct }),
    [modal, openModal, closeModal, selectedProduct, openProduct]
  );

  return <UIContext.Provider value={value}>{children}</UIContext.Provider>;
}

export const useUI = () => {
  const ctx = useContext(UIContext);
  if (!ctx) throw new Error("useUI must be used inside UIProvider");
  return ctx;
};
