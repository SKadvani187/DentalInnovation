import { useState, useEffect } from "react";
import { useLocation } from "react-router-dom";
import { useSettings } from "../../context/SettingsContext";
import { nameFromPath } from "../../lib/routes";

const DEFAULT_MSG = "Hi, I'm interested in your dental products. Can you help me?";
const HIDDEN_VIEWS = new Set(["product"]);

export default function WhatsAppFab() {
  const { pathname } = useLocation();
  const { company = {}, branding = {} } = useSettings();
  // Admin-configured WhatsApp number (Settings → General → Logos & WhatsApp) takes
  // priority; falls back to company sales/main phone, then a last-resort default.
  const PHONE = (branding.whatsappNumber || company.phoneSales || company.phone || "919328762586").replace(/\D/g, "");
  const [showTip, setShowTip] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setShowTip(true), 2500);
    const h = setTimeout(() => setShowTip(false), 9000);
    return () => { clearTimeout(t); clearTimeout(h); };
  }, []);

  if (HIDDEN_VIEWS.has(nameFromPath(pathname))) return null;

  const open = () => {
    const url = `https://wa.me/${PHONE}?text=${encodeURIComponent(DEFAULT_MSG)}`;
    window.open(url, "_blank", "noopener,noreferrer");
  };

  return (
    <div className="fixed bottom-5 right-5 z-[900] flex items-end gap-2">
      {showTip && (
        <div
          onClick={() => setShowTip(false)}
          className="hidden sm:flex items-center gap-2 bg-white shadow-lg rounded-full pl-4 pr-3 py-2 text-sm font-semibold text-brand-ink border border-gray-100 cursor-pointer animate-[fadeIn_300ms_ease]"
        >
          Need help? Chat now
          <button
            onClick={(e) => { e.stopPropagation(); setShowTip(false); }}
            aria-label="Dismiss"
            className="w-5 h-5 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center"
          >
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
              <path d="M6 6l12 12M6 18L18 6" />
            </svg>
          </button>
        </div>
      )}
      <button
        onClick={open}
        aria-label="Chat on WhatsApp"
        className="relative w-14 h-14 rounded-full bg-[#25D366] hover:bg-[#1ebe57] shadow-lg flex items-center justify-center text-white transition active:scale-95"
      >
        <span className="absolute inset-0 rounded-full bg-[#25D366] opacity-60 animate-ping" />
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" className="relative">
          <path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.11.55 4.16 1.6 5.97L0 24l6.18-1.62A11.95 11.95 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.25-6.21-3.48-8.52zM12 22a9.9 9.9 0 01-5.05-1.38l-.36-.21-3.67.96.98-3.58-.23-.37A9.93 9.93 0 012 12c0-5.52 4.48-10 10-10s10 4.48 10 10-4.48 10-10 10zm5.42-7.46c-.3-.15-1.76-.87-2.03-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.75-1.65-2.05-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.87 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z" />
        </svg>
      </button>
    </div>
  );
}
