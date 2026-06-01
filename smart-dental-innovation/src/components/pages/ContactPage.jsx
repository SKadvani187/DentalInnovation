import { useMemo, useState } from "react";
import { useUI } from "../../context/UIContext";

const PHONE_RAW = "919328762586";
const PHONE_DISPLAY = "+91 93287 62586";
const PHONE_SUPPORT = "+91 92653 18584";
const EMAIL_PRIMARY = "smartdentalinnovations.web@gmail.com";
const EMAIL_INFO = "info@smartdentalinnovations.com";
const ADDRESS_FULL = "Third floor, Swastik Plaza, 308, Savlia Cir, Yogi Chowk Ground, Chikuwadi, Varachha, Surat, Gujarat 395006";
const MAPS_QUERY = encodeURIComponent("Swastik Plaza, Yogi Chowk, Varachha, Surat, Gujarat 395006");

const DEPARTMENTS = [
  { id: "sales", label: "Sales Inquiry", icon: "💼", desc: "Bulk orders, demos, quotes" },
  { id: "support", label: "Product Support", icon: "🛠️", desc: "Warranty, repairs, returns" },
  { id: "partnership", label: "Partnerships", icon: "🤝", desc: "Distributors, resellers" },
  { id: "general", label: "General Query", icon: "💬", desc: "Anything else" },
];

const FAQS = [
  {
    q: "How fast do you respond?",
    a: "Sales & support inquiries: under 4 business hours (Mon–Sat, 10 AM–7 PM IST). General queries: within 24 hours.",
  },
  {
    q: "Do you ship pan-India?",
    a: "Yes — 5–7 business days to most pincodes. Free shipping above ₹20,000. COD available with verified pincode.",
  },
  {
    q: "Can I visit your office?",
    a: "Walk-ins welcome Mon–Sat, 10 AM–7 PM. We recommend booking via call to ensure product samples & demo handpieces are ready.",
  },
  {
    q: "How do bulk orders work?",
    a: "Use the Bulk Quote form on any product page (orders above ₹10,000). Our team replies with a custom quote within 24 hours.",
  },
];

export default function ContactPage() {
  const { navigate, showToast } = useUI();
  const [form, setForm] = useState({
    name: "",
    phone: "",
    email: "",
    department: "sales",
    description: "",
  });
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState("");
  const [openFaq, setOpenFaq] = useState(0);

  const onChange = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const onSubmit = (e) => {
    e.preventDefault();
    setError("");
    if (!form.name || !form.phone || !form.email || !form.description) {
      setError("Please fill in all required fields.");
      return;
    }
    if (!/^[6-9]\d{9}$/.test(form.phone)) {
      setError("Please enter a valid 10-digit Indian phone number.");
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      setError("Please enter a valid email address.");
      return;
    }
    try {
      const prev = JSON.parse(localStorage.getItem("sdi:contactSubmissions") || "[]");
      localStorage.setItem("sdi:contactSubmissions", JSON.stringify([{ ...form, ts: Date.now() }, ...prev]));
    } catch { /* ignore */ }
    setSubmitted(true);
    setForm({ name: "", phone: "", email: "", department: "sales", description: "" });
    showToast?.("Message sent. We'll be in touch soon!", "success");
  };

  return (
    <div className="bg-gradient-to-b from-[#eef5fb] via-white to-white">
      <Hero />

      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 -mt-12 relative z-10">
        <QuickActions />

        <nav className="flex items-center gap-2 text-sm text-brand-muted mb-6 mt-8">
          <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
          <span>/</span>
          <span className="text-brand-ink font-semibold">Contact Us</span>
        </nav>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
          <div className="lg:col-span-7">
            <FormCard
              form={form}
              setForm={setForm}
              onChange={onChange}
              onSubmit={onSubmit}
              submitted={submitted}
              error={error}
              onReset={() => setSubmitted(false)}
            />
          </div>

          <div className="lg:col-span-5 space-y-5">
            <ContactMethods />
            <BusinessHours />
          </div>
        </div>

        <OfficeMap />
        <FaqStrip faqs={FAQS} openFaq={openFaq} setOpenFaq={setOpenFaq} />
      </div>
    </div>
  );
}

function Hero() {
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-[#0b1d3a] via-[#173968] to-[#3684bf] text-white">
      <div className="absolute inset-0 opacity-20 pointer-events-none">
        <div className="absolute -top-20 -left-20 w-80 h-80 rounded-full bg-blue-300 blur-3xl" />
        <div className="absolute top-10 right-10 w-72 h-72 rounded-full bg-cyan-200 blur-3xl opacity-60" />
        <div className="absolute -bottom-20 left-1/3 w-96 h-96 rounded-full bg-white blur-3xl opacity-20" />
      </div>
      <div className="relative max-w-[1400px] mx-auto px-4 sm:px-6 pt-12 pb-24 text-center">
        <span className="inline-flex items-center gap-2 bg-white/15 backdrop-blur rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider mb-4">
          <span className="w-2 h-2 rounded-full bg-green-400 animate-pulse" />
          We're online now
        </span>
        <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">
          Let's talk about your clinic
        </h1>
        <p className="text-sm sm:text-base text-white/85 mt-3 max-w-2xl mx-auto leading-relaxed">
          Bulk orders, product demos, technical support, partnership — our team replies within 4 business hours. No bots.
        </p>
        <div className="flex flex-wrap items-center justify-center gap-2 mt-5 text-xs">
          {[
            { label: "Response: under 4 hrs", icon: "⚡" },
            { label: "Mon–Sat • 10 AM – 7 PM IST", icon: "🕐" },
            { label: "Trusted by 1000+ clinics", icon: "🦷" },
          ].map((b) => (
            <span key={b.label} className="bg-white/15 backdrop-blur border border-white/20 rounded-full px-3 py-1.5">
              {b.icon} {b.label}
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}

function QuickActions() {
  const actions = [
    {
      label: "Chat on WhatsApp",
      sub: "Instant reply",
      bg: "from-[#25D366] to-[#1ebe57]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.11.55 4.16 1.6 5.97L0 24l6.18-1.62A11.95 11.95 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.25-6.21-3.48-8.52zM12 22a9.9 9.9 0 01-5.05-1.38l-.36-.21-3.67.96.98-3.58-.23-.37A9.93 9.93 0 012 12c0-5.52 4.48-10 10-10s10 4.48 10 10-4.48 10-10 10z" /></svg>
      ),
      onClick: () => window.open(`https://wa.me/${PHONE_RAW}?text=${encodeURIComponent("Hi, I have a query about your dental products.")}`, "_blank"),
    },
    {
      label: "Call us",
      sub: PHONE_DISPLAY,
      bg: "from-[#3684bf] to-[#1f5f96]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" /></svg>
      ),
      onClick: () => { window.location.href = `tel:+${PHONE_RAW}`; },
    },
    {
      label: "Email us",
      sub: EMAIL_INFO,
      bg: "from-[#f97316] to-[#ea580c]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
      ),
      onClick: () => { window.location.href = `mailto:${EMAIL_INFO}`; },
    },
    {
      label: "Visit our office",
      sub: "Surat, Gujarat",
      bg: "from-[#ec4899] to-[#be185d]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" /></svg>
      ),
      onClick: () => window.open(`https://www.google.com/maps/search/?api=1&query=${MAPS_QUERY}`, "_blank"),
    },
  ];

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      {actions.map((a) => (
        <button
          key={a.label}
          onClick={a.onClick}
          className={`group bg-gradient-to-br ${a.bg} text-white rounded-2xl p-4 sm:p-5 text-left transition-all hover:-translate-y-1 hover:shadow-2xl active:scale-[0.98]`}
        >
          <div className="w-11 h-11 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center mb-3 transition group-hover:scale-110">
            {a.icon}
          </div>
          <p className="font-bold text-sm sm:text-base leading-tight">{a.label}</p>
          <p className="text-xs sm:text-sm text-white/85 mt-1 truncate">{a.sub}</p>
        </button>
      ))}
    </div>
  );
}

function FormCard({ form, setForm, onChange, onSubmit, submitted, error, onReset }) {
  const charCount = form.description.length;
  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-7 lg:p-8">
      <div className="flex items-start justify-between gap-3 mb-2">
        <div>
          <h2 className="text-2xl sm:text-3xl font-bold text-brand-ink">Send us a message</h2>
          <p className="text-sm text-brand-muted mt-1">
            Fill the form or <a href={`mailto:${EMAIL_INFO}`} className="text-[#3684bf] font-semibold hover:underline">email us directly</a>.
          </p>
        </div>
        <span className="hidden sm:inline-flex shrink-0 items-center gap-1.5 bg-green-50 border border-green-200 text-green-700 rounded-full px-2.5 py-1 text-[11px] font-bold">
          <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" />
          Replies in 4 hrs
        </span>
      </div>

      {submitted ? (
        <div className="mt-6 bg-green-50 border border-green-200 rounded-xl p-5 sm:p-6 text-center">
          <div className="w-14 h-14 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-3">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#16a34a"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
          </div>
          <h3 className="text-lg font-bold text-brand-ink mb-1">Message received!</h3>
          <p className="text-sm text-brand-muted mb-4">Our team will reply within 4 business hours. We've also sent a confirmation to your email.</p>
          <button
            onClick={onReset}
            className="text-sm font-semibold text-[#3684bf] hover:underline"
          >
            Send another message
          </button>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="space-y-5 mt-5">
          <div>
            <label className="block text-xs font-semibold text-brand-muted mb-2 uppercase tracking-wider">
              What can we help with?
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {DEPARTMENTS.map((d) => {
                const active = form.department === d.id;
                return (
                  <button
                    key={d.id}
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, department: d.id }))}
                    className={`text-left rounded-lg border-2 p-2.5 transition-all ${
                      active
                        ? "border-[#3684bf] bg-blue-50"
                        : "border-gray-200 hover:border-gray-300 bg-white"
                    }`}
                  >
                    <div className="text-lg mb-0.5">{d.icon}</div>
                    <div className={`text-xs font-bold leading-tight ${active ? "text-[#3684bf]" : "text-brand-ink"}`}>
                      {d.label}
                    </div>
                    <div className="hidden sm:block text-[10px] text-brand-muted leading-tight mt-0.5">{d.desc}</div>
                  </button>
                );
              })}
            </div>
          </div>

          <FloatField id="name" label="Full Name *" value={form.name} onChange={onChange("name")} />
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FloatField
              id="phone"
              label="Phone Number *"
              type="tel"
              inputMode="numeric"
              maxLength={10}
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value.replace(/\D/g, "").slice(0, 10) }))}
            />
            <FloatField id="email" label="Email *" type="email" value={form.email} onChange={onChange("email")} />
          </div>
          <div>
            <FloatField
              id="description"
              label="Your message *"
              value={form.description}
              onChange={onChange("description")}
              textarea
              rows={5}
              maxLength={500}
            />
            <div className="flex items-center justify-between mt-1 text-[11px] text-brand-muted">
              <span>Be specific — helps us reply faster</span>
              <span className={charCount > 450 ? "text-orange-600 font-semibold" : ""}>{charCount}/500</span>
            </div>
          </div>

          {error && (
            <div className="flex items-start gap-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" className="shrink-0 mt-0.5">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
              </svg>
              {error}
            </div>
          )}

          <button
            type="submit"
            className="w-full bg-gradient-to-r from-[#0b1d3a] to-[#3684bf] hover:opacity-95 text-white font-bold text-sm uppercase tracking-wider py-3.5 rounded-lg transition active:scale-[0.99] flex items-center justify-center gap-2 shadow-md"
          >
            Send Message
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" /></svg>
          </button>

          <p className="text-[11px] text-brand-muted text-center">
            By submitting, you agree to our <a className="underline">Privacy Policy</a>. We never share your data.
          </p>
        </form>
      )}
    </div>
  );
}

function ContactMethods() {
  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
      <h3 className="text-lg font-bold text-brand-ink mb-4">Reach us directly</h3>
      <ul className="space-y-4">
        <Method
          color="#16a34a"
          label="Sales"
          value={PHONE_DISPLAY}
          href={`tel:+${PHONE_RAW}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" /></svg>
          }
        />
        <Method
          color="#3684bf"
          label="Support"
          value={PHONE_SUPPORT}
          href={`tel:${PHONE_SUPPORT.replace(/\s/g, "")}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z" /></svg>
          }
        />
        <Method
          color="#f97316"
          label="Email Sales"
          value={EMAIL_PRIMARY}
          href={`mailto:${EMAIL_PRIMARY}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
          }
        />
        <Method
          color="#ec4899"
          label="General Info"
          value={EMAIL_INFO}
          href={`mailto:${EMAIL_INFO}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
          }
        />
      </ul>

      <div className="mt-5 pt-5 border-t border-gray-100">
        <p className="text-xs font-bold uppercase tracking-wider text-brand-muted mb-3">Follow us</p>
        <div className="flex items-center gap-2">
          {[
            { id: "facebook", color: "#1877F2", path: "M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z" },
            { id: "instagram", color: "#E4405F", path: "M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153.509.5.902 1.105 1.153 1.772.247.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 01-1.153 1.772c-.5.508-1.105.902-1.772 1.153-.637.247-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06-2.717 0-3.056-.01-4.122-.06-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 01-1.772-1.153 4.904 4.904 0 01-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.066.217-1.79.465-2.428a4.88 4.88 0 011.153-1.772A4.897 4.897 0 015.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm6.5-.25a1.25 1.25 0 10-2.5 0 1.25 1.25 0 002.5 0zM12 9a3 3 0 110 6 3 3 0 010-6z" },
            { id: "youtube", color: "#FF0000", path: "M21.582 6.186a2.506 2.506 0 00-1.768-1.768C18.254 4 12 4 12 4s-6.254 0-7.814.418A2.506 2.506 0 002.418 6.186C2 7.746 2 12 2 12s0 4.254.418 5.814a2.506 2.506 0 001.768 1.768C5.746 20 12 20 12 20s6.254 0 7.814-.418a2.506 2.506 0 001.768-1.768C22 16.254 22 12 22 12s0-4.254-.418-5.814zM10 15.464V8.536L16 12l-6 3.464z" },
            { id: "linkedin", color: "#0A66C2", path: "M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" },
          ].map((s) => (
            <a
              key={s.id}
              href="#"
              onClick={(e) => e.preventDefault()}
              aria-label={s.id}
              className="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition"
              style={{ color: s.color }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d={s.path} /></svg>
            </a>
          ))}
        </div>
      </div>
    </div>
  );
}

function Method({ icon, label, value, href, color }) {
  return (
    <li>
      <p className="text-[11px] font-bold uppercase tracking-wider text-brand-muted mb-1">{label}</p>
      <a href={href} className="flex items-center gap-2.5 group">
        <span
          className="w-8 h-8 rounded-lg flex items-center justify-center text-white shrink-0 transition group-hover:scale-110"
          style={{ backgroundColor: color }}
        >
          {icon}
        </span>
        <span className="text-sm text-brand-ink font-semibold break-all group-hover:text-[#3684bf] transition-colors">
          {value}
        </span>
      </a>
    </li>
  );
}

function BusinessHours() {
  const today = useMemo(() => {
    const d = new Date();
    const hr = d.getHours();
    const day = d.getDay(); // 0 Sun
    const open = day !== 0 && hr >= 10 && hr < 19;
    return { open, label: open ? "Open now" : "Closed" };
  }, []);

  const rows = [
    { day: "Monday – Friday", hours: "10:00 AM – 7:00 PM" },
    { day: "Saturday", hours: "10:00 AM – 7:00 PM" },
    { day: "Sunday", hours: "Closed" },
  ];

  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-bold text-brand-ink">Business hours</h3>
        <span className={`inline-flex items-center gap-1.5 text-[11px] font-bold rounded-full px-2.5 py-1 ${
          today.open ? "bg-green-50 text-green-700 border border-green-200" : "bg-red-50 text-red-700 border border-red-200"
        }`}>
          <span className={`w-1.5 h-1.5 rounded-full ${today.open ? "bg-green-500 animate-pulse" : "bg-red-500"}`} />
          {today.label}
        </span>
      </div>
      <ul className="space-y-2">
        {rows.map((r) => (
          <li key={r.day} className="flex items-center justify-between text-sm">
            <span className="text-brand-ink font-medium">{r.day}</span>
            <span className={`font-semibold ${r.hours === "Closed" ? "text-red-600" : "text-brand-ink"}`}>{r.hours}</span>
          </li>
        ))}
      </ul>
      <div className="mt-4 pt-4 border-t border-gray-100 text-xs text-brand-muted">
        🇮🇳 Timezone: India Standard Time (UTC+5:30)
      </div>
    </div>
  );
}

function OfficeMap() {
  return (
    <section className="mt-10">
      <div className="text-center mb-6">
        <span className="inline-block text-[11px] font-bold uppercase tracking-wider text-[#3684bf] bg-blue-50 border border-blue-100 rounded-full px-3 py-1 mb-2">
          Visit Us
        </span>
        <h2 className="text-2xl sm:text-3xl font-bold text-brand-ink">Our Office</h2>
        <p className="mt-1 text-sm text-brand-muted max-w-xl mx-auto">
          Walk-in welcome — see products in action, talk to our team, leave with a demo.
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div className="lg:col-span-7 relative aspect-[16/10] lg:aspect-auto bg-gradient-to-br from-blue-50 to-blue-100">
          <iframe
            src={`https://maps.google.com/maps?q=${MAPS_QUERY}&output=embed`}
            className="w-full h-full border-0"
            loading="lazy"
            title="Smart Dental Innovations office location"
          />
        </div>
        <div className="lg:col-span-5 p-5 sm:p-6 lg:p-8 flex flex-col">
          <div className="flex items-start gap-3 mb-4">
            <div className="w-11 h-11 rounded-lg bg-pink-50 flex items-center justify-center shrink-0">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="#ec4899"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" /></svg>
            </div>
            <div className="min-w-0">
              <p className="text-xs font-bold uppercase tracking-wider text-brand-muted">Address</p>
              <p className="text-sm font-semibold text-brand-ink leading-relaxed mt-1">{ADDRESS_FULL}</p>
            </div>
          </div>

          <ul className="space-y-2.5 text-sm text-brand-muted">
            <li className="flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="#3684bf"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z" /></svg>
              Near Yogi Chowk metro stop
            </li>
            <li className="flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="#3684bf"><path d="M14 17h-4v-1h4v1zm6.5-7l1.86 4H21v5c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2v-5h-1.36L3.5 10c.16-.55.66-.94 1.24-1H6V4c0-1.1.9-2 2-2h8c1.1 0 2 .9 2 2v5h1.26c.58.06 1.08.45 1.24 1zM10 7V5h4v2h-4z" /></svg>
              Free parking in basement
            </li>
            <li className="flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="#3684bf"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5 5H11v6l5.25 3.15.75-1.23-4.5-2.67z" /></svg>
              Book a slot via call to skip wait
            </li>
          </ul>

          <div className="mt-auto pt-5 flex flex-col sm:flex-row gap-2">
            <a
              href={`https://www.google.com/maps/dir/?api=1&destination=${MAPS_QUERY}`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex-1 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold text-sm py-2.5 rounded-lg text-center transition flex items-center justify-center gap-2"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21.71 11.29l-9-9a.996.996 0 00-1.41 0l-9 9a.996.996 0 000 1.41l9 9c.39.39 1.02.39 1.41 0l9-9a.996.996 0 000-1.41zM14 14.5V12h-4v3H8v-4c0-.55.45-1 1-1h5V7.5l3.5 3.5-3.5 3.5z" /></svg>
              Get directions
            </a>
            <a
              href={`tel:+${PHONE_RAW}`}
              className="flex-1 border border-gray-300 hover:border-[#3684bf] hover:text-[#3684bf] text-brand-ink font-semibold text-sm py-2.5 rounded-lg text-center transition flex items-center justify-center gap-2"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" /></svg>
              Call to book
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}

function FaqStrip({ faqs, openFaq, setOpenFaq }) {
  return (
    <section className="mt-10">
      <div className="text-center mb-6">
        <span className="inline-block text-[11px] font-bold uppercase tracking-wider text-[#3684bf] bg-blue-50 border border-blue-100 rounded-full px-3 py-1 mb-2">
          Quick Answers
        </span>
        <h2 className="text-2xl sm:text-3xl font-bold text-brand-ink">Common questions</h2>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        {faqs.map((f, i) => {
          const open = openFaq === i;
          return (
            <button
              key={i}
              onClick={() => setOpenFaq(open ? -1 : i)}
              className={`text-left bg-white border rounded-xl p-4 sm:p-5 transition ${
                open ? "border-[#3684bf] shadow-md" : "border-gray-100 hover:border-gray-200"
              }`}
            >
              <div className="flex items-start justify-between gap-3">
                <p className="font-bold text-brand-ink text-sm sm:text-base">{f.q}</p>
                <svg
                  className={`shrink-0 h-5 w-5 text-brand-muted transition-transform ${open ? "rotate-180" : ""}`}
                  viewBox="0 0 24 24"
                  fill="currentColor"
                >
                  <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
                </svg>
              </div>
              {open && (
                <p className="text-sm text-brand-muted mt-2 leading-relaxed">{f.a}</p>
              )}
            </button>
          );
        })}
      </div>
    </section>
  );
}

function FloatField({ id, label, value, onChange, type = "text", textarea, rows, ...rest }) {
  const filled = String(value || "").length > 0;
  return (
    <div className="relative">
      <label
        htmlFor={id}
        className={`absolute left-3 transition-all pointer-events-none px-1 bg-white ${
          filled ? "-top-2 text-[11px] text-[#3684bf] font-semibold" : "top-3.5 text-sm text-brand-muted"
        }`}
      >
        {label}
      </label>
      {textarea ? (
        <textarea
          id={id}
          rows={rows || 4}
          value={value}
          onChange={onChange}
          className="w-full border border-gray-300 rounded-md px-3 pt-4 pb-2 text-sm focus:outline-none focus:border-[#3684bf] focus:ring-1 focus:ring-[#3684bf]/30 transition resize-none"
          {...rest}
        />
      ) : (
        <input
          id={id}
          type={type}
          value={value}
          onChange={onChange}
          className="w-full border border-gray-300 rounded-md px-3 py-3.5 text-sm focus:outline-none focus:border-[#3684bf] focus:ring-1 focus:ring-[#3684bf]/30 transition"
          {...rest}
        />
      )}
    </div>
  );
}
