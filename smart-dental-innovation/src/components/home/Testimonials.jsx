import { useCallback, useEffect, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";
import { testimonials } from "../../data/testimonials";

export default function Testimonials() {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: true, align: "start" });
  const [selected, setSelected] = useState(0);

  const scrollTo = useCallback((i) => emblaApi?.scrollTo(i), [emblaApi]);

  useEffect(() => {
    if (!emblaApi) return;
    const onSelect = () => setSelected(emblaApi.selectedScrollSnap());
    emblaApi.on("select", onSelect);
    onSelect();
    const id = setInterval(() => emblaApi.scrollNext(), 6000);
    return () => {
      emblaApi.off("select", onSelect);
      clearInterval(id);
    };
  }, [emblaApi]);

  return (
    <section className="bg-brand-navy text-white py-12 sm:py-16 mt-10">
      <div className="max-w-[1400px] mx-auto px-3 sm:px-6">
        <div className="text-center mb-8">
          <p className="text-xs uppercase tracking-[0.22em] text-brand-orange font-semibold mb-2">Stay Connected</p>
          <h2 className="text-2xl sm:text-4xl font-bold mb-2">203k+ Dental Professionals Trust Us</h2>
          <p className="text-white/70 text-sm sm:text-base">Hear what practicing dentists say about our products</p>
        </div>

        <div className="embla" ref={emblaRef}>
          <div className="embla__container">
            {testimonials.map((t) => (
              <div key={t.id} className="embla__slide px-2 sm:px-3" style={{ flex: "0 0 100%", maxWidth: "100%" }}>
                <div className="bg-white text-brand-ink rounded-2xl p-6 sm:p-8 flex flex-col md:flex-row gap-5 sm:gap-7 max-w-3xl mx-auto">
                  <img src={t.productImage} alt="" className="w-full md:w-40 h-40 object-cover rounded-xl shrink-0" />
                  <div className="flex flex-col">
                    <div className="flex text-brand-amber mb-2">
                      {Array.from({ length: 5 }).map((_, i) => (
                        <svg key={i} width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 6.91-1.01z" />
                        </svg>
                      ))}
                    </div>
                    <p className="text-sm sm:text-base text-brand-ink/85 mb-4 leading-relaxed">"{t.text}"</p>
                    <div className="mt-auto flex items-center gap-3">
                      <img src={t.avatar} alt={t.name} className="w-10 h-10 rounded-full object-cover" />
                      <div>
                        <p className="font-semibold text-sm">{t.name}</p>
                        <p className="text-xs text-brand-muted">Verified Buyer</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="flex justify-center gap-2 mt-6">
          {testimonials.map((_, i) => (
            <button
              key={i}
              onClick={() => scrollTo(i)}
              aria-label={`Review ${i + 1}`}
              className={`h-2 rounded-full transition-all ${selected === i ? "w-8 bg-brand-orange" : "w-2 bg-white/40"}`}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
