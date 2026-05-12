import { useCallback, useEffect, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";

const slides = [
  {
    eyebrow: "Where Innovation Meets Dental Excellence",
    title: "Upgrade Your Practice with Smart Dental Products",
    sub: "High-quality dental materials for precise, reliable, and better clinical results.",
    img: "https://picsum.photos/seed/dental-hero-1/1600/700",
    cta: "Shop Bestsellers",
  },
  {
    eyebrow: "Professional. Proven. Performance.",
    title: "Implantology Tools Engineered for Precision",
    sub: "Surgical kits, drivers, and abutments trusted by 5,000+ practices across India.",
    img: "https://picsum.photos/seed/dental-hero-2/1600/700",
    cta: "Explore Implantology",
  },
  {
    eyebrow: "New Arrivals — Limited Stock",
    title: "Endodontics Reinvented",
    sub: "Apex locators, NiTi rotary files, and bioceramic sealers for faster, predictable RCT.",
    img: "https://picsum.photos/seed/dental-hero-3/1600/700",
    cta: "View New Arrivals",
  },
];

export default function HeroCarousel() {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: true });
  const [selected, setSelected] = useState(0);

  const scrollPrev = useCallback(() => emblaApi?.scrollPrev(), [emblaApi]);
  const scrollNext = useCallback(() => emblaApi?.scrollNext(), [emblaApi]);
  const scrollTo = useCallback((i) => emblaApi?.scrollTo(i), [emblaApi]);

  useEffect(() => {
    if (!emblaApi) return;
    const onSelect = () => setSelected(emblaApi.selectedScrollSnap());
    emblaApi.on("select", onSelect);
    onSelect();
    const id = setInterval(() => emblaApi.scrollNext(), 5000);
    return () => {
      emblaApi.off("select", onSelect);
      clearInterval(id);
    };
  }, [emblaApi]);

  return (
    <section className="relative max-w-[1400px] mx-auto px-3 sm:px-6 pt-4">
      <div className="relative overflow-hidden rounded-2xl">
        <div className="embla" ref={emblaRef}>
          <div className="embla__container">
            {slides.map((s, i) => (
              <div key={i} className="embla__slide relative">
                <div className="relative h-[280px] sm:h-[420px] lg:h-[520px] w-full">
                  <img
                    src={s.img}
                    alt={s.title}
                    className="absolute inset-0 w-full h-full object-cover"
                    loading={i === 0 ? "eager" : "lazy"}
                  />
                  <div className="absolute inset-0 bg-gradient-to-r from-brand-navy/85 via-brand-navy/55 to-transparent" />
                  <div className="relative h-full flex items-center px-6 sm:px-12 lg:px-20 max-w-4xl">
                    <div className="text-white">
                      <p className="text-xs sm:text-sm uppercase tracking-[0.18em] text-brand-orange font-semibold mb-3">
                        {s.eyebrow}
                      </p>
                      <h1 className="text-2xl sm:text-4xl lg:text-5xl font-bold leading-tight mb-3 sm:mb-4">
                        {s.title}
                      </h1>
                      <p className="text-sm sm:text-base lg:text-lg text-white/85 max-w-xl mb-5 sm:mb-7">
                        {s.sub}
                      </p>
                      <button className="inline-flex items-center gap-2 px-5 sm:px-7 py-2.5 sm:py-3 bg-brand-orange hover:bg-brand-orange-light text-white text-sm sm:text-base font-semibold rounded-lg shadow-lg transition">
                        {s.cta}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" /></svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Arrows */}
        <button
          onClick={scrollPrev}
          aria-label="Previous"
          className="hidden sm:flex absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/30 backdrop-blur-md text-white items-center justify-center hover:bg-white/50 transition"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.77 3.77 16 2 6 12l10 10 1.77-1.77L9.54 12z" /></svg>
        </button>
        <button
          onClick={scrollNext}
          aria-label="Next"
          className="hidden sm:flex absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/30 backdrop-blur-md text-white items-center justify-center hover:bg-white/50 transition"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" className="rotate-180"><path d="M17.77 3.77 16 2 6 12l10 10 1.77-1.77L9.54 12z" /></svg>
        </button>

        {/* Dots */}
        <div className="absolute bottom-3 sm:bottom-5 left-1/2 -translate-x-1/2 flex gap-2">
          {slides.map((_, i) => (
            <button
              key={i}
              onClick={() => scrollTo(i)}
              aria-label={`Slide ${i + 1}`}
              className={`h-2 rounded-full transition-all ${selected === i ? "w-8 bg-brand-orange" : "w-2 bg-white/60"}`}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
