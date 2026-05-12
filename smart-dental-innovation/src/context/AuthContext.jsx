import { createContext, useContext, useMemo, useCallback } from "react";
import { useLocalStorage } from "../hooks/useLocalStorage";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useLocalStorage("sdi:user", null);
  const [accounts, setAccounts] = useLocalStorage("sdi:accounts", []);

  const signup = useCallback(({ name, email, password }) => {
    if (!name || !email || !password) {
      return { ok: false, error: "All fields are required." };
    }
    if (accounts.some((a) => a.email === email)) {
      return { ok: false, error: "Account with this email already exists." };
    }
    const newAccount = { name, email, password };
    setAccounts([...accounts, newAccount]);
    setUser({ name, email });
    return { ok: true };
  }, [accounts, setAccounts, setUser]);

  const login = useCallback(({ email, password }) => {
    const acc = accounts.find((a) => a.email === email && a.password === password);
    if (!acc) return { ok: false, error: "Invalid email or password." };
    setUser({ name: acc.name, email: acc.email });
    return { ok: true };
  }, [accounts, setUser]);

  const logout = useCallback(() => setUser(null), [setUser]);

  const value = useMemo(
    () => ({ user, signup, login, logout }),
    [user, signup, login, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
};
