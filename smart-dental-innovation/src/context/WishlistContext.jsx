import { createContext, useContext, useMemo, useCallback } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";

const WishlistContext = createContext(null);

export function WishlistProvider({ children }) {
  const [ids, setIds] = useLocalStorage("sdi:wishlist", []);

  const toggle = useCallback((id) => {
    setIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }, [setIds]);

  const remove = useCallback((id) => {
    setIds((prev) => prev.filter((x) => x !== id));
  }, [setIds]);

  const has = useCallback((id) => ids.includes(id), [ids]);

  const value = useMemo(
    () => ({ ids, toggle, remove, has, count: ids.length }),
    [ids, toggle, remove, has]
  );

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>;
}

export const useWishlist = () => {
  const ctx = useContext(WishlistContext);
  if (!ctx) throw new Error("useWishlist must be used inside WishlistProvider");
  return ctx;
};
