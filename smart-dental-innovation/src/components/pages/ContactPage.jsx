import { useMemo, useState } from "react";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useSettings } from "../../context/SettingsContext";
import api from "../../lib/api";

// Contact info derived from company settings (with fallbacks). Used across sub-components.
function useContactInfo() {
  const { company = {} } = useSettings();
  return {
    PHONE_DISPLAY: company.phoneSales || "+91 93287 62586",
    PHONE_SUPPORT: company.phone || "+91 92653 18584",
    PHONE_RAW: (company.phoneSales || "919328762586").replace(/\D/g, ""),
    EMAIL_PRIMARY: company.emailSales || "smartdentalinnovations.web@gmail.com",
    EMAIL_INFO: company.email || "info@smartdentalinnovations.com",
    ADDRESS_FULL: company.address || "Third floor, Swastik Plaza, Varachha, Surat, Gujarat 395006",
    MAPS_QUERY: encodeURIComponent(company.addressShort || company.address || "Swastik Plaza, Yogi Chowk, Varachha, Surat, Gujarat 395006"),
    CITY_LABEL: [company.city, company.state].filter(Boolean).join(", ") || "Surat, Gujarat",
  };
}

// Contact page config from settings (FAQs, departments, hours) with fallbacks.
function useContactConfig() {
  const { contactConfig = {} } = useSettings();
  return {
    DEPARTMENTS: contactConfig.departments?.length ? contactConfig.departments : [
      { id: "sales", label: "Sales Inquiry", icon: "💼", desc: "Bulk orders, demos, quotes" },
      { id: "general", label: "General Query", icon: "💬", desc: "Anything else" },
    ],
    FAQS: contactConfig.faqs?.length ? contactConfig.faqs : [],
    BUSINESS_HOURS: contactConfig.businessHours?.length ? contactConfig.businessHours : [
      { day: "Monday – Saturday", hours: "10:00 AM – 7:00 PM" },
      { day: "Sunday", hours: "Closed" },
    ],
    RESPONSE_NOTE: contactConfig.responseNote || "our team replies within 4 business hours. No bots.",
    TIMEZONE: contactConfig.timezone || "India Standard Time (UTC+5:30)",
    OPEN_HOURS: {
      openHour:  contactConfig.openHours?.openHour  ?? 10,   // 24h
      closeHour: contactConfig.openHours?.closeHour ?? 19,
      openDays:  contactConfig.openHours?.openDays  ?? [1, 2, 3, 4, 5, 6], // 0=Sun
      openLabel: contactConfig.openHours?.openLabel  || "Open now",
      closedLabel: contactConfig.openHours?.closedLabel || "Closed",
    },
    HERO_BADGE: contactConfig.heroBadge || "We're online now",
    HERO_BADGE_CLOSED: contactConfig.heroBadgeClosed || "We're currently offline",
    HERO_TITLE: contactConfig.heroTitle || "Let's talk about your clinic",
    HERO_SUBTITLE: contactConfig.heroSubtitle || "Bulk orders, product demos, technical support, partnership — our team replies within 4 business hours. No bots.",
    FORM_TITLE: contactConfig.formTitle || "Send us a message",
    FORM_CHIP: contactConfig.formChip || "Replies in 4 hrs",
    STAT_CHIPS: contactConfig.statChips?.length ? contactConfig.statChips : [
      { icon: "⚡", label: "Response: under 4 hrs" },
      { icon: "🦷", label: "Trusted clinics" },
    ],
    OFFICE_SUBTITLE: contactConfig.officeSubtitle || "Walk-in welcome — see products in action, talk to our team, leave with a demo.",
    OFFICE_BULLETS: contactConfig.officeBullets?.length ? contactConfig.officeBullets : [
      "Near Yogi Chowk metro stop",
      "Free parking in basement",
      "Book a slot via call to skip wait",
    ],
    LABELS: {
      whatsapp:    contactConfig.labels?.whatsapp    || "Chat on WhatsApp",
      whatsappSub: contactConfig.labels?.whatsappSub || "Instant reply",
      call:        contactConfig.labels?.call        || "Call us",
      email:       contactConfig.labels?.email       || "Email us",
      visit:       contactConfig.labels?.visit       || "Visit our office",
      reachHeading:contactConfig.labels?.reachHeading|| "Reach us directly",
      faqHeading:  contactConfig.labels?.faqHeading  || "Common questions",
      successTitle:contactConfig.labels?.successTitle|| "Message received!",
      formSubtitle:contactConfig.labels?.formSubtitle|| "Fill the form or email us directly.",
      msgHint:     contactConfig.labels?.msgHint     || "Be specific — helps us reply faster",
      sendBtn:     contactConfig.labels?.sendBtn     || "Send Message",
      deptHelp:    contactConfig.labels?.deptHelp    || "What can we help with?",
      fieldName:   contactConfig.labels?.fieldName   || "Full Name *",
      fieldPhone:  contactConfig.labels?.fieldPhone  || "Phone Number *",
      fieldEmail:  contactConfig.labels?.fieldEmail  || "Email *",
      fieldMsg:    contactConfig.labels?.fieldMsg    || "Your message *",
      visitBadge:  contactConfig.labels?.visitBadge  || "Visit Us",
      officeHeading:contactConfig.labels?.officeHeading || "Our Office",
      reachSales:  contactConfig.labels?.reachSales  || "Sales",
      reachSupport:contactConfig.labels?.reachSupport|| "Support",
      reachEmailSales:contactConfig.labels?.reachEmailSales || "Email Sales",
      reachGeneral:contactConfig.labels?.reachGeneral|| "General Info",
      privacyNote: contactConfig.labels?.privacyNote || "By submitting, you agree to our Privacy Policy. We never share your data.",
      followHeading: contactConfig.labels?.followHeading || "Follow us",
      hoursHeading: contactConfig.labels?.hoursHeading || "Business hours",
    },
  };
}

// Live open/closed status from configured business hours.
function useOpenStatus() {
  const { OPEN_HOURS } = useContactConfig();
  return useMemo(() => {
    const d = new Date();
    const open = OPEN_HOURS.openDays.includes(d.getDay()) && d.getHours() >= OPEN_HOURS.openHour && d.getHours() < OPEN_HOURS.closeHour;
    return { open, label: open ? OPEN_HOURS.openLabel : OPEN_HOURS.closedLabel };
  }, [OPEN_HOURS]);
}

export default function ContactPage() {
  const { showToast } = useUI();
  const navigate = useAppNavigate();
  const { FAQS } = useContactConfig();
  const { contactSections = [] } = useSettings();
  // Admin show/hide: section is visible unless explicitly disabled.
  const disabled = new Set((contactSections || []).filter((s) => s.enabled === false).map((s) => s.key));
  const show = (key) => !disabled.has(key);
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

  const onSubmit = async (e) => {
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
      await api.contact({
        name: form.name, phone: form.phone, email: form.email,
        department: form.department, message: form.description,
      });
    } catch (err) {
      // Fallback: keep locally if API is unavailable.
      console.warn("[contact] API failed, saved locally:", err.message);
      try {
        const prev = JSON.parse(localStorage.getItem("sdi:contactSubmissions") || "[]");
        localStorage.setItem("sdi:contactSubmissions", JSON.stringify([{ ...form, ts: Date.now() }, ...prev]));
      } catch { /* ignore */ }
    }
    setSubmitted(true);
    setForm({ name: "", phone: "", email: "", department: "sales", description: "" });
    showToast?.("Message sent. We'll be in touch soon!", "success");
  };

  const showForm = show("form");
  const showMethods = show("contactMethods");
  const showHours = show("businessHours");
  return (
    <div className="bg-gradient-to-b from-[#eef5fb] via-white to-white">
      {show("hero") && <Hero />}

      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 -mt-12 relative z-10">
        {show("quickActions") && <QuickActions />}

        <nav className="flex items-center gap-2 text-sm text-brand-muted mb-6 mt-8">
          <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
          <span>/</span>
          <span className="text-brand-ink font-semibold">Contact Us</span>
        </nav>

        {(showForm || showMethods || showHours) && (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
            {showForm && (
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
            )}

            {(showMethods || showHours) && (
              <div className={`${showForm ? "lg:col-span-5" : "lg:col-span-12"} space-y-5`}>
                {showMethods && <ContactMethods />}
                {showHours && <BusinessHours />}
              </div>
            )}
          </div>
        )}

        {show("officeMap") && <OfficeMap />}
        {show("faq") && <FaqStrip faqs={FAQS} openFaq={openFaq} setOpenFaq={setOpenFaq} />}
      </div>
    </div>
  );
}

function Hero() {
  const { HERO_SUBTITLE, HERO_BADGE, HERO_BADGE_CLOSED, HERO_TITLE, STAT_CHIPS } = useContactConfig();
  const status = useOpenStatus();
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-[#0b1d3a] via-[#173968] to-[#3684bf] text-white">
      <div className="absolute inset-0 opacity-20 pointer-events-none">
        <div className="absolute -top-20 -left-20 w-80 h-80 rounded-full bg-blue-300 blur-3xl" />
        <div className="absolute top-10 right-10 w-72 h-72 rounded-full bg-cyan-200 blur-3xl opacity-60" />
        <div className="absolute -bottom-20 left-1/3 w-96 h-96 rounded-full bg-white blur-3xl opacity-20" />
      </div>
      <div className="relative max-w-[1400px] mx-auto px-4 sm:px-6 pt-12 pb-24 text-center">
        <span className="inline-flex items-center gap-2 bg-white/15 backdrop-blur rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider mb-4">
          <span className={`w-2 h-2 rounded-full ${status.open ? "bg-green-400 animate-pulse" : "bg-red-400"}`} />
          {status.open ? HERO_BADGE : HERO_BADGE_CLOSED}
        </span>
        <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight">
          {HERO_TITLE}
        </h1>
        <p className="text-sm sm:text-base text-white/85 mt-3 max-w-2xl mx-auto leading-relaxed">
          {HERO_SUBTITLE}
        </p>
        <div className="flex flex-wrap items-center justify-center gap-2 mt-5 text-xs">
          {STAT_CHIPS.map((b) => (
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
  const { PHONE_RAW, PHONE_DISPLAY, EMAIL_INFO, MAPS_QUERY, CITY_LABEL } = useContactInfo();
  const { LABELS } = useContactConfig();
  const actions = [
    {
      label: LABELS.whatsapp,
      sub: LABELS.whatsappSub,
      bg: "from-[#25D366] to-[#1ebe57]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.11.55 4.16 1.6 5.97L0 24l6.18-1.62A11.95 11.95 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.25-6.21-3.48-8.52zM12 22a9.9 9.9 0 01-5.05-1.38l-.36-.21-3.67.96.98-3.58-.23-.37A9.93 9.93 0 012 12c0-5.52 4.48-10 10-10s10 4.48 10 10-4.48 10-10 10z" /></svg>
      ),
      onClick: () => window.open(`https://wa.me/${PHONE_RAW}?text=${encodeURIComponent("Hi, I have a query about your dental products.")}`, "_blank"),
    },
    {
      label: LABELS.call,
      sub: PHONE_DISPLAY,
      bg: "from-[#3684bf] to-[#1f5f96]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" /></svg>
      ),
      onClick: () => { window.location.href = `tel:+${PHONE_RAW}`; },
    },
    {
      label: LABELS.email,
      sub: EMAIL_INFO,
      bg: "from-[#f97316] to-[#ea580c]",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
      ),
      onClick: () => { window.location.href = `mailto:${EMAIL_INFO}`; },
    },
    {
      label: LABELS.visit,
      sub: CITY_LABEL,
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
  const { EMAIL_INFO } = useContactInfo();
  const { DEPARTMENTS, RESPONSE_NOTE, FORM_TITLE, FORM_CHIP, LABELS } = useContactConfig();
  const status = useOpenStatus();
  const charCount = form.description.length;
  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-7 lg:p-8">
      <div className="flex items-start justify-between gap-3 mb-2">
        <div>
          <h2 className="text-2xl sm:text-3xl font-bold text-brand-ink">{FORM_TITLE}</h2>
          <p className="text-sm text-brand-muted mt-1">
            {LABELS.formSubtitle}
          </p>
        </div>
        <span className={`hidden sm:inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold border ${status.open ? "bg-green-50 border-green-200 text-green-700" : "bg-red-50 border-red-200 text-red-700"}`}>
          <span className={`w-1.5 h-1.5 rounded-full ${status.open ? "bg-green-500 animate-pulse" : "bg-red-500"}`} />
          {FORM_CHIP}
        </span>
      </div>

      {submitted ? (
        <div className="mt-6 bg-green-50 border border-green-200 rounded-xl p-5 sm:p-6 text-center">
          <div className="w-14 h-14 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-3">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#16a34a"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
          </div>
          <h3 className="text-lg font-bold text-brand-ink mb-1">{LABELS.successTitle}</h3>
          <p className="text-sm text-brand-muted mb-4">Thanks — {RESPONSE_NOTE} We've also sent a confirmation to your email.</p>
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
              {LABELS.deptHelp}
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

          <FloatField id="name" label={LABELS.fieldName} value={form.name} onChange={onChange("name")} />
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FloatField
              id="phone"
              label={LABELS.fieldPhone}
              type="tel"
              inputMode="numeric"
              maxLength={10}
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value.replace(/\D/g, "").slice(0, 10) }))}
            />
            <FloatField id="email" label={LABELS.fieldEmail} type="email" value={form.email} onChange={onChange("email")} />
          </div>
          <div>
            <FloatField
              id="description"
              label={LABELS.fieldMsg}
              value={form.description}
              onChange={onChange("description")}
              textarea
              rows={5}
              maxLength={500}
            />
            <div className="flex items-center justify-between mt-1 text-[11px] text-brand-muted">
              <span>{LABELS.msgHint}</span>
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
            {LABELS.sendBtn}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" /></svg>
          </button>

          <p className="text-[11px] text-brand-muted text-center">
            {LABELS.privacyNote}
          </p>
        </form>
      )}
    </div>
  );
}

function ContactMethods() {
  const { PHONE_DISPLAY, PHONE_SUPPORT, PHONE_RAW, EMAIL_PRIMARY, EMAIL_INFO } = useContactInfo();
  const { socials = [] } = useSettings();
  const { LABELS } = useContactConfig();
  const socialUrl = (id) => socials.find((s) => s.id === id)?.url || "#";
  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
      <h3 className="text-lg font-bold text-brand-ink mb-4">{LABELS.reachHeading}</h3>
      <ul className="space-y-4">
        <Method
          color="#16a34a"
          label={LABELS.reachSales}
          value={PHONE_DISPLAY}
          href={`tel:+${PHONE_RAW}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" /></svg>
          }
        />
        <Method
          color="#3684bf"
          label={LABELS.reachSupport}
          value={PHONE_SUPPORT}
          href={`tel:${PHONE_SUPPORT.replace(/\s/g, "")}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z" /></svg>
          }
        />
        <Method
          color="#f97316"
          label={LABELS.reachEmailSales}
          value={EMAIL_PRIMARY}
          href={`mailto:${EMAIL_PRIMARY}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
          }
        />
        <Method
          color="#ec4899"
          label={LABELS.reachGeneral}
          value={EMAIL_INFO}
          href={`mailto:${EMAIL_INFO}`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
          }
        />
      </ul>

      <div className="mt-5 pt-5 border-t border-gray-100">
        <p className="text-xs font-bold uppercase tracking-wider text-brand-muted mb-3">{LABELS.followHeading}</p>
        <div className="flex items-center gap-2">
          {[
            { id: "facebook", color: "#1877F2", path: "M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z" },
            { id: "instagram", color: "#E4405F", path: "M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153.509.5.902 1.105 1.153 1.772.247.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 01-1.153 1.772c-.5.508-1.105.902-1.772 1.153-.637.247-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06-2.717 0-3.056-.01-4.122-.06-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 01-1.772-1.153 4.904 4.904 0 01-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.066.217-1.79.465-2.428a4.88 4.88 0 011.153-1.772A4.897 4.897 0 015.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 5a5 5 0 100 10 5 5 0 000-10zm6.5-.25a1.25 1.25 0 10-2.5 0 1.25 1.25 0 002.5 0zM12 9a3 3 0 110 6 3 3 0 010-6z" },
            { id: "youtube", color: "#FF0000", path: "M21.582 6.186a2.506 2.506 0 00-1.768-1.768C18.254 4 12 4 12 4s-6.254 0-7.814.418A2.506 2.506 0 002.418 6.186C2 7.746 2 12 2 12s0 4.254.418 5.814a2.506 2.506 0 001.768 1.768C5.746 20 12 20 12 20s6.254 0 7.814-.418a2.506 2.506 0 001.768-1.768C22 16.254 22 12 22 12s0-4.254-.418-5.814zM10 15.464V8.536L16 12l-6 3.464z" },
            { id: "linkedin", color: "#0A66C2", path: "M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" },
          ].filter((s) => socialUrl(s.id) !== "#").map((s) => (
            <a
              key={s.id}
              href={socialUrl(s.id)}
              target="_blank"
              rel="noopener noreferrer"
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
  const { BUSINESS_HOURS: rows, TIMEZONE, LABELS } = useContactConfig();
  const today = useOpenStatus();

  return (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-bold text-brand-ink">{LABELS.hoursHeading}</h3>
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
        🇮🇳 Timezone: {TIMEZONE}
      </div>
    </div>
  );
}

function OfficeMap() {
  const { MAPS_QUERY, ADDRESS_FULL, PHONE_RAW } = useContactInfo();
  const { OFFICE_SUBTITLE, OFFICE_BULLETS, LABELS } = useContactConfig();
  return (
    <section className="mt-10">
      <div className="text-center mb-6">
        <span className="inline-block text-[11px] font-bold uppercase tracking-wider text-[#3684bf] bg-blue-50 border border-blue-100 rounded-full px-3 py-1 mb-2">
          {LABELS.visitBadge}
        </span>
        <h2 className="text-2xl sm:text-3xl font-bold text-brand-ink">{LABELS.officeHeading}</h2>
        <p className="mt-1 text-sm text-brand-muted max-w-xl mx-auto">
          {OFFICE_SUBTITLE}
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
            {OFFICE_BULLETS.map((b, i) => (
              <li key={i} className="flex items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#3684bf"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
                {b}
              </li>
            ))}
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
  const { LABELS } = useContactConfig();
  return (
    <section className="mt-10">
      <div className="text-center mb-6">
        <span className="inline-block text-[11px] font-bold uppercase tracking-wider text-[#3684bf] bg-blue-50 border border-blue-100 rounded-full px-3 py-1 mb-2">
          Quick Answers
        </span>
        <h2 className="text-2xl sm:text-3xl font-bold text-brand-ink">{LABELS.faqHeading}</h2>
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
