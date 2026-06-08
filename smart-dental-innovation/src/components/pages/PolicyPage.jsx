import { useParams } from "react-router-dom";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useSettings } from "../../context/SettingsContext";

export default function PolicyPage() {
  const navigate = useAppNavigate();
  const { type: typeParam } = useParams();
  const { policies = {}, company = {} } = useSettings();
  const email = company.email || "info@smartdentalinnovations.com";
  const type = typeParam || "terms";
  const data = policies[type] || policies.terms || { title: "Policy", sections: [] };
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
          <a className="text-[#3684bf] font-semibold" href={`mailto:${email}`}>
            {email}
          </a>
          .
        </div>
      </article>
    </div>
  );
}
