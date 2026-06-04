import { createContext, useContext, useMemo, useCallback, useEffect, useRef } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import api from "../lib/api";
import { useAuth } from "./AuthContext";

const WishlistContext = createContext(null);

export function WishlistProvider({ children }) {
  const [ids, setIds] = useLocalStorage("sdi:wishlist", []);
  const { token, clearToken } = useAuth();
  const synced = useRef(false);

  // On login: merge local + server wishlist, then push back the union.
  useEffect(() => {
    if (!token || synced.current) return;
    synced.current = true;
    api.syncWishlist(ids)
      .then((merged) => { if (Array.isArray(merged)) setIds(merged); })
      .catch((err) => {
        // Stale/invalid token -> clear it silently (stay in local-only mode).
        if (/unauthorized/i.test(err.message)) clearToken?.();
        else console.warn("[wishlist] sync failed:", err.message);
      });
  }, [token, ids, setIds, clearToken]);

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
