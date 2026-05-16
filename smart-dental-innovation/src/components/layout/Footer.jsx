import React from 'react';
import TopBar from './TopBar';

const FOOTER_SECTIONS = [
  {
    title: "ABOUT",
    links: [
      { label: "Contact Us", href: "/contact-us" },
      { label: "About Us", href: "/about-us" },
      { label: "Careers", href: "https://www.linkedin.com/in/smart-dental-innovations-017331382/" }
    ]
  },
  {
    title: "CONTACT WITH US",
    links: [
      { label: "Buying Guide", href: "/buying-guide" },
      { label: "Bulk Price Inquiry", href: "/contact-us" }
    ]
  },
  {
    title: "HELP",
    links: [
      { label: "Orders", href: "/orders" },
      { label: "Refunds", href: "/orders" },
      { label: "Payments", href: "/orders" }
    ]
  },
  {
    title: "POLICY",
    links: [
      { label: "Return Policy", href: "/return-policy" },
      { label: "Term Of Use", href: "/terms-and-conditions" },
      { label: "Privacy", href: "/privacy-policy" },
      { label: "Sitemap", href: "/sitemap.xml" }
    ]
  }
];

export function Footer() {
  return (
    <footer className="w-full bg-[var(--background-secondary,#f8f9fa)] text-[var(--text-primary,#212529)] border-t border-gray-200">
      <TopBar />
      {/* Removed px-4 sm:px-6 md:px-8 to remove extra padding on left/right edge */}
      <div className="max-w-[1200px] mx-auto px-0 py-12">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-11 gap-8">
          
          {FOOTER_SECTIONS.map((section) => {
            const lgSpan = section.title === "CONTACT WITH US" ? "lg:col-span-3" : "lg:col-span-2";
            return (
              <div key={section.title} className={`flex flex-col ${lgSpan}`}>
                <h6 className="text-[14px] font-bold tracking-wider text-gray-900 uppercase mb-4">
                  {section.title}
                </h6>
                <div className="flex flex-col gap-2.5">
                  {section.links.map((link) => (
                    <a key={link.label} href={link.href} className="text-[13px] text-gray-600 font-medium hover:text-[var(--main,#1976d2)] transition-colors no-underline">
                      {link.label}
                    </a>
                  ))}
                </div>
              </div>
            );
          })}

          {/* REGISTERED OFFICE ADDRESS */}
          <div className="col-12 sm:col-span-2 md:col-span-4 lg:col-span-2 flex flex-col">
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
    </footer>
  );
}

export default Footer;