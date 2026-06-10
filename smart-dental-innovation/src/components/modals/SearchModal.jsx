import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";
import { useProducts } from "../../hooks/useApiData";
import api from "../../lib/api";
import ProductCard from "../home/ProductCard";
import Footer from "../layout/Footer";

// Read a File as a base64 data URL (data:image/...;base64,....)
function fileToDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

export default function SearchModal() {
  const { modal, closeModal, searchSeed, setSearchSeed, searchImageFile, setSearchImageFile, showToast } = useUI();
  const { data: allProducts } = useProducts();
  const open = modal === "search";
  const [query, setQuery] = useState("");
  const [listening, setListening] = useState(false);
  const [imageSearch, setImageSearch] = useState(null); // { name, preview, tokens, aiQuery, loading }
  const inputRef = useRef(null);
  const fileRef = useRef(null);

  const startVoice = () => {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
      showToast("Voice search isn't supported in this browser. Try Chrome or Edge.", "error");
      return;
    }
    const rec = new SR();
    rec.lang = "en-IN";
    rec.interimResults = false;
    rec.maxAlternatives = 1;
    setListening(true);
    rec.onresult = (ev) => {
      const transcript = ev.results?.[0]?.[0]?.transcript || "";
      setQuery(transcript);
      if (imageSearch) clearImageSearch();
    };
    rec.onerror = (ev) => {
      setListening(false);
      const msg = ev?.error === "not-allowed" || ev?.error === "service-not-allowed"
        ? "Microphone permission denied. Allow mic access to use voice search."
        : "Voice search couldn't hear anything. Please try again.";
      showToast(msg, "error");
    };
    rec.onend = () => setListening(false);
    try { rec.start(); } catch { setListening(false); }
  };

  const ingestImageFile = async (file) => {
    if (!file) return;
    const baseRaw = file.name.replace(/\.[^.]+$/, "");
    const base = baseRaw.toLowerCase();
    const STOPWORDS = new Set(["ai", "img", "image", "png", "jpg", "jpeg", "webp", "the", "and", "of", "to", "in", "on", "for", "copy", "v1", "v2"]);
    const tokens = base
      .replace(/[()_\-\s]+/g, " ")
      .split(" ")
      .map((t) => t.trim())
      .filter((t) => t.length >= 3 && !STOPWORDS.has(t) && !/^\d+$/.test(t));
    const preview = URL.createObjectURL(file);
    // Show preview immediately with a loading state; filename tokens are the offline fallback.
    setImageSearch({ name: file.name, base, tokens, preview, aiQuery: "", loading: true });
    setQuery("");

    // Ask the backend (Claude Vision) to actually identify the product from the image.
    try {
      const dataUrl = await fileToDataUrl(file);
      const res = await api.imageSearch(dataUrl);
      const aiQuery = (res?.query || "").trim();
      setImageSearch((prev) => (prev && prev.preview === preview ? { ...prev, aiQuery, loading: false } : prev));
      if (!aiQuery && (!tokens.length)) {
        showToast("Couldn't identify the product. Try a clearer image.", "error");
      }
    } catch (err) {
      // AI unavailable (no key / backend down) — keep the filename-token fallback.
      console.warn("[imageSearch] AI fallback to filename:", err.message);
      setImageSearch((prev) => (prev && prev.preview === preview ? { ...prev, loading: false } : prev));
    }
  };

  const onImagePicked = (e) => {
    const file = e.target.files?.[0];
    if (file) ingestImageFile(file);
    e.target.value = "";
  };

  const clearImageSearch = () => {
    if (imageSearch?.preview) URL.revokeObjectURL(imageSearch.preview);
    setImageSearch(null);
  };

  useEffect(() => {
    if (open && searchImageFile) {
      ingestImageFile(searchImageFile);
      setSearchImageFile(null);
    }
  }, [open, searchImageFile, setSearchImageFile]);

  useEffect(() => {
    if (open && searchSeed) {
      setQuery(searchSeed);
      setSearchSeed("");
    }
  }, [open, searchSeed, setSearchSeed]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e) => e.key === "Escape" && closeModal();
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    const t = setTimeout(() => inputRef.current?.focus(), 50);
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
      clearTimeout(t);
    };
  }, [open, closeModal]);

  useEffect(() => {
    if (!open) {
      setQuery("");
      clearImageSearch();
    }
  }, [open]);

  const results = useMemo(() => {
    if (imageSearch) {
      // Preferred path: Claude Vision identified the product → text-match like a normal search.
      const aiq = (imageSearch.aiQuery || "").trim().toLowerCase();
      if (aiq) {
        const aiToks = aiq.split(/\s+/).filter(Boolean);
        const matched = allProducts.filter((p) => {
          const hay = `${p.name} ${p.category || ""} ${p.warranty || ""}`.toLowerCase();
          return aiToks.some((t) => hay.includes(t));
        });
        if (matched.length) return matched;
        // fall through to filename heuristic if the catalog has no name match
      }
      const toks = imageSearch.tokens;
      const baseSlug = imageSearch.base.replace(/[^a-z0-9]+/g, "_");
      const baseNorm = imageSearch.base.replace(/[^a-z0-9]+/g, "");
      const scored = allProducts.map((p) => {
        const url = (p.image || "").toLowerCase();
        const allUrls = [url];
        const urlsNorm = allUrls.map((u) => u.replace(/[^a-z0-9]+/g, ""));
        let score = 0;
        if (allUrls.some((u) => u.includes(baseSlug))) score += 100;
        if (urlsNorm.some((u) => u.includes(baseNorm))) score += 80;
        const nameHay = `${p.name} ${p.category || ""}`.toLowerCase();
        for (const t of toks) {
          if (nameHay.includes(t)) score += 2;
        }
        return { p, score };
      });
      const strong = scored.filter((s) => s.score >= 80).sort((a, b) => b.score - a.score);
      if (strong.length) return strong.map((s) => s.p);
      const weak = scored.filter((s) => s.score > 0).sort((a, b) => b.score - a.score).slice(0, 12);
      return weak.map((s) => s.p);
    }
    const q = query.trim().toLowerCase();
    if (!q) return allProducts;
    return allProducts.filter((p) => {
      const hay = `${p.name} ${p.category || ""} ${p.warranty || ""}`.toLowerCase();
      return q.split(/\s+/).every((tok) => hay.includes(tok));
    });
  }, [query, imageSearch, allProducts]);

  const showInitial = !query.trim() && !imageSearch;
  const count = results.length;

  return createPortal(
    <div
      className={`fixed inset-0 z-[1200] bg-white flex flex-col transition-opacity duration-200 ${
        open ? "opacity-100" : "opacity-0 pointer-events-none"
      }`}
      role="dialog"
      aria-modal="true"
    >
      <header className="flex items-center gap-3 px-4 sm:px-6 py-3 border-b border-gray-200 bg-white sticky top-0 z-10">
        <button
          onClick={closeModal}
          aria-label="Back"
          className="w-9 h-9 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-ink shrink-0"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M15 6l-6 6 6 6" />
          </svg>
        </button>
        <div className="flex-1 flex items-center gap-2 bg-gray-100 rounded-full px-4 h-11">
          <svg width="18" height="18" viewBox="0 0 15 15" fill="none" stroke="#6b7280" strokeWidth="1.4" className="shrink-0">
            <path d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z" />
          </svg>
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search products..."
            className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none text-brand-ink placeholder:text-gray-400"
          />
          {!showInitial && (
            <span className="text-xs text-brand-muted whitespace-nowrap">
              {count} result{count === 1 ? "" : "s"}
            </span>
          )}
          {query && (
            <button
              onClick={() => { setQuery(""); inputRef.current?.focus(); }}
              aria-label="Clear"
              className="w-7 h-7 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-500"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                <path d="M6 6l12 12M18 6L6 18" />
              </svg>
            </button>
          )}
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={onImagePicked}
          />
          <button
            type="button"
            onClick={() => fileRef.current?.click()}
            aria-label="Search by image"
            className="w-7 h-7 rounded-full hover:bg-gray-200 flex items-center justify-center text-gray-600"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.9 13.98l2.1 2.53 3.1-3.99c.2-.26.6-.26.8.01l3.51 4.68c.25.33.01.8-.4.8H6.02c-.42 0-.65-.48-.39-.81L8.12 13.98c.19-.25.57-.26.78 0z" />
            </svg>
          </button>
          <button
            type="button"
            onClick={startVoice}
            aria-label="Voice search"
            className={`w-7 h-7 rounded-full flex items-center justify-center transition ${listening ? "bg-red-100 text-red-600 animate-pulse" : "hover:bg-gray-200 text-gray-600"}`}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 14c1.66 0 2.99-1.34 2.99-3L15 5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-3c0 3-2.54 5.1-5.3 5.1S6.7 14 6.7 11H5c0 3.41 2.72 6.23 6 6.72V21h2v-3.28c3.28-.48 6-3.3 6-6.72h-1.7z" />
            </svg>
          </button>
        </div>
      </header>

      {imageSearch && (
        <div className="bg-blue-50 border-b border-blue-100 px-4 sm:px-6 py-2 flex items-center gap-3">
          <img
            src={imageSearch.preview}
            alt="search"
            className="w-10 h-10 rounded-md object-cover border border-blue-200 shrink-0"
          />
          <div className="flex-1 min-w-0">
            <p className="text-xs font-semibold text-brand-ink truncate">
              {imageSearch.loading
                ? "Identifying product…"
                : imageSearch.aiQuery
                  ? `Identified: ${imageSearch.aiQuery}`
                  : `Image search: ${imageSearch.name}`}
            </p>
            <p className="text-[11px] text-brand-muted">
              {imageSearch.loading
                ? "Analysing the image with AI"
                : `${count} matched product${count === 1 ? "" : "s"}`}
            </p>
          </div>
          <button
            onClick={clearImageSearch}
            className="text-xs text-[#3684bf] font-semibold hover:underline shrink-0"
          >
            Clear
          </button>
        </div>
      )}

      <div className="flex-1 overflow-y-auto bg-white">
        <div className="px-4 sm:px-6 py-5">
          {imageSearch?.loading && count === 0 ? (
            <div className="bg-gray-100 rounded-2xl py-16 px-6 text-center mt-4">
              <h3 className="text-2xl font-bold text-brand-ink mb-2">Analysing image…</h3>
              <p className="text-sm text-brand-muted">Identifying the product with AI — one moment.</p>
            </div>
          ) : count === 0 ? (
            <div className="bg-gray-100 rounded-2xl py-16 px-6 text-center mt-4">
              <h3 className="text-2xl font-bold text-brand-ink mb-2">No Results Found</h3>
              <p className="text-sm text-brand-muted">
                {imageSearch
                  ? `No matching products for this image. Try a different image or browse categories.`
                  : `No products found for "${query}"`}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4">
              {results.map((p) => (
                <ProductCard key={p.id} product={p} onOpen={closeModal} />
              ))}
            </div>
          )}
        </div>
        <Footer />
      </div>
    </div>,
    document.body
  );
}
