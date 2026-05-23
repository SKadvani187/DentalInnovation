import { createContext, useContext, useMemo, useCallback, useRef } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";

const AuthContext = createContext(null);

const OTP_TTL_MS = 5 * 60 * 1000;

function genOtp() {
  return String(Math.floor(1000 + Math.random() * 9000));
}

export function AuthProvider({ children }) {
  const [user, setUser] = useLocalStorage("sdi:user", null);
  const [accounts, setAccounts] = useLocalStorage("sdi:accounts", []);
  const otpStore = useRef(new Map());

  const requestOtp = useCallback((mobile) => {
    if (!/^[6-9]\d{9}$/.test(mobile)) {
      return { ok: false, error: "Enter a valid 10-digit mobile number." };
    }
    const otp = genOtp();
    otpStore.current.set(mobile, { otp, expiresAt: Date.now() + OTP_TTL_MS });
    // Demo: surface OTP via console + return so UI can show toast in dev.
    // In real flow, SMS gateway would send it.
    console.info(`[Demo OTP] ${mobile} -> ${otp}`);
    return { ok: true, demoOtp: otp };
  }, []);

  const verifyOtp = useCallback(({ mobile, otp }) => {
    const entry = otpStore.current.get(mobile);
    if (!entry) return { ok: false, error: "Request a new OTP." };
    if (Date.now() > entry.expiresAt) {
      otpStore.current.delete(mobile);
      return { ok: false, error: "OTP expired. Request a new one." };
    }
    if (entry.otp !== otp) return { ok: false, error: "Invalid OTP." };
    otpStore.current.delete(mobile);

    const existing = accounts.find((a) => a.mobile === mobile);
    if (existing) {
      setUser({ ...existing });
      return { ok: true, isNew: false };
    }
    return { ok: true, isNew: true, mobile };
  }, [accounts, setUser]);

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
    return { ok: true };
  }, [accounts, setAccounts, setUser]);

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

  const logout = useCallback(() => setUser(null), [setUser]);

  const value = useMemo(
    () => ({ user, requestOtp, verifyOtp, completeProfile, updateProfile, addAddress, removeAddress, logout }),
    [user, requestOtp, verifyOtp, completeProfile, updateProfile, addAddress, removeAddress, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
};
