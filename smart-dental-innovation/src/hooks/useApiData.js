// Generic data hooks: fetch a collection from the API, fall back to static data.
// Keeps storefront working if API is down. Returns { data, loading, source }.
import { useEffect, useState } from "react";
import api from "../lib/api";

import { allProducts as sProducts } from "../data/products";
import { combos as sCombos } from "../data/combos";
import { events as sEvents } from "../data/events";
import { offerZone as sOffers } from "../data/offers";
import { categories as sCategories } from "../data/categories";
import { testimonials as sTestimonials } from "../data/testimonials";

function useCollection(fetcher, staticData) {
  const [data, setData] = useState(staticData);
  const [loading, setLoading] = useState(true);
  const [source, setSource] = useState("static");

  useEffect(() => {
    let alive = true;
    fetcher()
      .then((rows) => {
        if (!alive) return;
        if (Array.isArray(rows) && rows.length) {
          setData(rows);
          setSource("api");
        }
      })
      .catch((err) => console.warn("[api] fallback to static:", err.message))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, []);

  return { data, loading, source };
}

export const useProducts = () => useCollection(() => api.products(), sProducts);
export const useCombos = () => useCollection(() => api.combos(), sCombos);
export const useEvents = () => useCollection(() => api.events(), sEvents);
export const useOffers = () => useCollection(() => api.offers(), sOffers);
export const useCategories = () => useCollection(() => api.categories(), sCategories);
export const useTestimonials = () => useCollection(() => api.testimonials(), sTestimonials);

// Single product by slug, with static fallback via findProductById.
export function useProduct(slug, staticFallback) {
  const [product, setProduct] = useState(staticFallback || null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    if (!slug) { setLoading(false); return; }
    api.product(slug)
      .then((p) => { if (alive && p) setProduct(p); })
      .catch((err) => console.warn("[api] product fallback:", err.message))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [slug]);

  return { product, loading };
}
