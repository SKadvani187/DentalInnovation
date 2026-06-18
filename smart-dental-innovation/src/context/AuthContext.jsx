import { createContext, useContext, useMemo, useCallback, useEffect } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";
import api, { setAuthToken } from "../lib/api";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useLocalStorage("sdi:user", null);
  const [accounts, setAccounts] = useLocalStorage("sdi:accounts", []);
  const [token, setToken] = useLocalStorage("sdi:token", null);

  // Restore token into the API client on load.
  useEffect(() => { setAuthToken(token); }, [token]);

  // NOTE: a previous "self-heal" effect silently re-acquired a token via api.login()
  // (find-or-create) whenever a profile existed without a token. The backend now requires
  // a server-verified OTP before issuing a token (auth.php), so that path is both blocked
  // and a security hole if it weren't — removed. A token-less session re-authenticates
  // through the normal OTP flow instead.

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
    // Verified — persist customer + token (backend find-or-create).
    const existing = accounts.find((a) => a.mobile === mobile);
    const res = await syncToApi(mobile, existing ? { name: existing.name, email: existing.email } : {});

    // Backend is authoritative: if it returns an existing customer with a real name,
    // log them straight in — even if this browser has no local account (incognito,
    // cleared storage, different device). This is the main "OTP ok but not logged in" fix.
    const apiCust = res?.customer;
    const apiHasProfile = apiCust && res?.isNew === false && apiCust.name && apiCust.name.trim() !== "";
    if (apiHasProfile) {
      const merged = {
        mobile,
        name: apiCust.name,
        email: apiCust.email || "",
        address: apiCust.address || "",
        addresses: apiCust.addresses || [],
        city: apiCust.city || "",
        state: apiCust.state || "",
        pincode: apiCust.pincode || "",
        clinicName: apiCust.clinicName || "",
      };
      setUser(merged);
      // Cache locally so future logins on this browser are instant.
      setAccounts((prev) => {
        const without = prev.filter((a) => a.mobile !== mobile);
        return [...without, { mobile, name: merged.name, email: merged.email, address: merged.address }];
      });
      return { ok: true, isNew: false };
    }

    // Local account known (offline / API down) — log in from it.
    if (existing) {
      setUser({ ...existing });
      return { ok: true, isNew: false };
    }

    // Genuinely new (no profile yet): ask for name to complete profile.
    return { ok: true, isNew: true, mobile };
  }, [accounts, setUser, setAccounts, syncToApi]);

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
    // Persist the real name via action=profile (Bearer token from the login that ran during
    // OTP verify). NOT api.login() — the OTP was already consumed by that first login, so a
    // second login here would fail OTP verification and the name would never reach the DB,
    // leaving the placeholder "Customer XXXX" in the admin list.
    api.updateProfile({ name: acc.name, email: acc.email }).catch((err) =>
      console.warn("[auth] completeProfile persist failed:", err.message)
    );
    return { ok: true };
  }, [accounts, setAccounts, setUser]);

  // Best-effort backend persistence of the address book. Addresses live in
  // customers.addresses (JSON) via auth.php?action=profile, so they survive re-login /
  // a cleared browser. Fire-and-forget: local state is already updated; if the API is
  // down we stay in offline mode (the next login re-sync reconciles).
  const persistProfile = useCallback((updates) => {
    if (!token) return;
    api.updateProfile(updates).catch((err) => console.warn("[auth] profile persist failed:", err.message));
  }, [token]);

  const updateProfile = useCallback((updates) => {
    if (!user) return { ok: false };
    const next = { ...user, ...updates };
    setUser(next);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? { ...a, ...next } : a)));
    persistProfile(updates);
    return { ok: true };
  }, [user, accounts, setAccounts, setUser, persistProfile]);

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
    persistProfile({ addresses: next });
    return { ok: true, id };
  }, [user, accounts, setAccounts, setUser, persistProfile]);

  // Replace an existing address (edit) by id, optionally promoting it to default.
  const updateAddress = useCallback((id, patch) => {
    if (!user) return { ok: false };
    let next = (user.addresses || []).map((a) => (a.id === id ? { ...a, ...patch } : a));
    if (patch.isDefault) next = next.map((a) => ({ ...a, isDefault: a.id === id }));
    const updated = { ...user, addresses: next };
    setUser(updated);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? updated : a)));
    persistProfile({ addresses: next });
    return { ok: true };
  }, [user, accounts, setAccounts, setUser, persistProfile]);

  // Promote one address to default (clears the flag on the rest).
  const setDefaultAddress = useCallback((id) => {
    if (!user) return { ok: false };
    const next = (user.addresses || []).map((a) => ({ ...a, isDefault: a.id === id }));
    const updated = { ...user, addresses: next };
    setUser(updated);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? updated : a)));
    persistProfile({ addresses: next });
    return { ok: true };
  }, [user, accounts, setAccounts, setUser, persistProfile]);

  const removeAddress = useCallback((id) => {
    if (!user) return { ok: false };
    const next = (user.addresses || []).filter((a) => a.id !== id);
    const updated = { ...user, addresses: next };
    setUser(updated);
    setAccounts(accounts.map((a) => (a.mobile === user.mobile ? updated : a)));
    persistProfile({ addresses: next });
    return { ok: true };
  }, [user, accounts, setAccounts, setUser, persistProfile]);

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
    () => ({ user, token, requestOtp, verifyOtp, completeProfile, updateProfile, addAddress, updateAddress, setDefaultAddress, removeAddress, logout, clearToken }),
    [user, token, requestOtp, verifyOtp, completeProfile, updateProfile, addAddress, updateAddress, setDefaultAddress, removeAddress, logout, clearToken]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
};
