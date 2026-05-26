import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";
import { useAuth } from "../../context/AuthContext";

const BULK_THRESHOLD = 10000;
const STORAGE_KEY = "sdi:bulkQuotes";

const initialForm = () => ({
  name: "",
  phone: "",
  email: "",
  pincode: "",
  address: "",
  quantity: "",
  expectedPrice: "",
});

export default function BulkQuoteModal() {
  const { modal, closeModal, selectedProduct, showToast } = useUI();
  const { user } = useAuth();
  const open = modal === "bulk";
  const [form, setForm] = useState(initialForm);
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!open) return;
    document.body.style.overflow = "hidden";
    const onKey = (e) => e.key === "Escape" && closeModal();
    window.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = "";
      window.removeEventListener("keydown", onKey);
    };
  }, [open, closeModal]);

  useEffect(() => {
    if (open) {
      setForm((f) => ({
        ...f,
        name: f.name || user?.name || "",
        phone: f.phone || user?.phone || "",
        email: f.email || user?.email || "",
      }));
      setErrors({});
    }
  }, [open, user]);

  if (!open) return null;

  const setField = (k, v) => {
    setForm((f) => ({ ...f, [k]: v }));
    if (errors[k]) setErrors((e) => ({ ...e, [k]: undefined }));
  };

  const validate = () => {
    const e = {};
    if (!form.name.trim()) e.name = "Required";
    if (!/^\d{10}$/.test(form.phone.trim())) e.phone = "10-digit phone";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) e.email = "Invalid email";
    if (!/^\d{6}$/.test(form.pincode.trim())) e.pincode = "6-digit PIN";
    if (!form.address.trim()) e.address = "Required";
    if (!form.quantity || Number(form.quantity) <= 0) e.quantity = "Min 1";
    if (!form.expectedPrice || Number(form.expectedPrice) <= 0) e.expectedPrice = "Required";
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = (ev) => {
    ev.preventDefault();
    if (!validate()) return;
    setSubmitting(true);
    const payload = {
      id: `bq-${Date.now()}`,
      ts: Date.now(),
      productId: selectedProduct?.id || null,
      productName: selectedProduct?.name || null,
      ...form,
      quantity: Number(form.quantity),
      expectedPrice: Number(form.expectedPrice),
    };
    try {
      const prev = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
      localStorage.setItem(STORAGE_KEY, JSON.stringify([payload, ...prev]));
    } catch { /* ignore quota */ }

    setTimeout(() => {
      setSubmitting(false);
      showToast?.("Bulk quote request submitted. Our team will reach out soon.", "success");
      setForm(initialForm());
      closeModal();
    }, 450);
  };

  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-center justify-center p-3 sm:p-4"
      role="dialog"
      aria-modal="true"
      onClick={closeModal}
    >
      <div
        className="w-full max-w-[760px] bg-white rounded-xl shadow-2xl overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start justify-between px-5 sm:px-6 pt-5 pb-3 border-b border-gray-100">
          <div>
            <h3 className="text-base sm:text-lg font-semibold text-brand-ink leading-tight">
              Only for overall purchase values above ₹{BULK_THRESHOLD.toLocaleString("en-IN")}
            </h3>
            {selectedProduct?.name && (
              <p className="text-xs text-brand-muted mt-1">
                Product: <span className="font-semibold text-brand-ink">{selectedProduct.name}</span>
              </p>
            )}
          </div>
          <button
            onClick={closeModal}
            aria-label="Close"
            className="w-9 h-9 rounded-full hover:bg-red-50 flex items-center justify-center text-red-500 shrink-0 -mt-1"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 6l12 12M6 18L18 6" />
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="px-5 sm:px-6 py-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Field
              label="Name"
              required
              icon={<UserIcon />}
              placeholder="Enter your name"
              value={form.name}
              onChange={(v) => setField("name", v)}
              error={errors.name}
            />
            <Field
              label="Phone Number"
              required
              icon={<PhoneIcon />}
              placeholder="Enter your phone number"
              value={form.phone}
              onChange={(v) => setField("phone", v.replace(/\D/g, "").slice(0, 10))}
              error={errors.phone}
              inputMode="numeric"
            />
            <Field
              label="Email"
              required
              icon={<MailIcon />}
              placeholder="Enter your email"
              value={form.email}
              onChange={(v) => setField("email", v)}
              error={errors.email}
              type="email"
            />
            <Field
              label="Pincode"
              required
              icon={<PinIcon />}
              placeholder="Enter your pincode"
              value={form.pincode}
              onChange={(v) => setField("pincode", v.replace(/\D/g, "").slice(0, 6))}
              error={errors.pincode}
              inputMode="numeric"
            />
            <Field
              className="sm:col-span-2"
              label="Address"
              required
              icon={<PinIcon />}
              placeholder="Enter your address"
              value={form.address}
              onChange={(v) => setField("address", v)}
              error={errors.address}
            />
            <Field
              label="Quantity"
              required
              icon={<PlusIcon />}
              placeholder="Enter required quantity"
              value={form.quantity}
              onChange={(v) => setField("quantity", v.replace(/\D/g, ""))}
              error={errors.quantity}
              inputMode="numeric"
            />
            <Field
              label="Expected price"
              required
              icon={<RupeeIcon />}
              placeholder="Expected price per piece"
              value={form.expectedPrice}
              onChange={(v) => setField("expectedPrice", v.replace(/[^\d.]/g, ""))}
              error={errors.expectedPrice}
              inputMode="decimal"
            />
          </div>

          <button
            type="submit"
            disabled={submitting}
            className="mt-6 w-full bg-[#3684bf] hover:bg-[#1f5f96] disabled:opacity-60 text-white font-bold uppercase tracking-wide py-3 rounded-md transition"
          >
            {submitting ? "Submitting..." : "Submit"}
          </button>
        </form>
      </div>
    </div>,
    document.body
  );
}

function Field({ label, required, icon, placeholder, value, onChange, error, type = "text", inputMode, className = "" }) {
  return (
    <label className={`block ${className}`}>
      <span className="block text-xs text-brand-muted mb-1">
        {label}{required && " *"}
      </span>
      <div className={`flex items-center gap-2 border rounded-md px-3 py-2 bg-white transition ${error ? "border-red-400" : "border-gray-300 focus-within:border-[#3684bf]"}`}>
        <span className="text-gray-400 shrink-0">{icon}</span>
        <input
          type={type}
          inputMode={inputMode}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="flex-1 min-w-0 text-sm text-brand-ink placeholder:text-gray-400 focus:outline-none bg-transparent"
        />
      </div>
      {error && <p className="text-[11px] text-red-600 mt-1">{error}</p>}
    </label>
  );
}

const UserIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4" /></svg>
);
const PhoneIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02z" /></svg>
);
const MailIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5z" /></svg>
);
const PinIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7m0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5" /></svg>
);
const PlusIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z" /></svg>
);
const RupeeIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M13.66 7c-.45-.59-1.12-1-2.16-1H7V4h10v2h-3.26c.45.59.71 1.27.83 2H17v2h-2.43c-.34 2.32-2.14 3.79-4.4 4l4.42 5H12.5L8 14v-2h2.5c1.62 0 2.79-.8 3.04-2H7V8h6.66c-.13-.4-.3-.74-.5-1z" /></svg>
);
