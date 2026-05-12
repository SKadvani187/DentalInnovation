const columns = [
  { title: "About", links: ["Contact Us", "About Us", "Careers"] },
  { title: "Contact", links: ["Buying Guide", "Bulk Price Inquiry"] },
  { title: "Help", links: ["Orders", "Refunds", "Payments"] },
  { title: "Policy", links: ["Return Policy", "Terms of Use", "Privacy", "Sitemap"] },
];

export default function Footer() {
  return (
    <footer className="bg-gray-50 border-t border-gray-200 mt-12">
      <div className="max-w-[1400px] mx-auto px-3 sm:px-6 py-10 sm:py-12">
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8">
          {/* Brand */}
          <div className="col-span-2 lg:col-span-2">
            <div className="flex items-center gap-2 mb-3">
              <div className="w-10 h-10 rounded-lg bg-brand-navy text-white flex items-center justify-center font-bold">SD</div>
              <div className="leading-tight">
                <p className="font-bold text-brand-navy">Smart Dental</p>
                <p className="text-[10px] uppercase tracking-wider text-brand-muted">Innovations</p>
              </div>
            </div>
            <p className="text-xs text-brand-muted leading-relaxed mb-4">
              High-quality dental materials for precise, reliable, and better clinical results. Trusted by 5,000+ dental practices.
            </p>

            <p className="text-xs text-brand-muted mb-1">
              <strong className="text-brand-ink">Registered Office:</strong><br />
              Third floor, Swastik Plaza, 308, Savlia Cir,<br />
              Yogi Chowk Ground, Chikuwadi, Varachha,<br />
              Surat, Gujarat 395006
            </p>
            <p className="text-xs text-brand-muted mt-3">
              <strong className="text-brand-ink">Phone:</strong> +91 93287 62586<br />
              <strong className="text-brand-ink">Email:</strong> smartdentalinnovations.web@gmail.com<br />
              <strong className="text-brand-ink">Hours:</strong> Mon–Sat, 10:00 AM – 7:00 PM
            </p>
          </div>

          {columns.map((c) => (
            <div key={c.title}>
              <h4 className="text-xs uppercase tracking-wider font-bold text-brand-ink mb-3">{c.title}</h4>
              <ul className="space-y-2">
                {c.links.map((l) => (
                  <li key={l}>
                    <a href="#" className="text-xs text-brand-muted hover:text-brand-navy">{l}</a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Payment / trust */}
        <div className="mt-10 pt-6 border-t border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3 flex-wrap">
            <span className="text-xs font-semibold text-brand-ink">100% Secure Payments</span>
            {["VISA", "MC", "UPI", "Net Banking"].map((p) => (
              <span key={p} className="px-2.5 py-1 rounded border border-gray-300 text-[10px] font-semibold bg-white">{p}</span>
            ))}
          </div>
          <div className="flex items-center gap-2 text-xs">
            <div className="flex text-brand-amber">
              {Array.from({ length: 5 }).map((_, i) => (
                <svg key={i} width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 6.91-1.01z" />
                </svg>
              ))}
            </div>
            <span className="font-semibold text-brand-ink">4.5</span>
            <span className="text-brand-muted">Average online rating</span>
          </div>
        </div>

        <p className="mt-6 text-center text-xs text-brand-muted">
          2016–2025, SMART DENTAL INNOVATION • Crafted with <span className="text-brand-orange">♥</span> in India
        </p>
      </div>
    </footer>
  );
}
