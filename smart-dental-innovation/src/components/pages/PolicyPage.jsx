import { useUI } from "../../context/UIContext";

const CONTENT = {
  return: {
    title: "Return Policy",
    sections: [
      {
        h: "Eligibility",
        p: "Most unused products may be returned within 7 days of delivery if sealed and in original packaging. Hygiene-sensitive items (intraoral burs, files, scrubs) are non-returnable once opened.",
      },
      {
        h: "Process",
        p: "Raise a return from Account → Orders → select item → Request Return. Our team will schedule pickup within 48 hours.",
      },
      {
        h: "Refund Timeline",
        p: "Refunds initiate within 3 business days of pickup quality-check. UPI/Card refunds reach your account in 5–7 business days. COD orders refund to original UPI/Bank shared at pickup.",
      },
      {
        h: "Damaged or Incorrect Items",
        p: "Report within 48 hours of delivery with unboxing video. Full refund or replacement at our cost.",
      },
    ],
  },
  terms: {
    title: "Terms of Use",
    sections: [
      {
        h: "Acceptance",
        p: "By accessing this site you agree to these terms. Products are sold business-to-business to licensed dental professionals and registered clinics.",
      },
      {
        h: "Product Information",
        p: "We attempt to display accurate product details, but specifications, packaging, and availability may change without notice. Always verify catalogue with our team before bulk orders.",
      },
      {
        h: "Orders & Pricing",
        p: "Prices are in INR and exclusive of statutory taxes unless stated. We reserve the right to cancel orders with incorrect price/stock, with full refund.",
      },
      {
        h: "Liability",
        p: "Smart Dental Innovations is not liable for clinical outcomes resulting from product use. Operators must follow manufacturer instructions and applicable regulations.",
      },
      {
        h: "Governing Law",
        p: "Disputes are subject to courts of Surat, Gujarat.",
      },
    ],
  },
  privacy: {
    title: "Privacy Policy",
    sections: [
      {
        h: "Data We Collect",
        p: "Name, phone, email, billing & shipping address, clinic GSTIN (if provided), order history, device/browser metadata, and pincode entered for delivery checks.",
      },
      {
        h: "How We Use It",
        p: "Fulfilling orders, payment processing, delivery, customer support, fraud prevention, statutory invoicing, and product communication you've opted into.",
      },
      {
        h: "Sharing",
        p: "Limited sharing with logistics, payment gateways, and tax authorities as required. We never sell personal data.",
      },
      {
        h: "Your Rights",
        p: "Email info@smartdentalinnovations.com to access, correct, or delete your data. We retain order records as required by Indian tax law (typically 7 years).",
      },
      {
        h: "Cookies",
        p: "We use functional cookies for cart, login, and analytics. You may disable cookies, but parts of the site may not work.",
      },
    ],
  },
};

export default function PolicyPage() {
  const { view, navigate } = useUI();
  const type = view?.params?.type || "terms";
  const data = CONTENT[type] || CONTENT.terms;
  const tabs = [
    { id: "return", label: "Return Policy" },
    { id: "terms", label: "Terms of Use" },
    { id: "privacy", label: "Privacy" },
  ];

  return (
    <div className="max-w-[1100px] mx-auto px-4 sm:px-6 py-8">
      <nav className="flex items-center gap-2 text-sm text-brand-muted mb-4">
        <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
        <span>/</span>
        <button onClick={() => navigate("policy", { type: "terms" })} className="hover:text-[#3684bf]">Policies</button>
        <span>/</span>
        <span className="text-brand-ink font-semibold">{data.title}</span>
      </nav>

      <div className="flex flex-wrap gap-2 mb-6 border-b border-gray-200">
        {tabs.map((t) => (
          <button
            key={t.id}
            onClick={() => navigate("policy", { type: t.id })}
            className={`px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition ${
              type === t.id
                ? "border-[#3684bf] text-[#3684bf]"
                : "border-transparent text-brand-muted hover:text-brand-ink"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <article className="bg-white rounded-xl border border-gray-100 p-6 sm:p-8 space-y-5">
        <h1 className="text-2xl sm:text-3xl font-bold text-brand-ink">{data.title}</h1>
        <p className="text-xs text-brand-muted">Last updated: 01 June 2026</p>
        {data.sections.map((s, i) => (
          <section key={i}>
            <h2 className="text-base sm:text-lg font-bold text-brand-ink mb-1.5">{s.h}</h2>
            <p className="text-sm text-brand-muted leading-relaxed">{s.p}</p>
          </section>
        ))}
        <div className="pt-4 border-t border-gray-100 text-xs text-brand-muted">
          Questions? Email{" "}
          <a className="text-[#3684bf] font-semibold" href="mailto:info@smartdentalinnovations.com">
            info@smartdentalinnovations.com
          </a>
          .
        </div>
      </article>
    </div>
  );
}
