
import { useCallback, useEffect, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useProductIndex } from "../../hooks/useProductIndex";
import { useSettings } from "../../context/SettingsContext";

export default function HeroCarousel() {
  const navigate = useAppNavigate();
  const { nameFor } = useProductIndex();
  const { heroSlides } = useSettings();
  const slides = Array.isArray(heroSlides) ? heroSlides : [];
  const go = (id) => navigate("product", { id, name: nameFor(id) });
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
    <section className="relative max-w-[1400px] mx-auto px-3 sm:px-6 mt-[15px]">
      <div className="relative overflow-hidden rounded-[20px]">
        <div className="embla" ref={emblaRef}>
          <div className="embla__container">
            {slides.map((s, i) => (
              <div
                key={i}
                onClick={() => go(s.productId)}
                className="embla__slide relative min-w-0 w-full h-[400px] lg:h-[500px] overflow-hidden">
                <img
                  src={s.src}
                  alt=""
                  aria-hidden="true"
                  className="absolute inset-0 w-full h-full object-cover scale-110 blur-2xl opacity-70"
                />
                <img
                  src={s.src}
                  alt=""
                  loading={i === 0 ? "eager" : "lazy"}
                  decoding="async"
                  className="relative z-10 w-full h-full object-contain block"
                />
              </div>
            ))}
          </div>
        </div>

        {/* Arrows */}
        <button
          onClick={scrollPrev}
          aria-label="Previous"
          className="hidden sm:flex absolute left-4 top-1/2 -translate-y-1/2
             w-11 h-11 rounded-full
             bg-black/45 hover:bg-black/70
             backdrop-blur-md text-white
             ring-1 ring-white/25 shadow-lg
             items-center justify-center transition
             focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.77 3.77 16 2 6 12l10 10 1.77-1.77L9.54 12z" /></svg>
        </button>
        <button
          onClick={scrollNext}
          aria-label="Next"
          className="hidden sm:flex absolute left-4 top-1/2 -translate-y-1/2
             w-11 h-11 rounded-full
             bg-black/45 hover:bg-black/70
             backdrop-blur-md text-white
             ring-1 ring-white/25 shadow-lg
             items-center justify-center transition
             focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
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
