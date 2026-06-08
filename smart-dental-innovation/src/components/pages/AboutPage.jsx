import { useEffect, useRef, useState } from "react";
import { useUI } from "../../context/UIContext";
import { useSettings } from "../../context/SettingsContext";

const ABOUT_BLOCKS = {
  hero: HeroSection,
  story: OurStory,
  stats: StatsStrip,
  milestones: Milestones,
  coreValues: CoreValues,
  leadership: Leadership,
  whyTrust: WhyTrust,
  missionVision: MissionVision,
  testimonials: Testimonials,
  certifications: Certifications,
  whatWeOffer: WhatWeOffer,
  cta: ReadyCTA,
  contactStrip: ContactStrip,
  socialStrip: SocialStrip,
};

const ABOUT_DEFAULT = [
  "hero","story","stats","milestones","coreValues","leadership","whyTrust",
  "missionVision","testimonials","certifications","whatWeOffer","cta","contactStrip","socialStrip",
];

export default function AboutPage() {
  const { aboutSections = [] } = useSettings();
  const layout = (aboutSections?.length ? aboutSections : ABOUT_DEFAULT.map((k) => ({ key: k, enabled: true })))
    .filter((s) => s.enabled !== false);
  return (
    <div>
      {layout.map((s) => {
        const Block = ABOUT_BLOCKS[s.key];
        return Block ? <Block key={s.key} /> : null;
      })}
    </div>
  );
}

// About config from settings (with static fallback)
function useAbout() {
  const { aboutConfig } = useSettings();
  return aboutConfig || {};
}

function useCountUp(target, duration = 1600) {
  const [val, setVal] = useState(0);
  const ref = useRef(null);
  const started = useRef(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting && !started.current) {
          started.current = true;
          const start = performance.now();
          const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            setVal(Math.round(target * eased));
            if (t < 1) requestAnimationFrame(tick);
          };
          requestAnimationFrame(tick);
        }
      });
    }, { threshold: 0.4 });
    obs.observe(el);
    return () => obs.disconnect();
  }, [target, duration]);
  return [ref, val];
}

function SectionLabel({ children }) {
  return (
    <span className="inline-flex items-center gap-3 text-[#3684bf] text-xs font-bold uppercase tracking-[0.2em]">
      <span className="w-8 h-[2px] bg-[#3684bf]" />
      {children}
    </span>
  );
}

function HeroSection() {
  const { hero = {} } = useAbout();
  const { navigate } = useUI();
  const stats = hero.stats || [];
  return (
    <section className="relative overflow-hidden bg-[#0b1d3a] text-white">
      <div className="absolute inset-0 pointer-events-none opacity-30"
        style={{ backgroundImage: "linear-gradient(rgba(54,132,191,0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(54,132,191,0.15) 1px, transparent 1px)", backgroundSize: "40px 40px" }}
      />
      <div className="max-w-[1400px] mx-auto px-4 py-16 lg:py-24 grid grid-cols-1 lg:grid-cols-2 gap-10 relative">
        <div>
          <span className="inline-flex items-center gap-2 border border-[#3684bf] rounded-full px-4 py-1.5 text-xs font-bold tracking-wider text-[#5fb6ff] uppercase">
            <span className="w-1.5 h-1.5 rounded-full bg-[#5fb6ff]" />
            {hero.badge}
          </span>
          <h1 className="mt-6 text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>
            {hero.title}
          </h1>
          <p className="mt-6 text-base text-gray-300 max-w-xl leading-relaxed">{hero.description}</p>
          <button onClick={() => navigate("category")} className="mt-8 inline-flex items-center gap-2 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold px-6 py-3 rounded-lg transition shadow-lg">
            {hero.ctaText}
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
          </button>
        </div>
        <div className="lg:pl-10">
          <div className="bg-[#0f2547]/60 border border-[#1f3a66] backdrop-blur-sm rounded-2xl p-8">
            <h3 className="text-2xl font-bold mb-2">{hero.cardTitle}</h3>
            <div className="h-px bg-[#1f3a66] mb-6" />
            <div className="grid grid-cols-2 gap-4">
              {stats.map((s, i) => (
                <div key={i} className="bg-[#0a1f3f]/60 border border-[#1f3a66] rounded-xl px-5 py-6 text-center">
                  <div className="text-3xl font-bold text-[#7ec9ff]" style={{ fontFamily: "'El Messiri', serif" }}>{s.value}</div>
                  <div className="text-sm text-gray-300 mt-1">{s.label}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function OurStory() {
  const { story = {} } = useAbout();
  const tiles = [
    { label: "Endodontics", icon: <path d="M12 2C8 6 7 11 12 22 17 11 16 6 12 2z" /> },
    { label: "Cautery", icon: <><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2" /></> },
    { label: "Implants", icon: <><rect x="4" y="4" width="7" height="7" rx="1.5" /><rect x="13" y="4" width="7" height="7" rx="1.5" /><rect x="4" y="13" width="7" height="7" rx="1.5" /><rect x="13" y="13" width="7" height="7" rx="1.5" /></> },
    { label: "New Clinic", icon: <><rect x="5" y="3" width="14" height="18" rx="2" /><path d="M12 8v6M9 11h6" /></> },
    { label: "Restorative", icon: <><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.35-4.35" /></> },
    { label: "Handpiece", icon: <path d="M12 2L4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6l-8-4z" /> },
  ];
  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <div className="relative">
          <div className="relative bg-gradient-to-br from-[#0b1d3a] to-[#13335f] rounded-2xl p-8 lg:p-10">
            <div className="grid grid-cols-3 gap-4">
              {tiles.map((t) => (
                <div key={t.label} className="bg-[#0f2547]/60 border border-[#1f3a66] rounded-xl p-4 flex flex-col items-center justify-center text-center hover:bg-[#13335f] transition">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#5fb6ff" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{t.icon}</svg>
                  <span className="text-xs text-white mt-2 font-medium">{t.label}</span>
                </div>
              ))}
            </div>
          </div>
          <div className="absolute -bottom-8 right-8 lg:right-16 bg-[#5fb6ff] text-[#0b1d3a] rounded-xl px-6 py-4 shadow-2xl">
            <div className="text-xl font-bold" style={{ fontFamily: "'El Messiri', serif" }}>{story.parentLabel}</div>
            <div className="text-xs font-semibold">{story.parentName}</div>
          </div>
        </div>
        <div>
          <SectionLabel>{story.label}</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>{story.heading}</h2>
          {(story.paragraphs || []).map((p, i) => (
            <p key={i} className={`${i === 0 ? "mt-6" : "mt-4"} text-brand-muted leading-relaxed`}>{p}</p>
          ))}
          <div className="mt-6 space-y-3">
            {(story.promises || []).map((p, i) => <PromiseRow key={i} title={p.title} text={p.text} />)}
          </div>
        </div>
      </div>
    </section>
  );
}

function PromiseRow({ title, text }) {
  return (
    <div className="promise-row group flex items-start gap-3 bg-[#eaf4fc] border-l-4 border-[#3684bf] rounded-md p-4 cursor-pointer">
      <span className="w-8 h-8 rounded-md bg-white flex items-center justify-center text-[#3684bf] shrink-0 transition-transform duration-300 group-hover:scale-110">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
      </span>
      <div>
        <h4 className="font-bold text-brand-ink transition-colors duration-300 group-hover:text-[#3684bf]">{title}</h4>
        <p className="text-sm text-brand-muted leading-relaxed">{text}</p>
      </div>
    </div>
  );
}

function StatsStrip() {
  const { stats = [] } = useAbout();
  // parse value like "1,000+" -> {num, suffix}
  const parse = (v) => {
    const m = String(v).match(/^([\d,.]+)(.*)$/);
    if (!m) return { num: 0, suffix: v, fmt: (x) => x };
    const isRating = m[2].includes("★");
    const num = parseFloat(m[1].replace(/,/g, "")) * (isRating ? 10 : 1);
    return { num, suffix: m[2], fmt: (x) => isRating ? (x / 10).toFixed(1) : Math.round(x).toLocaleString("en-IN") };
  };
  return (
    <section className="bg-[#0b1d3a] py-14">
      <div className="max-w-[1400px] mx-auto px-4 grid grid-cols-2 lg:grid-cols-4 gap-6 lg:divide-x lg:divide-[#1f3a66]">
        {stats.map((s, i) => <CountStat key={i} label={s.label} parsed={parse(s.value)} />)}
      </div>
    </section>
  );
}

function CountStat({ label, parsed }) {
  const [ref, val] = useCountUp(parsed.num, 1800);
  return (
    <div ref={ref} className="text-center px-4">
      <div className="inline-flex w-12 h-12 rounded-lg bg-[#0f2547] border border-[#1f3a66] items-center justify-center mb-4 text-[#5fb6ff]">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="5" width="18" height="13" rx="1.5" /><path d="M3 18l4 3h10l4-3" /></svg>
      </div>
      <div className="text-4xl font-bold text-white tabular-nums" style={{ fontFamily: "'El Messiri', serif" }}>
        {parsed.fmt(val)}<span className="text-[#5fb6ff]">{parsed.suffix.replace("★", "★")}</span>
      </div>
      <div className="text-sm text-gray-300 mt-1">{label}</div>
    </div>
  );
}

function Milestones() {
  const { milestones = {} } = useAbout();
  const items = milestones.items || [];
  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>{milestones.label}</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>{milestones.heading}</h2>
          <p className="mt-3 text-brand-muted max-w-xl mx-auto">{milestones.subtitle}</p>
        </div>
        <div className="relative">
          <div className="hidden md:block absolute left-1/2 top-0 bottom-0 w-[2px] bg-gradient-to-b from-[#5fb6ff] via-[#3684bf] to-[#0b1d3a]" />
          <ol className="space-y-8 md:space-y-12">
            {items.map((m, i) => {
              const left = i % 2 === 0;
              return (
                <li key={i} className="md:grid md:grid-cols-2 md:gap-10 items-center">
                  <div className={`${left ? "md:order-1 md:text-right md:pr-10" : "md:order-2 md:pl-10"}`}>
                    <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:shadow-lg hover:border-[#3684bf] transition inline-block max-w-md">
                      <div className="text-[11px] font-bold uppercase tracking-wider text-[#3684bf] mb-1">{m.year}</div>
                      <h3 className="text-lg font-bold text-brand-ink mb-1">{m.title}</h3>
                      <p className="text-sm text-brand-muted leading-relaxed">{m.text}</p>
                    </div>
                  </div>
                  <div className={`hidden md:flex ${left ? "md:order-2" : "md:order-1 justify-end"} relative items-center`}>
                    <div className="absolute w-5 h-5 rounded-full bg-[#3684bf] border-4 border-white shadow-md z-10" style={left ? { left: 0, transform: "translateX(-50%)" } : { right: 0, transform: "translateX(50%)" }} />
                  </div>
                </li>
              );
            })}
          </ol>
        </div>
      </div>
    </section>
  );
}

function CoreValues() {
  const { coreValues = {} } = useAbout();
  const values = coreValues.items || [];
  const colors = ["bg-blue-100", "bg-gray-100", "bg-yellow-100", "bg-blue-100", "bg-gray-100", "bg-orange-100"];
  return (
    <section className="bg-[#eef5fb] py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>{coreValues.label}</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>{coreValues.heading}</h2>
          <p className="mt-4 text-brand-muted max-w-xl mx-auto">{coreValues.subtitle}</p>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-7">
          {values.map((v, i) => (
            <div key={i} className="core-value-card group relative bg-white border border-gray-100 rounded-2xl p-8 lg:p-10 min-h-[280px] shadow-sm cursor-pointer overflow-hidden flex flex-col">
              <span className="core-value-accent absolute top-0 left-0 right-0 h-[3px] bg-[#5fb6ff] origin-left scale-x-0 transition-transform duration-300 group-hover:scale-x-100" />
              <div className={`w-14 h-14 rounded-lg ${colors[i % colors.length]} flex items-center justify-center text-2xl mb-6 transition-transform duration-300 group-hover:scale-110`}>{v.icon}</div>
              <h3 className="text-xl font-bold text-brand-ink mb-3 transition-colors duration-300 group-hover:text-[#3684bf]">{v.title}</h3>
              <p className="text-sm text-brand-muted leading-relaxed flex-1">{v.text}</p>
              <span className="absolute bottom-5 right-7 text-5xl font-bold text-gray-100 select-none transition-colors duration-300 group-hover:text-[#cfe5f5]" style={{ fontFamily: "'El Messiri', serif" }}>{v.n}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function Leadership() {
  const { leadership = {} } = useAbout();
  const team = leadership.team || [];
  return (
    <section className="bg-[#eef5fb] py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>{leadership.label}</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>{leadership.heading}</h2>
          <p className="mt-3 text-brand-muted max-w-xl mx-auto">{leadership.subtitle}</p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          {team.map((p, i) => (
            <div key={i} className="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all">
              <div className="aspect-[4/3] bg-gradient-to-br from-[#0b1d3a] to-[#3684bf] flex items-center justify-center overflow-hidden">
                <img src={p.img} alt={p.name} className="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-transform duration-300" />
              </div>
              <div className="p-5">
                <h3 className="font-bold text-brand-ink">{p.name}</h3>
                <p className="text-xs font-semibold text-[#3684bf] uppercase tracking-wider mb-2">{p.role}</p>
                <p className="text-sm text-brand-muted leading-relaxed">{p.bio}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function WhyTrust() {
  const { whyTrust = {} } = useAbout();
  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
        <div>
          <SectionLabel>{whyTrust.label}</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>{whyTrust.heading}</h2>
          <p className="mt-5 text-brand-muted leading-relaxed">{whyTrust.subtitle}</p>
          <div className="mt-8 space-y-0">
            {(whyTrust.rows || []).map((r, i) => <TrustRow key={i} icon={r.icon} title={r.title} text={r.text} />)}
          </div>
        </div>
        <div className="relative lg:pl-8">
          <div className="hidden lg:block absolute right-0 top-20 w-[85%] h-[420px] bg-[#0b1d3a] rounded-2xl overflow-hidden">
            <span className="absolute inset-0 flex items-end justify-center pb-10 text-[120px] font-bold text-[#142a4f] select-none" style={{ fontFamily: "'El Messiri', serif" }}>SDI</span>
          </div>
          <div className="relative bg-white border border-gray-100 rounded-2xl p-6 shadow-xl z-10 lg:max-w-md lg:ml-0">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-12 h-12 rounded-lg bg-[#3684bf] flex items-center justify-center text-white">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 2l3 6.3 7 1-5 4.9 1.2 7L12 17.8 5.8 21.2 7 14.2l-5-4.9 7-1z" /></svg>
              </div>
              <div>
                <h3 className="font-bold text-brand-ink">{whyTrust.satTitle}</h3>
                <p className="text-xs text-brand-muted">Based on verified reviews</p>
              </div>
            </div>
            <div className="bg-[#eaf4fc] rounded-lg px-4 py-3 flex items-center gap-2 mb-5">
              <span className="text-yellow-500 text-lg">★★★★½</span>
              <span className="font-bold text-brand-ink text-sm">{whyTrust.satRating}</span>
              <span className="text-sm text-brand-muted">— Average Rating</span>
            </div>
            {(whyTrust.satBars || []).map((b, i) => <SatBar key={i} label={b.label} value={b.value} />)}
          </div>
        </div>
      </div>
    </section>
  );
}

function TrustRow({ icon = "check", title, text }) {
  const icons = {
    check: <path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />,
    shield: <path d="M12 2L3 6v6c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V6l-9-4zm-1 14l-4-4 1.4-1.4L11 13.2l5.6-5.6L18 9l-7 7z" />,
    clock: <><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="2" /><path d="M12 7v5l3 2" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></>,
    chat: <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" />,
    dollar: <path d="M12 1v22M17 5H9.5a3.5 3.5 0 100 7h5a3.5 3.5 0 110 7H6" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />,
  };
  return (
    <div className="trust-row group flex items-start gap-3 border-b border-gray-200 py-5 px-3 -mx-3 rounded-lg cursor-pointer">
      <span className="w-10 h-10 rounded-md bg-[#3684bf] text-white flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:scale-110">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">{icons[icon] || icons.check}</svg>
      </span>
      <div>
        <h4 className="font-bold text-brand-ink transition-colors duration-300 group-hover:text-[#3684bf]">{title}</h4>
        <p className="text-sm text-brand-muted leading-relaxed mt-0.5">{text}</p>
      </div>
    </div>
  );
}

function SatBar({ label, value }) {
  return (
    <div className="mb-3">
      <div className="flex justify-between text-sm mb-1">
        <span className="text-brand-ink">{label}</span>
        <span className="font-bold text-brand-ink">{value}%</span>
      </div>
      <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
        <div className="h-full bg-[#3684bf] rounded-full" style={{ width: `${value}%` }} />
      </div>
    </div>
  );
}

function MissionVision() {
  const { missionVision = {} } = useAbout();
  return (
    <section className="bg-[#0b1d3a] text-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>{missionVision.label}</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold" style={{ fontFamily: "'El Messiri', serif" }}>{missionVision.heading}</h2>
          <p className="mt-4 text-gray-300 max-w-2xl mx-auto leading-relaxed">{missionVision.subtitle}</p>
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="mv-card group bg-gradient-to-br from-[#0f2547] to-[#13335f] border border-[#1f3a66] rounded-2xl p-8 lg:p-10 cursor-pointer">
            <div className="w-14 h-14 rounded-lg bg-[#0a1f3f]/70 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110"><span className="text-3xl">🎯</span></div>
            <h3 className="text-2xl font-bold mb-3 transition-colors duration-300 group-hover:text-[#5fb6ff]" style={{ fontFamily: "'El Messiri', serif" }}>Our Mission</h3>
            <p className="text-gray-300 leading-relaxed">{missionVision.mission}</p>
          </div>
          <div className="mv-card group bg-gradient-to-br from-[#0f2547] to-[#13335f] border-l-4 border-[#5fb6ff] border-y border-r border-[#1f3a66] rounded-2xl p-8 lg:p-10 cursor-pointer">
            <div className="w-14 h-14 rounded-lg bg-[#0a1f3f]/70 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110"><span className="text-3xl">🔭</span></div>
            <h3 className="text-2xl font-bold mb-3 transition-colors duration-300 group-hover:text-[#5fb6ff]" style={{ fontFamily: "'El Messiri', serif" }}>Our Vision</h3>
            <p className="text-gray-300 leading-relaxed">{missionVision.vision}</p>
          </div>
        </div>
      </div>
    </section>
  );
}

function Testimonials() {
  const { testimonials = {} } = useAbout();
  const reviews = testimonials.items || [];
  const [idx, setIdx] = useState(0);
  useEffect(() => {
    if (!reviews.length) return;
    const t = setInterval(() => setIdx((i) => (i + 1) % reviews.length), 6000);
    return () => clearInterval(t);
  }, [reviews.length]);
  if (!reviews.length) return null;
  const r = reviews[idx % reviews.length];
  return (
    <section className="bg-white py-16 lg:py-24 relative overflow-hidden">
      <div className="absolute top-0 left-0 w-72 h-72 bg-[#3684bf]/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2 pointer-events-none" />
      <div className="absolute bottom-0 right-0 w-80 h-80 bg-[#5fb6ff]/5 rounded-full blur-3xl translate-x-1/2 translate-y-1/2 pointer-events-none" />
      <div className="max-w-[900px] mx-auto px-4 text-center relative">
        <SectionLabel>{testimonials.label}</SectionLabel>
        <h2 className="mt-4 text-3xl lg:text-4xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>{testimonials.heading}</h2>
        <div className="mt-10 bg-white border border-gray-100 rounded-2xl p-8 sm:p-10 shadow-md relative">
          <svg className="absolute top-5 left-5 w-10 h-10 text-[#5fb6ff]/40" viewBox="0 0 24 24" fill="currentColor"><path d="M14 17h3l2-4V7h-6v6h3zM6 17h3l2-4V7H5v6h3z" /></svg>
          <div className="flex justify-center items-center gap-1 mb-4">
            {[...Array(5)].map((_, i) => (
              <svg key={i} width="20" height="20" viewBox="0 0 24 24" fill={i < r.stars ? "#fbbf24" : "#e5e7eb"}><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" /></svg>
            ))}
          </div>
          <p className="text-base sm:text-lg text-brand-ink leading-relaxed font-medium">"{r.text}"</p>
          <div className="mt-6">
            <p className="font-bold text-brand-ink">{r.name}</p>
            <p className="text-xs text-brand-muted">{r.clinic}</p>
          </div>
        </div>
        <div className="flex justify-center items-center gap-2 mt-6">
          {reviews.map((_, i) => (
            <button key={i} onClick={() => setIdx(i)} aria-label={`Review ${i + 1}`} className={`h-2 rounded-full transition-all ${i === idx ? "w-8 bg-[#3684bf]" : "w-2 bg-gray-300 hover:bg-gray-400"}`} />
          ))}
        </div>
      </div>
    </section>
  );
}

function Certifications() {
  const { certifications = {} } = useAbout();
  const items = certifications.items || [];
  return (
    <section className="bg-[#0b1d3a] py-12 lg:py-16">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-8">
          <SectionLabel>{certifications.label}</SectionLabel>
          <h2 className="mt-4 text-2xl lg:text-3xl font-bold text-white" style={{ fontFamily: "'El Messiri', serif" }}>{certifications.heading}</h2>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
          {items.map((c, i) => (
            <div key={i} className="bg-[#0f2547]/80 border border-[#1f3a66] rounded-xl p-4 text-center hover:border-[#5fb6ff] transition group">
              <div className="text-2xl mb-2 transition group-hover:scale-110">{c.icon}</div>
              <p className="text-xs font-bold text-white">{c.label}</p>
              <p className="text-[10px] text-gray-400 mt-0.5">{c.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function WhatWeOffer() {
  const { navigate } = useUI();
  const { categories = [] } = useSettings();
  const tiles = (categories.length ? categories : []).slice(0, 12).map((c) => ({ label: c.title, category: c.id }));
  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>Our Range</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>What We <span className="italic text-[#5fb6ff]">Offer</span></h2>
          <p className="mt-4 text-brand-muted max-w-xl mx-auto">A complete ecosystem of dental tools across every specialty and clinical need.</p>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          {tiles.map((c) => (
            <button key={c.category} onClick={() => navigate("category", { category: c.category, title: c.label })}
              className="offer-tile group flex items-center gap-3 bg-[#eef5fb] border border-transparent rounded-xl px-5 py-4 text-left cursor-pointer">
              <span className="w-2 h-2 rounded-full bg-[#3684bf] shrink-0 transition-transform duration-300 group-hover:scale-150" />
              <span className="font-semibold text-brand-ink text-sm transition-colors duration-300 group-hover:text-[#3684bf]">{c.label}</span>
            </button>
          ))}
        </div>
      </div>
    </section>
  );
}

function ReadyCTA() {
  const { cta = {} } = useAbout();
  const { navigate } = useUI();
  return (
    <section className="bg-[#eef5fb] py-16 lg:py-24">
      <div className="max-w-[900px] mx-auto px-4 text-center">
        <SectionLabel>{cta.label}</SectionLabel>
        <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>{cta.heading}</h2>
        <p className="mt-5 text-brand-muted max-w-xl mx-auto leading-relaxed">{cta.subtitle}</p>
        <div className="mt-8 flex items-center justify-center gap-3 flex-wrap">
          <button onClick={() => navigate("category")} className="cta-btn cta-primary inline-flex items-center gap-2 bg-[#0b1d3a] text-white font-bold px-7 py-3.5 rounded-full shadow-lg hover:bg-[#13294f] transition-colors">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" /></svg>
            {cta.shopText}
          </button>
          <button onClick={() => navigate("contact")} className="cta-btn cta-outline inline-flex items-center gap-2 border border-gray-300 text-brand-ink font-bold px-7 py-3.5 rounded-full bg-white hover:bg-gray-50 transition-colors">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" /></svg>
            {cta.contactText}
          </button>
        </div>
      </div>
    </section>
  );
}

function ContactStrip() {
  const { company = {} } = useSettings();
  const items = [
    { label: "VISIT US", value: company.address, icon: <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" /><circle cx="12" cy="10" r="3" /></> },
    { label: "CALL US", value: company.phone, icon: <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" /> },
    { label: "EMAIL US", value: company.email, icon: <><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" /></> },
  ];
  return (
    <div className="bg-[#0b1d3a] text-white">
      <div className="max-w-[1400px] mx-auto px-6 py-8 grid grid-cols-1 sm:grid-cols-3 gap-6 lg:divide-x lg:divide-[#1f3a66]">
        {items.map((c) => (
          <div key={c.label} className="flex items-center gap-4 px-4">
            <div className="w-12 h-12 rounded-lg bg-[#0f2547] border border-[#1f3a66] flex items-center justify-center text-[#5fb6ff] shrink-0">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{c.icon}</svg>
            </div>
            <div className="leading-tight">
              <div className="text-[11px] tracking-wider text-gray-400 font-semibold">{c.label}</div>
              <div className="text-sm text-white font-semibold mt-1">{c.value}</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function SocialStrip() {
  const { socials = [], stats = [] } = useSettings();
  const PATHS = {
    facebook: "M22 12a10 10 0 10-11.6 9.87v-6.99H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.77-3.9 1.1 0 2.24.2 2.24.2v2.46h-1.27c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.88h-2.34v6.99A10 10 0 0022 12z",
    instagram: "M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm5 5a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6zm5.5-3a1 1 0 100 2 1 1 0 000-2z",
    youtube: "M21.58 7.19a2.51 2.51 0 00-1.77-1.77C18.25 5 12 5 12 5s-6.25 0-7.81.42A2.51 2.51 0 002.42 7.19C2 8.75 2 12 2 12s0 3.25.42 4.81a2.51 2.51 0 001.77 1.77C5.75 19 12 19 12 19s6.25 0 7.81-.42a2.51 2.51 0 001.77-1.77C22 15.25 22 12 22 12s0-3.25-.42-4.81zM10 15V9l5 3-5 3z",
    google: "M21.35 11.1H12v2.85h5.35c-.23 1.5-1.7 4.4-5.35 4.4-3.22 0-5.85-2.66-5.85-5.95s2.63-5.95 5.85-5.95c1.83 0 3.06.78 3.76 1.45l2.56-2.47C16.79 3.95 14.6 3 12 3 6.99 3 3 6.99 3 12s3.99 9 9 9c5.2 0 8.65-3.66 8.65-8.8 0-.6-.07-1.05-.16-1.5z",
    linkedin: "M4.98 3.5C4.98 4.88 3.88 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.22 8h4.56v14H.22V8zm7.06 0h4.37v1.92h.06c.61-1.15 2.1-2.36 4.32-2.36 4.62 0 5.47 3.04 5.47 7v7.44h-4.55v-6.6c0-1.57-.03-3.6-2.2-3.6-2.2 0-2.54 1.72-2.54 3.5V22H7.28V8z",
  };
  const followers = stats.find((s) => /follower/i.test(s.label));
  return (
    <div className="bg-[#f3f5f8] border-t border-gray-200">
      <div className="max-w-[1400px] mx-auto px-6 py-4 flex items-center gap-4 flex-wrap">
        <span className="text-xs font-bold tracking-wider text-brand-ink">STAY CONNECTED</span>
        <div className="flex items-center gap-3">
          {socials.filter((s) => PATHS[s.id]).map((s) => (
            <a key={s.id} href={s.url} target="_blank" rel="noopener noreferrer" aria-label={s.label} className="w-7 h-7 flex items-center justify-center text-gray-700 hover:text-[#3684bf] cursor-pointer">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d={PATHS[s.id]} /></svg>
            </a>
          ))}
        </div>
        {followers && <span className="text-xs text-brand-muted ml-auto sm:ml-2">Over {followers.value}{followers.suffix} Followers</span>}
      </div>
    </div>
  );
}
