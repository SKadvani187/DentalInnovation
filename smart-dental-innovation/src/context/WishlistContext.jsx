import { createContext, useContext, useMemo, useCallback, useEffect, useRef } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import api from "../lib/api";
import { useAuth } from "./AuthContext";

const WishlistContext = createContext(null);

export function WishlistProvider({ children }) {
  const [ids, setIds] = useLocalStorage("sdi:wishlist", []);
  const { token, sessionExpired } = useAuth();
  const synced = useRef(false);

  // On login: merge local + server wishlist, then push back the union.
  useEffect(() => {
    if (!token || synced.current) return;
    synced.current = true;
    api.syncWishlist(ids)
      .then((merged) => { if (Array.isArray(merged)) setIds(merged); })
      .catch((err) => {
        // A 401 means the server has rejected this token outright — the session is dead, and
        // pretending otherwise leaves the header greeting someone who cannot actually order.
        // `token &&` guards the earlier failure mode where a sync fired before the token was
        // set, produced a spurious 401, and logged people out mid-session.
        if (err.status === 401 && token) { sessionExpired(); return; }
        // Anything else (offline, server hiccup) must NOT log the user out — stay local-only.
        console.warn("[wishlist] sync failed:", err.message);
      });
  }, [token, ids, setIds, sessionExpired]);

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
    setIds((prev) => {
      const next = prev.filter((x) => x !== id);
      // Persist the removal to the server too — otherwise the item reappears after
      // reload/re-login because the login merge re-adds the still-saved server copy.
      if (token) api.syncWishlist(next).catch(() => {});
      return next;
    });
  }, [setIds, token]);

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
