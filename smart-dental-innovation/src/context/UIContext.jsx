import { createContext, useContext, useMemo, useState, useCallback, useRef, useEffect } from "react";

const UIContext = createContext(null);

export function UIProvider({ children }) {
  const [modal, setModal] = useState(null);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [view, setView] = useState({ name: "home", params: null });
  const [toasts, setToasts] = useState([]); // [{ id, type, message }]
  const [searchSeed, setSearchSeed] = useState("");
  const [searchImageFile, setSearchImageFile] = useState(null);
  const idRef = useRef(0);

  const openModal = useCallback((name) => setModal(name), []);
  const closeModal = useCallback(() => setModal(null), []);
  const openSearch = useCallback((seed = "") => {
    setSearchSeed(seed);
    setModal("search");
  }, []);
  const openSearchWithImage = useCallback((file) => {
    setSearchImageFile(file);
    setModal("search");
  }, []);

  const openProduct = useCallback((product) => {
    setSelectedProduct(product);
    setModal("product");
  }, []);

  const navigate = useCallback((name, params = null) => {
    setView({ name, params });
    window.scrollTo({ top: 0, behavior: "instant" });
  }, []);

  const dismissToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const showToast = useCallback((message, type = "success") => {
    const id = ++idRef.current;
    setToasts((prev) => [...prev, { id, type, message }]);
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 2500);
  }, []);

  const value = useMemo(
    () => ({ modal, openModal, closeModal, openSearch, openSearchWithImage, searchSeed, setSearchSeed, searchImageFile, setSearchImageFile, selectedProduct, setSelectedProduct, openProduct, view, navigate, toasts, showToast, dismissToast }),
    [modal, openModal, closeModal, openSearch, openSearchWithImage, searchSeed, searchImageFile, selectedProduct, openProduct, view, navigate, toasts, showToast, dismissToast]
  );

  return <UIContext.Provider value={value}>{children}</UIContext.Provider>;
}

export const useUI = () => {
  const ctx = useContext(UIContext);
  if (!ctx) throw new Error("useUI must be used inside UIProvider");
  return ctx;
};
