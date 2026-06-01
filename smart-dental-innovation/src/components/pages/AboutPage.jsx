import { useEffect, useRef, useState } from "react";
import { useUI } from "../../context/UIContext";

export default function AboutPage() {
  return (
    <div>
      <HeroSection />
      <OurStory />
      <StatsStrip />
      <Milestones />
      <CoreValues />
      <Leadership />
      <WhyTrust />
      <MissionVision />
      <Testimonials />
      <Certifications />
      <WhatWeOffer />
      <ReadyCTA />
      <ContactStrip />
      <SocialStrip />
    </div>
  );
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

function Milestones() {
  const items = [
    { year: "2019", title: "Founded in Surat", text: "Started as a small dental supply venture with a focus on imported handpieces." },
    { year: "2021", title: "1,000+ products", text: "Catalogue expanded to cover Endodontics, Implantology, Restorative & more." },
    { year: "2023", title: "Pan-India shipping", text: "Reached 500+ pincodes with reliable 5–7 day delivery and free shipping ₹20k+." },
    { year: "2025", title: "Division of Younique", text: "Joined Younique Dental Innovations to scale manufacturer-direct sourcing." },
    { year: "2026", title: "1000+ clinics served", text: "Trusted partner to over a thousand dental clinics across 28 states." },
  ];
  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>Our Journey</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>
            Milestones <span className="italic text-[#5fb6ff]">that matter</span>
          </h2>
          <p className="mt-3 text-brand-muted max-w-xl mx-auto">
            From a small Surat office to serving clinics nationwide — here's how far we've come.
          </p>
        </div>

        <div className="relative">
          <div className="hidden md:block absolute left-1/2 top-0 bottom-0 w-[2px] bg-gradient-to-b from-[#5fb6ff] via-[#3684bf] to-[#0b1d3a]" />
          <ol className="space-y-8 md:space-y-12">
            {items.map((m, i) => {
              const left = i % 2 === 0;
              return (
                <li key={m.year} className="md:grid md:grid-cols-2 md:gap-10 items-center">
                  <div className={`${left ? "md:order-1 md:text-right md:pr-10" : "md:order-2 md:pl-10"}`}>
                    <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:shadow-lg hover:border-[#3684bf] transition inline-block max-w-md">
                      <div className="text-[11px] font-bold uppercase tracking-wider text-[#3684bf] mb-1">{m.year}</div>
                      <h3 className="text-lg font-bold text-brand-ink mb-1">{m.title}</h3>
                      <p className="text-sm text-brand-muted leading-relaxed">{m.text}</p>
                    </div>
                  </div>
                  <div className={`hidden md:flex ${left ? "md:order-2" : "md:order-1 justify-end"} relative items-center`}>
                    <div className={`absolute ${left ? "left-0" : "right-0"} w-5 h-5 rounded-full bg-[#3684bf] border-4 border-white shadow-md z-10 -translate-x-1/2`} style={left ? { left: 0, transform: "translateX(-50%)" } : { right: 0, transform: "translateX(50%)" }} />
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

function Leadership() {
  const team = [
    { name: "Dr. Rakesh Patel", role: "Founder & CEO", bio: "20+ years in dental supplies. Vision-driven leader.", img: "https://merchant-cdn.storedum.com/ai_img_44.png" },
    { name: "Dr. Priya Shah", role: "Chief Clinical Officer", bio: "BDS, MDS Endodontics. Curates clinical product range.", img: "https://merchant-cdn.storedum.com/ai_img_40_(5).png" },
    { name: "Hiren Mehta", role: "Head of Operations", bio: "10+ years logistics & supply chain expertise.", img: "https://merchant-cdn.storedum.com/ai_img_42_(1).png" },
    { name: "Ankit Joshi", role: "Customer Success Lead", bio: "Ensures every clinic gets white-glove support.", img: "https://merchant-cdn.storedum.com/ai_img_31_(2).png" },
  ];
  return (
    <section className="bg-[#eef5fb] py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>The People</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>
            Meet the <span className="italic text-[#5fb6ff]">Team</span>
          </h2>
          <p className="mt-3 text-brand-muted max-w-xl mx-auto">
            Clinicians, engineers, and operators working to make modern dentistry accessible.
          </p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          {team.map((p) => (
            <div key={p.name} className="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all">
              <div className="aspect-[4/3] bg-gradient-to-br from-[#0b1d3a] to-[#3684bf] flex items-center justify-center overflow-hidden">
                <img src={p.img} alt={p.name} className="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-transform duration-300" />
              </div>
              <div className="p-5">
                <h3 className="font-bold text-brand-ink">{p.name}</h3>
                <p className="text-xs font-semibold text-[#3684bf] uppercase tracking-wider mb-2">{p.role}</p>
                <p className="text-sm text-brand-muted leading-relaxed">{p.bio}</p>
                <div className="mt-3 flex items-center gap-2">
                  {["linkedin", "email"].map((s) => (
                    <span key={s} className="w-7 h-7 rounded-full bg-gray-100 hover:bg-[#3684bf] hover:text-white flex items-center justify-center text-gray-500 cursor-pointer transition">
                      {s === "linkedin" ? (
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.44-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29zM5.34 7.43a2.06 2.06 0 11-.01-4.13 2.06 2.06 0 01.01 4.13zM7.12 20.45H3.55V9h3.57v11.45z" /></svg>
                      ) : (
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
                      )}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function Testimonials() {
  const reviews = [
    {
      name: "Dr. Amit Sharma",
      clinic: "Sharma Dental Care, Mumbai",
      stars: 5,
      text: "Switched all my supplies to SDI last year. Their handpieces are buttery smooth and warranty claims took 3 days — fastest I've ever seen.",
    },
    {
      name: "Dr. Kavita Reddy",
      clinic: "Smile Studio, Hyderabad",
      stars: 5,
      text: "The bulk pricing for our new clinic setup saved us nearly ₹2L. Sales team understood exactly what a fresh clinic needs.",
    },
    {
      name: "Dr. Ravi Kumar",
      clinic: "Kumar Family Dentistry, Pune",
      stars: 4,
      text: "RF Cautery they recommended is a game-changer. Patients heal faster and procedures are cleaner. Wish I'd switched sooner.",
    },
    {
      name: "Dr. Neha Iyer",
      clinic: "Iyer Endodontics, Chennai",
      stars: 5,
      text: "Endodontic files are top quality and delivery is always on time. Customer support actually answers — rare these days.",
    },
  ];
  const [idx, setIdx] = useState(0);
  useEffect(() => {
    const t = setInterval(() => setIdx((i) => (i + 1) % reviews.length), 6000);
    return () => clearInterval(t);
  }, [reviews.length]);

  const r = reviews[idx];

  return (
    <section className="bg-white py-16 lg:py-24 relative overflow-hidden">
      <div className="absolute top-0 left-0 w-72 h-72 bg-[#3684bf]/5 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2 pointer-events-none" />
      <div className="absolute bottom-0 right-0 w-80 h-80 bg-[#5fb6ff]/5 rounded-full blur-3xl translate-x-1/2 translate-y-1/2 pointer-events-none" />

      <div className="max-w-[900px] mx-auto px-4 text-center relative">
        <SectionLabel>Trusted by Dentists</SectionLabel>
        <h2 className="mt-4 text-3xl lg:text-4xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>
          What clinicians <span className="italic text-[#5fb6ff]">say</span>
        </h2>

        <div className="mt-10 bg-white border border-gray-100 rounded-2xl p-8 sm:p-10 shadow-md relative">
          <svg className="absolute top-5 left-5 w-10 h-10 text-[#5fb6ff]/40" viewBox="0 0 24 24" fill="currentColor">
            <path d="M14 17h3l2-4V7h-6v6h3zM6 17h3l2-4V7H5v6h3z" />
          </svg>
          <div className="flex justify-center items-center gap-1 mb-4">
            {[...Array(5)].map((_, i) => (
              <svg key={i} width="20" height="20" viewBox="0 0 24 24" fill={i < r.stars ? "#fbbf24" : "#e5e7eb"}>
                <path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
              </svg>
            ))}
          </div>
          <p className="text-base sm:text-lg text-brand-ink leading-relaxed font-medium">
            "{r.text}"
          </p>
          <div className="mt-6">
            <p className="font-bold text-brand-ink">{r.name}</p>
            <p className="text-xs text-brand-muted">{r.clinic}</p>
          </div>
        </div>

        <div className="flex justify-center items-center gap-2 mt-6">
          {reviews.map((_, i) => (
            <button
              key={i}
              onClick={() => setIdx(i)}
              aria-label={`Review ${i + 1}`}
              className={`h-2 rounded-full transition-all ${i === idx ? "w-8 bg-[#3684bf]" : "w-2 bg-gray-300 hover:bg-gray-400"}`}
            />
          ))}
        </div>
      </div>
    </section>
  );
}

function Certifications() {
  const items = [
    { label: "ISO 13485:2016", desc: "Medical Device Quality", icon: "📋" },
    { label: "CE Marked", desc: "European Conformity", icon: "✅" },
    { label: "FDA Listed", desc: "US Compliance", icon: "🇺🇸" },
    { label: "MDR Compliant", desc: "EU Medical Device Reg.", icon: "🛡️" },
    { label: "GST Registered", desc: "Tax-compliant invoicing", icon: "📄" },
    { label: "Made in India", desc: "Atmanirbhar Bharat", icon: "🇮🇳" },
  ];
  return (
    <section className="bg-[#0b1d3a] py-12 lg:py-16">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-8">
          <SectionLabel>Certified & Trusted</SectionLabel>
          <h2 className="mt-4 text-2xl lg:text-3xl font-bold text-white" style={{ fontFamily: "'El Messiri', serif" }}>
            Quality you can <span className="italic text-[#5fb6ff]">verify</span>
          </h2>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
          {items.map((c) => (
            <div key={c.label} className="bg-[#0f2547]/80 border border-[#1f3a66] rounded-xl p-4 text-center hover:border-[#5fb6ff] transition group">
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

function ContactStrip() {
  const items = [
    { label: "VISIT US", value: "Third Floor, Swastik Plaza, Varachha, Surat, Gujarat 395006", icon: <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" /><circle cx="12" cy="10" r="3" /></> },
    { label: "CALL US", value: "+91 92653 18584", icon: <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" /> },
    { label: "EMAIL US", value: "info@smartdentalinnovations.com", icon: <><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" /></> },
  ];
  return (
    <div className="bg-[#0b1d3a] text-white">
      <div className="max-w-[1400px] mx-auto px-6 py-8 grid grid-cols-1 sm:grid-cols-3 gap-6 lg:divide-x lg:divide-[#1f3a66]">
        {items.map((c) => (
          <div key={c.label} className="flex items-center gap-4 px-4">
            <div className="w-12 h-12 rounded-lg bg-[#0f2547] border border-[#1f3a66] flex items-center justify-center text-[#5fb6ff] shrink-0">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                {c.icon}
              </svg>
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
  const socials = [
    { l: "Facebook", p: "M22 12a10 10 0 10-11.6 9.87v-6.99H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.77-3.9 1.1 0 2.24.2 2.24.2v2.46h-1.27c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.88h-2.34v6.99A10 10 0 0022 12z" },
    { l: "Instagram", p: "M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm5 5a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6zm5.5-3a1 1 0 100 2 1 1 0 000-2z" },
    { l: "YouTube", p: "M21.58 7.19a2.51 2.51 0 00-1.77-1.77C18.25 5 12 5 12 5s-6.25 0-7.81.42A2.51 2.51 0 002.42 7.19C2 8.75 2 12 2 12s0 3.25.42 4.81a2.51 2.51 0 001.77 1.77C5.75 19 12 19 12 19s6.25 0 7.81-.42a2.51 2.51 0 001.77-1.77C22 15.25 22 12 22 12s0-3.25-.42-4.81zM10 15V9l5 3-5 3z" },
    { l: "Google", p: "M21.35 11.1H12v2.85h5.35c-.23 1.5-1.7 4.4-5.35 4.4-3.22 0-5.85-2.66-5.85-5.95s2.63-5.95 5.85-5.95c1.83 0 3.06.78 3.76 1.45l2.56-2.47C16.79 3.95 14.6 3 12 3 6.99 3 3 6.99 3 12s3.99 9 9 9c5.2 0 8.65-3.66 8.65-8.8 0-.6-.07-1.05-.16-1.5z" },
    { l: "LinkedIn", p: "M4.98 3.5C4.98 4.88 3.88 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.22 8h4.56v14H.22V8zm7.06 0h4.37v1.92h.06c.61-1.15 2.1-2.36 4.32-2.36 4.62 0 5.47 3.04 5.47 7v7.44h-4.55v-6.6c0-1.57-.03-3.6-2.2-3.6-2.2 0-2.54 1.72-2.54 3.5V22H7.28V8z" },
  ];
  return (
    <div className="bg-[#f3f5f8] border-t border-gray-200">
      <div className="max-w-[1400px] mx-auto px-6 py-4 flex items-center gap-4 flex-wrap">
        <span className="text-xs font-bold tracking-wider text-brand-ink">STAY CONNECTED</span>
        <div className="flex items-center gap-3">
          {socials.map((s) => (
            <a key={s.l} aria-label={s.l} className="w-7 h-7 flex items-center justify-center text-gray-700 hover:text-[#3684bf] cursor-pointer">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d={s.p} /></svg>
            </a>
          ))}
        </div>
        <span className="text-xs text-brand-muted ml-auto sm:ml-2">Over 203k+ Followers</span>
      </div>
    </div>
  );
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
  return (
    <section className="relative overflow-hidden bg-[#0b1d3a] text-white">
      <div className="absolute inset-0 pointer-events-none opacity-30"
        style={{
          backgroundImage:
            "linear-gradient(rgba(54,132,191,0.15) 1px, transparent 1px), linear-gradient(90deg, rgba(54,132,191,0.15) 1px, transparent 1px)",
          backgroundSize: "40px 40px",
        }}
      />

      <div className="max-w-[1400px] mx-auto px-4 py-16 lg:py-24 grid grid-cols-1 lg:grid-cols-2 gap-10 relative">
        <div>
          <span className="inline-flex items-center gap-2 border border-[#3684bf] rounded-full px-4 py-1.5 text-xs font-bold tracking-wider text-[#5fb6ff]">
            <span className="w-1.5 h-1.5 rounded-full bg-[#5fb6ff]" />
            ABOUT SMART DENTAL INNOVATIONS
          </span>

          <h1 className="mt-6 text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>
            Innovating <br />
            <span className="italic text-[#5fb6ff]">Dentistry</span>,<br />
            One Tool at a Time
          </h1>

          <p className="mt-6 text-base text-gray-300 max-w-xl leading-relaxed">
            A division of Younique Dental Innovations, we are Surat's premier destination for advanced dental products — designed to empower clinicians, elevate care, and deliver clinical excellence in every procedure.
          </p>

          <button className="mt-8 inline-flex items-center gap-2 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold px-6 py-3 rounded-lg transition shadow-lg">
            Explore Our Products
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
              <path d="M5 12h14M13 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <div className="lg:pl-10">
          <div className="bg-[#0f2547]/60 border border-[#1f3a66] backdrop-blur-sm rounded-2xl p-8">
            <h3 className="text-2xl font-bold mb-2">
              Smart <span className="text-[#5fb6ff]">Dental</span> Innovations
            </h3>
            <div className="h-px bg-[#1f3a66] mb-6" />

            <div className="grid grid-cols-2 gap-4">
              <HeroStat value="1000+" label="Products" />
              <HeroStat value="203k+" label="Followers" />
              <HeroStat value="4.5★" label="Avg Rating" />
              <HeroStat value="100%" label="Original" />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function HeroStat({ value, label }) {
  return (
    <div className="bg-[#0a1f3f]/60 border border-[#1f3a66] rounded-xl px-5 py-6 text-center">
      <div className="text-3xl font-bold text-[#7ec9ff]" style={{ fontFamily: "'El Messiri', serif" }}>
        {value}
      </div>
      <div className="text-sm text-gray-300 mt-1">{label}</div>
    </div>
  );
}

function OurStory() {
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
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#5fb6ff" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    {t.icon}
                  </svg>
                  <span className="text-xs text-white mt-2 font-medium">{t.label}</span>
                </div>
              ))}
            </div>
          </div>
          <div className="absolute -bottom-8 right-8 lg:right-16 bg-[#5fb6ff] text-[#0b1d3a] rounded-xl px-6 py-4 shadow-2xl">
            <div className="text-xl font-bold" style={{ fontFamily: "'El Messiri', serif" }}>A Division Of</div>
            <div className="text-xs font-semibold">Younique Dental Innovations</div>
          </div>
        </div>

        <div>
          <SectionLabel>Our Story</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>
            Built for <span className="italic text-[#5fb6ff]">Dentists</span>
            <br />
            Who Think Ahead
          </h2>
          <p className="mt-6 text-brand-muted leading-relaxed">
            Smart Dental Innovations was born from a simple vision — to make premium, innovative dental tools accessible to every clinician across India. As a proud division of Younique Dental Innovations, we combine world-class engineering with a deep understanding of the dental profession.
          </p>
          <p className="mt-4 text-brand-muted leading-relaxed">
            From our base in Surat, Gujarat, we serve dental professionals nationwide with over 1,000+ carefully curated products — from RF Cautery units and implant systems to handpieces, burs, and clinic setup essentials.
          </p>

          <div className="mt-6 space-y-3">
            <PromiseRow title="100% Original Products" text="Every product is authentic, quality-checked, and backed by manufacturer warranties." />
            <PromiseRow title="Expert-Curated Range" text="Products selected by dental professionals for dental professionals — no compromises." />
            <PromiseRow title="Dedicated Support Team" text="Responsive support before, during, and after every order." />
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
  const items = [
    { target: 1000, suffix: "+", label: "Dental Products", format: (v) => v.toLocaleString("en-IN"), icon: <><rect x="3" y="5" width="18" height="13" rx="1.5" /><path d="M3 18l4 3h10l4-3" /></> },
    { target: 203, suffix: "k+", label: "Social Followers", format: (v) => v.toLocaleString("en-IN"), icon: <><circle cx="9" cy="8" r="3" /><circle cx="17" cy="9" r="2.5" /><path d="M3 20c0-3 3-5 6-5s6 2 6 5M14 20c0-2 2-4 5-4" /></> },
    { target: 45, suffix: "★", label: "Average Rating", format: (v) => (v / 10).toFixed(1), icon: <path d="M12 2l3 6.3 7 1-5 4.9 1.2 7L12 17.8 5.8 21.2 7 14.2l-5-4.9 7-1z" /> },
    { target: 100, suffix: "%", label: "Original & Verified", format: (v) => v.toString(), icon: <path d="M12 2L3 6v6c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V6l-9-4z" /> },
  ];
  return (
    <section className="bg-[#0b1d3a] py-14">
      <div className="max-w-[1400px] mx-auto px-4 grid grid-cols-2 lg:grid-cols-4 gap-6 lg:divide-x lg:divide-[#1f3a66]">
        {items.map((s) => (
          <CountStat key={s.label} stat={s} />
        ))}
      </div>
    </section>
  );
}

function CountStat({ stat }) {
  const [ref, val] = useCountUp(stat.target, 1800);
  return (
    <div ref={ref} className="text-center px-4">
      <div className="inline-flex w-12 h-12 rounded-lg bg-[#0f2547] border border-[#1f3a66] items-center justify-center mb-4 text-[#5fb6ff]">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          {stat.icon}
        </svg>
      </div>
      <div className="text-4xl font-bold text-white tabular-nums" style={{ fontFamily: "'El Messiri', serif" }}>
        {stat.format(val)}<span className="text-[#5fb6ff]">{stat.suffix}</span>
      </div>
      <div className="text-sm text-gray-300 mt-1">{stat.label}</div>
    </div>
  );
}

function CoreValues() {
  const values = [
    { n: "01", title: "Innovation First", text: "We continuously scout and introduce cutting-edge dental technologies so your clinic stays ahead of the curve — always.", icon: "🏛", color: "bg-blue-100" },
    { n: "02", title: "Clinical Excellence", text: "Every product is rigorously evaluated for clinical performance. We only offer what we'd use in our own practice.", icon: "🎯", color: "bg-gray-100" },
    { n: "03", title: "Dentist Partnership", text: "We're not just a store — we're a partner in your growth. Bulk pricing, expert guidance, and a team that truly understands dentistry.", icon: "🤝", color: "bg-yellow-100" },
    { n: "04", title: "Smart Value", text: "Premium quality at fair prices. We work directly with manufacturers and innovators to ensure you get the best deal, always.", icon: "⚡", color: "bg-blue-100" },
    { n: "05", title: "Reliability & Warranty", text: "Backed by manufacturer warranties, every product we sell is built to last. Our after-sales support is second to none.", icon: "🛡", color: "bg-gray-100" },
    { n: "06", title: "Continuous Growth", text: "We grow when our dentists grow. Your success is our metric — and we're committed to growing alongside you, every step of the way.", icon: "🚀", color: "bg-orange-100" },
  ];

  return (
    <section className="bg-[#eef5fb] py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>What We Stand For</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>
            Our Core <span className="italic text-[#5fb6ff]">Values</span>
          </h2>
          <p className="mt-4 text-brand-muted max-w-xl mx-auto">
            Every decision we make is guided by principles that put dentists and patient care first.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-7">
          {values.map((v) => (
            <div
              key={v.n}
              className="core-value-card group relative bg-white border border-gray-100 rounded-2xl p-8 lg:p-10 min-h-[280px] shadow-sm cursor-pointer overflow-hidden flex flex-col"
            >
              <span className="core-value-accent absolute top-0 left-0 right-0 h-[3px] bg-[#5fb6ff] origin-left scale-x-0 transition-transform duration-300 group-hover:scale-x-100" />
              <div className={`w-14 h-14 rounded-lg ${v.color} flex items-center justify-center text-2xl mb-6 transition-transform duration-300 group-hover:scale-110`}>
                {v.icon}
              </div>
              <h3 className="text-xl font-bold text-brand-ink mb-3 transition-colors duration-300 group-hover:text-[#3684bf]">{v.title}</h3>
              <p className="text-sm text-brand-muted leading-relaxed flex-1">{v.text}</p>
              <span className="absolute bottom-5 right-7 text-5xl font-bold text-gray-100 select-none transition-colors duration-300 group-hover:text-[#cfe5f5]" style={{ fontFamily: "'El Messiri', serif" }}>
                {v.n}
              </span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function WhyTrust() {
  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
        <div>
          <SectionLabel>Why Smart Dental</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>
            Why Dentists <span className="italic text-[#5fb6ff]">Trust</span> Us
          </h2>
          <p className="mt-5 text-brand-muted leading-relaxed">
            Thousands of dental professionals across India choose Smart Dental Innovations for one simple reason — we deliver exactly what we promise.
          </p>

          <div className="mt-8 space-y-0">
            <TrustRow icon="check" title="Curated, Not Just Listed" text="Every product in our catalog has been assessed for clinical utility, quality, and value before being offered to you." />
            <TrustRow icon="shield" title="Warranty-Backed Products" text="From 6-month to 2-year warranties, shop with confidence knowing every major product is protected." />
            <TrustRow icon="clock" title="Fast & Secure Delivery" text="Reliable shipping with secure packaging — your instruments arrive safely, on time, ready to use." />
            <TrustRow icon="chat" title="Expert Customer Support" text="Talk to real dental professionals on our team, Monday to Saturday from 9 AM to 7 PM." />
            <TrustRow icon="dollar" title="Bulk & Clinic Pricing" text="Setting up a new clinic or stocking up? Contact us for special bulk pricing tailored to your needs." />
          </div>
        </div>

        <div className="relative lg:pl-8">
          <div className="hidden lg:block absolute right-0 top-20 w-[85%] h-[420px] bg-[#0b1d3a] rounded-2xl overflow-hidden">
            <span className="absolute inset-0 flex items-end justify-center pb-10 text-[120px] font-bold text-[#142a4f] select-none" style={{ fontFamily: "'El Messiri', serif" }}>
              SDI
            </span>
          </div>
          <div className="relative bg-white border border-gray-100 rounded-2xl p-6 shadow-xl z-10 lg:max-w-md lg:ml-0">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-12 h-12 rounded-lg bg-[#3684bf] flex items-center justify-center text-white">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 2l3 6.3 7 1-5 4.9 1.2 7L12 17.8 5.8 21.2 7 14.2l-5-4.9 7-1z" /></svg>
              </div>
              <div>
                <h3 className="font-bold text-brand-ink">Customer Satisfaction</h3>
                <p className="text-xs text-brand-muted">Based on verified reviews</p>
              </div>
            </div>
            <div className="bg-[#eaf4fc] rounded-lg px-4 py-3 flex items-center gap-2 mb-5">
              <span className="text-yellow-500 text-lg">★★★★½</span>
              <span className="font-bold text-brand-ink text-sm">4.5 / 5</span>
              <span className="text-sm text-brand-muted">— Average Rating</span>
            </div>

            <SatBar label="Product Quality" value={96} />
            <SatBar label="Delivery Speed" value={91} />
            <SatBar label="Value for Money" value={94} />
            <SatBar label="Customer Support" value={89} />
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
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">{icons[icon]}</svg>
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
  return (
    <section className="bg-[#0b1d3a] text-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>Our Direction</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold" style={{ fontFamily: "'El Messiri', serif" }}>
            Mission & <span className="italic text-[#5fb6ff]">Vision</span>
          </h2>
          <p className="mt-4 text-gray-300 max-w-2xl mx-auto leading-relaxed">
            We're on a mission to redefine how dental professionals access, experience, and benefit from modern dental technology.
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="mv-card group bg-gradient-to-br from-[#0f2547] to-[#13335f] border border-[#1f3a66] rounded-2xl p-8 lg:p-10 cursor-pointer">
            <div className="w-14 h-14 rounded-lg bg-[#0a1f3f]/70 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
              <span className="text-3xl">🎯</span>
            </div>
            <h3 className="text-2xl font-bold mb-3 transition-colors duration-300 group-hover:text-[#5fb6ff]" style={{ fontFamily: "'El Messiri', serif" }}>Our Mission</h3>
            <p className="text-gray-300 leading-relaxed">
              To provide every dental professional in India with access to innovative, reliable, and affordable dental products — paired with expert guidance and support — so they can deliver exceptional patient care without compromise. We strive to be the most trusted dental supply partner in the country.
            </p>
          </div>

          <div className="mv-card group bg-gradient-to-br from-[#0f2547] to-[#13335f] border-l-4 border-[#5fb6ff] border-y border-r border-[#1f3a66] rounded-2xl p-8 lg:p-10 cursor-pointer">
            <div className="w-14 h-14 rounded-lg bg-[#0a1f3f]/70 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
              <span className="text-3xl">🔭</span>
            </div>
            <h3 className="text-2xl font-bold mb-3 transition-colors duration-300 group-hover:text-[#5fb6ff]" style={{ fontFamily: "'El Messiri', serif" }}>Our Vision</h3>
            <p className="text-gray-300 leading-relaxed">
              To become India's leading platform for smart dental innovation — where dentists discover tomorrow's tools today. We envision a future where every clinic, whether in a metro or a small town, has equal access to world-class dental technology that transforms patient outcomes.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

function WhatWeOffer() {
  const { navigate } = useUI();
  const items = [
    { label: "Unique Products", icon: "💡", category: "unique" },
    { label: "Restorative", icon: "🦷", category: "restorative" },
    { label: "Endodontics", icon: "🔧", category: "endodontics" },
    { label: "Implantology", icon: "🪛", category: "implantology" },
    { label: "Mirrors", icon: "🪞", category: "mirrors" },
    { label: "Handpiece", icon: "⚙️", category: "handpiece" },
    { label: "Implant Components", icon: "🔩", category: "implant-component" },
    { label: "Dental Burs", icon: "💎", category: "burs" },
    { label: "Accessories", icon: "🧰", category: "accessories" },
    { label: "Smartmed Scrub", icon: "🥼", category: "scrub" },
    { label: "New Clinic Setup", icon: "🏥", category: "clinic-setup" },
    { label: "New Arrivals", icon: "🆕", category: "new" },
  ];

  return (
    <section className="bg-white py-16 lg:py-24">
      <div className="max-w-[1400px] mx-auto px-4">
        <div className="text-center mb-12">
          <SectionLabel>Our Range</SectionLabel>
          <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink" style={{ fontFamily: "'El Messiri', serif" }}>
            What We <span className="italic text-[#5fb6ff]">Offer</span>
          </h2>
          <p className="mt-4 text-brand-muted max-w-xl mx-auto">
            A complete ecosystem of dental tools across every specialty and clinical need.
          </p>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          {items.map((c) => (
            <button
              key={c.label}
              onClick={() => navigate("category", { category: c.category, title: c.label })}
              className="offer-tile group flex items-center gap-3 bg-[#eef5fb] border border-transparent rounded-xl px-5 py-4 text-left cursor-pointer"
            >
              <span className="text-xl shrink-0 transition-transform duration-300 group-hover:scale-125">{c.icon}</span>
              <span className="font-semibold text-brand-ink text-sm transition-colors duration-300 group-hover:text-[#3684bf]">{c.label}</span>
            </button>
          ))}
        </div>
      </div>
    </section>
  );
}

function ReadyCTA() {
  const { navigate } = useUI();
  return (
    <section className="bg-[#eef5fb] py-16 lg:py-24">
      <div className="max-w-[900px] mx-auto px-4 text-center">
        <SectionLabel>Get Started</SectionLabel>
        <h2 className="mt-4 text-4xl lg:text-5xl font-bold text-brand-ink leading-tight" style={{ fontFamily: "'El Messiri', serif" }}>
          Ready to Elevate Your
          <br />
          <span className="italic text-[#5fb6ff]">Dental Practice?</span>
        </h2>
        <p className="mt-5 text-brand-muted max-w-xl mx-auto leading-relaxed">
          Join thousands of dental professionals who trust Smart Dental Innovations for quality products, fair prices, and genuine expertise.
        </p>

        <div className="mt-8 flex items-center justify-center gap-3 flex-wrap">
          <button
            onClick={() => navigate("category")}
            className="cta-btn cta-primary inline-flex items-center gap-2 bg-[#0b1d3a] text-white font-bold px-7 py-3.5 rounded-full shadow-lg hover:bg-[#13294f] transition-colors"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" /></svg>
            Shop Now
          </button>
          <button
            onClick={() => navigate("contact")}
            className="cta-btn cta-outline inline-flex items-center gap-2 border border-gray-300 text-brand-ink font-bold px-7 py-3.5 rounded-full bg-white hover:bg-gray-50 transition-colors"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z" /></svg>
            Contact Us
          </button>
        </div>
      </div>
    </section>
  );
}
