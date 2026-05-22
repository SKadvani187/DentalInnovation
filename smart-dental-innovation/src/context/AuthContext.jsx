import { createContext, useContext, useMemo, useCallback, useRef } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";

const AuthContext = createContext(null);

const OTP_TTL_MS = 5 * 60 * 1000;

function genOtp() {
  return String(Math.floor(100000 + Math.random() * 900000));
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

  const verifyOtp = useCallback(({ mobile, otp, name }) => {
    const entry = otpStore.current.get(mobile);
    if (!entry) return { ok: false, error: "Request a new OTP." };
    if (Date.now() > entry.expiresAt) {
      otpStore.current.delete(mobile);
      return { ok: false, error: "OTP expired. Request a new one." };
    }
    if (entry.otp !== otp) return { ok: false, error: "Invalid OTP." };
    otpStore.current.delete(mobile);

    let acc = accounts.find((a) => a.mobile === mobile);
    if (!acc) {
      acc = { mobile, name: name || `User ${mobile.slice(-4)}` };
      setAccounts([...accounts, acc]);
    } else if (name && name !== acc.name) {
      acc = { ...acc, name };
      setAccounts(accounts.map((a) => (a.mobile === mobile ? acc : a)));
    }
    setUser({ name: acc.name, mobile: acc.mobile });
    return { ok: true };
  }, [accounts, setAccounts, setUser]);

  const logout = useCallback(() => setUser(null), [setUser]);

  const value = useMemo(
    () => ({ user, requestOtp, verifyOtp, logout }),
    [user, requestOtp, verifyOtp, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
};
