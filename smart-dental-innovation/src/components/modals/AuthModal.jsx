import { useState } from "react";
import Modal from "../ui/Modal";
import Button from "../ui/Button";
import { useUI } from "../../context/UIContext";
import { useAuth } from "../../context/AuthContext";

export default function AuthModal() {
  const { modal, closeModal } = useUI();
  const { login, signup } = useAuth();
  const [tab, setTab] = useState("login");
  const [form, setForm] = useState({ name: "", email: "", password: "" });
  const [error, setError] = useState("");

  if (modal !== "auth") return null;

  const onChange = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = (e) => {
    e.preventDefault();
    setError("");
    const result = tab === "login"
      ? login({ email: form.email, password: form.password })
      : signup(form);
    if (!result.ok) {
      setError(result.error);
      return;
    }
    setForm({ name: "", email: "", password: "" });
    closeModal();
  };

  return (
    <Modal open={true} onClose={closeModal} maxWidth="max-w-md">
      <div className="p-5 sm:p-6">
        <div className="flex border-b border-gray-200 mb-5">
          {["login", "signup"].map((t) => (
            <button
              key={t}
              onClick={() => { setTab(t); setError(""); }}
              className={`flex-1 py-3 text-sm font-bold uppercase tracking-wide ${tab === t ? "text-brand-navy border-b-2 border-brand-navy" : "text-brand-muted"}`}
            >
              {t === "login" ? "Sign In" : "Create Account"}
            </button>
          ))}
        </div>

        <form onSubmit={submit} className="space-y-3">
          {tab === "signup" && (
            <input
              required
              type="text"
              placeholder="Full Name"
              value={form.name}
              onChange={onChange("name")}
              className="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:border-brand-navy"
            />
          )}
          <input
            required
            type="email"
            placeholder="Email Address"
            value={form.email}
            onChange={onChange("email")}
            className="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:border-brand-navy"
          />
          <input
            required
            type="password"
            placeholder="Password"
            value={form.password}
            onChange={onChange("password")}
            className="w-full border border-gray-300 rounded-md px-3 py-2.5 text-sm focus:outline-none focus:border-brand-navy"
          />

          {error && <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{error}</p>}

          <Button type="submit" variant="primary" size="lg" className="w-full">
            {tab === "login" ? "Sign In" : "Create Account"}
          </Button>
        </form>

        <p className="mt-4 text-center text-[11px] text-brand-muted">
          By continuing you accept the Terms of Use & Privacy Policy. (Demo auth — credentials stored locally.)
        </p>
      </div>
    </Modal>
  );
}
