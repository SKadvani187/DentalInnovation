// Generic data hooks: fetch a collection from the API. All content is DB-only;
// collections start empty and fill once the API responds. Returns { data, loading, source }.
import { useEffect, useState } from "react";
import api from "../lib/api";

function useCollection(fetcher, staticData = []) {
  const [data, setData] = useState(staticData);
  const [loading, setLoading] = useState(true);
  const [source, setSource] = useState("static");

  useEffect(() => {
    let alive = true;
    fetcher()
      .then((rows) => {
        if (!alive) return;
        // API responded = authoritative. Use its result even when EMPTY, so deleting all
        // rows in admin actually clears them on the storefront (no stale static fallback).
        // Static data is only a fallback for when the API itself fails (offline) — see catch.
        if (Array.isArray(rows)) {
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

export const useProducts = () => useCollection(() => api.products());
export const useCombos = () => useCollection(() => api.combos());
export const useEvents = () => useCollection(() => api.events());
export const useOffers = () => useCollection(() => api.offers());
export const useCategories = () => useCollection(() => api.categories());
export const useTestimonials = () => useCollection(() => api.testimonials());

// Per-product FAQs, by product slug. Empty array when none.
export function useFaqs(slug) {
  const [faqs, setFaqs] = useState([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    let alive = true;
    if (!slug) { setLoading(false); return; }
    setLoading(true);
    api.faqs(slug)
      .then((rows) => { if (alive) setFaqs(Array.isArray(rows) ? rows : []); })
      .catch((err) => console.warn("[api] faqs fallback:", err.message))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [slug]);
  return { faqs, loading };
}

// Per-product answered Q&A, by product slug. Returns { questions, loading, reload }.
export function useQuestions(slug) {
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [tick, setTick] = useState(0);
  useEffect(() => {
    let alive = true;
    if (!slug) { setLoading(false); return; }
    setLoading(true);
    api.questions(slug)
      .then((rows) => { if (alive) setQuestions(Array.isArray(rows) ? rows : []); })
      .catch((err) => console.warn("[api] questions fallback:", err.message))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [slug, tick]);
  return { questions, loading, reload: () => setTick((t) => t + 1) };
}

// Product reviews (approved) + aggregate summary, by product slug.
// Returns { reviews, summary, loading, reload }. summary = { avg, count, distribution }.
export function useReviews(slug) {
  const emptySummary = { avg: 0, count: 0, distribution: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 } };
  const [reviews, setReviews] = useState([]);
  const [summary, setSummary] = useState(emptySummary);
  const [loading, setLoading] = useState(true);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let alive = true;
    if (!slug) { setLoading(false); return; }
    setLoading(true);
    api.reviews(slug)
      .then((j) => {
        if (!alive) return;
        setReviews(Array.isArray(j.reviews) ? j.reviews : []);
        setSummary(j.summary || emptySummary);
      })
      .catch((err) => console.warn("[api] reviews fallback:", err.message))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, tick]);

  return { reviews, summary, loading, reload: () => setTick((t) => t + 1) };
}

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
