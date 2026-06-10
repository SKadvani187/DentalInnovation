import { createContext, useContext, useMemo, useCallback, useEffect, useRef } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import api from "../lib/api";
import { useAuth } from "./AuthContext";

const WishlistContext = createContext(null);

export function WishlistProvider({ children }) {
  const [ids, setIds] = useLocalStorage("sdi:wishlist", []);
  const { token } = useAuth();
  const synced = useRef(false);

  // On login: merge local + server wishlist, then push back the union.
  useEffect(() => {
    if (!token || synced.current) return;
    synced.current = true;
    api.syncWishlist(ids)
      .then((merged) => { if (Array.isArray(merged)) setIds(merged); })
      .catch((err) => {
        // A wishlist sync failure must NOT log the user out — just stay in local-only
        // mode. (Previously a 401 here cleared the token and broke account/checkout.)
        console.warn("[wishlist] sync failed:", err.message);
      });
  }, [token, ids, setIds]);

  // Reset sync flag on logout so next login re-syncs.
  useEffect(() => { if (!token) synced.current = false; }, [token]);

  const toggle = useCallback((id) => {
    setIds((prev) => {
      const next = prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id];
      if (token) api.syncWishlist(next).catch(() => {});
      return next;
    });
  }, [setIds, token]);

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
