import React from 'react';
import TopBar from './TopBar';
import { useUI } from '../../context/UIContext';

const FOOTER_SECTIONS = [
  {
    title: "ABOUT",
    links: [
      { label: "Contact Us", route: "contact" },
      { label: "About Us", route: "about" },
      { label: "Careers", external: "https://www.linkedin.com/in/smart-dental-innovations-017331382/" }
    ]
  },
  {
    title: "CONTACT WITH US",
    links: [
      { label: "Buying Guide", route: "about" },
      { label: "Bulk Price Inquiry", route: "contact" }
    ]
  },
  {
    title: "HELP",
    links: [
      { label: "Orders", route: "orders", requireAuth: true },
      { label: "Refunds", route: "orders", requireAuth: true },
      { label: "Payments", route: "orders", requireAuth: true }
    ]
  },
  {
    title: "POLICY",
    links: [
      { label: "Return Policy", route: "policy", params: { type: "return" } },
      { label: "Term Of Use", route: "policy", params: { type: "terms" } },
      { label: "Privacy", route: "policy", params: { type: "privacy" } },
      { label: "Sitemap", external: "/sitemap.xml" }
    ]
  }
];

export function Footer() {
  const { navigate, openModal } = useUI();
  const handleLink = (link) => {
    if (link.external) {
      window.open(link.external, "_blank", "noopener,noreferrer");
      return;
    }
    if (link.requireAuth) {
      try {
        const raw = localStorage.getItem("sdi:auth");
        const u = raw ? JSON.parse(raw) : null;
        if (!u) { openModal("auth"); return; }
      } catch { openModal("auth"); return; }
    }
    if (link.route) navigate(link.route, link.params || null);
  };
  return (
    <footer className="w-full bg-[var(--background-secondary,#f8f9fa)] text-[var(--text-primary,#212529)] border-t border-gray-200">
      <TopBar />
      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 md:px-8 py-10 sm:py-12">
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-11 gap-6 sm:gap-8">

          {FOOTER_SECTIONS.map((section) => {
            const lgSpan = section.title === "CONTACT WITH US" ? "lg:col-span-3" : "lg:col-span-2";
            return (
              <div key={section.title} className={`flex flex-col ${lgSpan}`}>
                <h6 className="text-[13px] sm:text-[14px] font-bold tracking-wider text-gray-900 uppercase mb-3 sm:mb-4">
                  {section.title}
                </h6>
                <div className="flex flex-col items-start gap-2 sm:gap-2.5">
                  {section.links.map((link) => (
                    <button
                      key={link.label}
                      type="button"
                      onClick={() => handleLink(link)}
                      className="text-left text-[12px] sm:text-[13px] text-gray-600 font-medium hover:text-[var(--main,#1976d2)] transition-colors bg-transparent border-0 p-0 cursor-pointer"
                    >
                      {link.label}
                    </button>
                  ))}
                </div>
              </div>
            );
          })}

          {/* REGISTERED OFFICE ADDRESS */}
          <div className="col-span-2 md:col-span-3 lg:col-span-2 flex flex-col">
            <h6 className="text-[14px] font-bold tracking-wider text-gray-900 uppercase mb-4">
              REGISTERED OFFICE ADDRESS
            </h6>
            <div className="flex flex-col gap-3.5 text-[13px] text-gray-600 font-medium">
              <div className="flex items-start gap-2.5">
                <svg className="w-4 h-4 shrink-0 text-gray-500 mt-0.5" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2m-6 0h-4V4h4z" />
                </svg>
                <span className="leading-relaxed">
                  Third floor, Swastik Plaza, 308, Savlia Cir, Yogi Chowk Ground, Chikuwadi, Varachha, Surat, Gujarat 395006
                </span>
              </div>

              <div className="flex items-center gap-2.5">
                <svg className="w-4 h-4 shrink-0 text-gray-500" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02z" />
                </svg>
                <a href="tel:+919328762586" className="hover:text-[var(--main,#1976d2)] transition-colors">
                  +919328762586
                </a>
              </div>

              <div className="flex items-center gap-2.5">
                <svg className="w-4 h-4 shrink-0 text-gray-500" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 4-8 5-8-5V6l8 5 8-5z" />
                </svg>
                <a href="mailto:smartdentalinnovations.web@gmail.com" className="hover:text-[var(--main,#1976d2)] transition-colors break-all">
                  smartdentalinnovations.web@gmail.com
                </a>
              </div>

              <div className="flex items-center gap-2.5">
                <svg className="w-4 h-4 shrink-0 text-gray-500" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2m-6 0h-4V4h4z" />
                </svg>
                <span>Mon to Sat (10:00 AM to 7:00 PM)</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      {/* PAYMENT + RATING STRIP */}
      <div className="border-t border-gray-200">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 md:px-8 py-8">
          <div className="flex flex-col lg:flex-row items-center justify-between gap-6">

            {/* Secure payments box */}
            <div className="w-full lg:max-w-[600px] rounded-xl border border-[var(--main,#1976d2)] px-5 py-5">
              <div className="flex items-center gap-3 mb-3">
                <svg className="w-7 h-7 shrink-0 text-[var(--main,#1976d2)]" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 2 4 5v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V5zm-2 14-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8z" />
                </svg>
                <div>
                  <p className="text-[15px] font-bold uppercase text-gray-900 leading-tight">100% Secure Payments</p>
                  <p className="text-[13px] text-gray-500">Secure SSL Encrypted Payment</p>
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {[
                  { label: "Credit/Debit Card", icon: "M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2m0 14H4v-6h16zm0-10H4V6h16z" },
                  { label: "Net Banking", icon: "M11.5 1 2 6v2h19V6M16 10v7h3v-7M2 22h19v-3H2M10 10v7h3v-7M4 10v7h3v-7z" },
                  { label: "UPI", icon: "M3 11h8V3H3zm2-6h4v4H5zM3 21h8v-8H3zm2-6h4v4H5zm8-12v8h8V3zm6 6h-4V5h4zm-6 12h2v2h-2zm2-6h2v2h-2zm2 2h2v2h-2zm-4 0h2v2h-2zm2 2h2v2h-2zm2 2h2v2h-2zm-2 0h2v2h-2zm0-4h2v2h-2z" },
                ].map((p) => (
                  <div key={p.label} className="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-3">
                    <svg className="w-6 h-6 shrink-0 text-[var(--main,#1976d2)]" viewBox="0 0 24 24">
                      <path fill="currentColor" d={p.icon} />
                    </svg>
                    <span className="text-[13px] font-medium uppercase text-gray-600">{p.label}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Average rating */}
            <div className="flex items-baseline gap-2">
              <span className="text-[18px] font-bold text-[var(--main,#1976d2)]">4.5</span>
              <span className="text-[14px] text-gray-600">Average online rating</span>
            </div>

          </div>
        </div>
      </div>

      {/* COPYRIGHT BAR */}
      <div className="border-t border-gray-200">
        <div className="max-w-[1400px] mx-auto px-4 py-5 text-center">
          <p className="text-[13px] font-medium uppercase tracking-wide text-gray-500">
            2016-2025, Smart Dental Innovation <span className="mx-1.5">•</span>
            Crafted with <span className="text-red-500">♥</span> in India
          </p>
        </div>
      </div>
    </footer>
  );
}

export default Footer;