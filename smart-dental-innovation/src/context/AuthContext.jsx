import { createContext, useContext, useMemo, useCallback, useRef, useEffect } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import api, { setAuthToken } from "../lib/api";

const AuthContext = createContext(null);

const OTP_TTL_MS = 5 * 60 * 1000;

function genOtp() {
  return String(Math.floor(1000 + Math.random() * 9000));
}

export function AuthProvider({ children }) {
  const [user, setUser] = useLocalStorage("sdi:user", null);
  const [accounts, setAccounts] = useLocalStorage("sdi:accounts", []);
  const [token, setToken] = useLocalStorage("sdi:token", null);
  const otpStore = useRef(new Map());

  // Restore token into the API client on load.
  useEffect(() => { setAuthToken(token); }, [token]);

  // Self-heal: if we have a user profile but no API token (e.g. the original login
  // ran while the backend was unreachable, leaving a token-less session), silently
  // re-acquire a token via find-or-create. Backend keys customers by mobile.
  // One-shot per session so a downstream 401 (which clears the token) can't loop this.
  const reSyncTried = useRef(false);
  useEffect(() => {
    const mobile = user?.mobile || user?.phone;
    if (!mobile || token || reSyncTried.current) return;
    reSyncTried.current = true;
    api.login({ mobile, name: user?.name, email: user?.email })
      .then((res) => {
        if (res?.token) { setToken(res.token); setAuthToken(res.token); }
      })
      .catch((err) => console.warn("[auth] token re-sync failed:", err.message));
  }, [user, token, setToken]);

  // Persist the customer to the backend (find-or-create) and capture the API token.
  const syncToApi = useCallback(async (mobile, profile = {}) => {
    try {
      const res = await api.login({ mobile, ...profile });
      setToken(res.token);
      setAuthToken(res.token);
      return res;
    } catch (err) {
      console.warn("[auth] API sync failed (offline mode):", err.message);
      return null;
    }
  }, [setToken]);

  // Request OTP via backend (real SMS/email + rate limiting).
  const requestOtp = useCallback(async (mobile) => {
    if (!/^[6-9]\d{9}$/.test(mobile)) {
      return { ok: false, error: "Enter a valid 10-digit mobile number." };
    }
    try {
      const res = await api.requestOtp({ mobile });
      return { ok: true, devOtp: res.devOtp || null, sent: res.sent, devMode: res.devMode, attemptsLeft: res.attemptsLeft, message: res.message };
    } catch (err) {
      return { ok: false, error: err.message || "Could not send OTP." };
    }
  }, []);

  // Verify OTP via backend. On success: log in (find-or-create) and capture token.
  const verifyOtp = useCallback(async ({ mobile, otp }) => {
    try {
      await api.verifyOtp({ mobile, otp });
    } catch (err) {
      return { ok: false, error: err.message || "Invalid OTP." };
    }
    // Verified — persist customer + token.
    const existing = accounts.find((a) => a.mobile === mobile);
    const res = await syncToApi(mobile, existing ? { name: existing.name, email: existing.email } : {});
    if (existing) {
      setUser({ ...existing });
      return { ok: true, isNew: false };
    }
    // New customer: backend created a record; ask for name to complete profile.
    return { ok: true, isNew: !res || res.isNew !== false, mobile };
  }, [accounts, setUser, syncToApi]);

  const completeProfile = useCallback(({ mobile, name, email, address }) => {
    if (!name?.trim()) return { ok: false, error: "Enter your name." };
    const acc = { mobile, name: name.trim(), email: email || "", address: address || "" };
    const exists = accounts.find((a) => a.mobile === mobile);
    if (exists) {
      setAccounts(accounts.map((a) => (a.mobile === mobile ? { ...a, ...acc } : a)));
    } else {
      setAccounts([...accounts, acc]);
    }
    setUser(acc);
    syncToApi(mobile, { name: acc.name, email: acc.email });
    return { ok: true };
  }, [accounts, setAccounts, setUser, syncToApi]);

  const updateProfile = useCallback((updates) => {
    if (!user) return { ok: false };
    const next = { ...user, ...updates };
    setUser(next);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? { ...a, ...next } : a)));
    return { ok: true };
  }, [user, accounts, setAccounts, setUser]);

  const addAddress = useCallback((addr) => {
    if (!user) return { ok: false };
    const list = user.addresses || [];
    const id = `addr-${Date.now()}`;
    let next = [...list, { id, ...addr }];
    if (addr.isDefault) {
      next = next.map((a) => ({ ...a, isDefault: a.id === id }));
    }
    const updated = { ...user, addresses: next };
    setUser(updated);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? updated : a)));
    return { ok: true, id };
  }, [user, accounts, setAccounts, setUser]);

  const removeAddress = useCallback((id) => {
    if (!user) return { ok: false };
    const next = (user.addresses || []).filter((a) => a.id !== id);
    const updated = { ...user, addresses: next };
    setUser(updated);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? updated : a)));
    return { ok: true };
  }, [user, accounts, setAccounts, setUser]);

  const logout = useCallback(() => {
    setUser(null);
    setToken(null);
    setAuthToken(null);
  }, [setUser, setToken]);

  // Clear only a stale/invalid token (e.g. after a 401), without wiping the user profile.
  const clearToken = useCallback(() => {
    setToken(null);
    setAuthToken(null);
  }, [setToken]);

  const value = useMemo(
    () => ({ user, token, requestOtp, verifyOtp, completeProfile, updateProfile, addAddress, removeAddress, logout, clearToken }),
    [user, token, requestOtp, verifyOtp, completeProfile, updateProfile, addAddress, removeAddress, logout, clearToken]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
};
