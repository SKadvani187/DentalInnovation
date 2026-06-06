import { useMemo, useState } from "react";
import { findProductById } from "../../data/products";
import { useProducts, useCombos, useFaqs, useQuestions } from "../../hooks/useApiData";
import { useUI } from "../../context/UIContext";
import { useSettings } from "../../context/SettingsContext";
import api from "../../lib/api";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

export default function QnaPage() {
  const { view, navigate, showToast } = useUI();
  const { data: allProducts } = useProducts();
  const { data: combos } = useCombos();
  const { productContent } = useSettings();
  const id = view?.params?.id;
  const product = useMemo(
    () => allProducts.find((p) => p.id === id) || findProductById(id) || combos.find((c) => c.id === id) || allProducts[0],
    [id, allProducts, combos]
  );

  // Per-product FAQs (admin) + answered customer questions (DB). FAQs fall back to global.
  const { faqs: dbFaqs } = useFaqs(product.id);
  const { questions: answeredQ, reload: reloadQ } = useQuestions(product.id);

  const [postOpen, setPostOpen] = useState(false);
  const [question, setQuestion] = useState("");
  const [busy, setBusy] = useState(false);

  const faqList = dbFaqs.length ? dbFaqs : (productContent.faqs || []);
  const qnaList = [...answeredQ, ...faqList];

  const onPost = async (e) => {
    e.preventDefault();
    if (!question.trim()) return;
    setBusy(true);
    try {
      const r = await api.submitQuestion({ product: product.id, question: question.trim() });
      showToast?.(r.message || "Question submitted.", "success");
      setQuestion("");
      setPostOpen(false);
      reloadQ();
    } catch (err) {
      showToast?.(err.message || "Could not submit your question.", "error");
    } finally {
      setBusy(false);
    }
  };

  const todayStr = new Date().toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });

  return (
    <div className="max-w-[1400px] mx-auto px-4 py-6">
      <button
        onClick={() => navigate("product", { id: product.id })}
        className="flex items-center gap-2 text-sm text-brand-ink hover:text-[#3684bf] mb-4"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        Back to Product
      </button>

      <h1 className="text-3xl font-bold text-brand-ink mb-1">Questions and Answer</h1>
      <p className="text-brand-muted mb-5">Have doubts regarding this product?</p>

      <button
        onClick={() => setPostOpen(true)}
        className="mb-6 border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white font-semibold px-5 py-2 rounded transition"
      >
        Post Your Question
      </button>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <aside className="lg:col-span-4">
          <div className="border border-gray-200 rounded-xl overflow-hidden sticky top-20">
            <div className="aspect-square bg-white flex items-center justify-center p-6 relative">
              <button aria-label="Wishlist" className="absolute top-3 left-3 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center hover:bg-gray-50">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#374151" strokeWidth="2">
                  <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                </svg>
              </button>
              <img src={product.image} alt={product.name} className="max-w-full max-h-full object-contain" />
            </div>
            <div className="p-5 border-t border-gray-100">
              <h2 className="font-bold text-brand-ink mb-2">{product.name}</h2>
              {product.description && (
                <p className="text-sm text-brand-muted leading-relaxed line-clamp-4 mb-3">{product.description}</p>
              )}
              <div className="flex items-baseline gap-2">
                <span className="text-2xl font-bold text-brand-ink">{fmt(product.price)}</span>
                <span className="text-sm text-brand-muted line-through">₹{product.mrp?.toLocaleString("en-IN")}</span>
              </div>
            </div>
          </div>
        </aside>

        <section className="lg:col-span-8 space-y-4">
          {postOpen && (
            <form onSubmit={onPost} className="border border-orange-200 bg-orange-50 rounded-xl p-5 space-y-3">
              <label className="block text-sm font-bold text-brand-ink">Ask your question</label>
              <input
                autoFocus
                type="text"
                value={question}
                onChange={(e) => setQuestion(e.target.value)}
                placeholder="What would you like to know about this product?"
                className="w-full border border-gray-300 rounded px-3 py-2.5 text-sm focus:outline-none focus:border-orange-500"
              />
              <div className="flex gap-2">
                <button
                  type="submit"
                  disabled={!question.trim() || busy}
                  className="bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm px-5 py-2 rounded transition disabled:opacity-60"
                >
                  {busy ? "Submitting…" : "Submit Question"}
                </button>
                <button
                  type="button"
                  onClick={() => { setPostOpen(false); setQuestion(""); }}
                  className="border border-gray-300 hover:border-gray-400 text-brand-ink text-sm px-4 py-2 rounded"
                >
                  Cancel
                </button>
              </div>
            </form>
          )}

          {qnaList.length === 0 && (
            <div className="border border-gray-200 rounded-xl p-10 text-center text-sm text-brand-muted">
              No questions yet. Be the first to ask about this product.
            </div>
          )}
          {qnaList.map((f, i) => (
            <article key={`${f.id}-${i}`} className="border border-gray-200 rounded-xl p-5">
              <div className="flex items-start gap-2 mb-2">
                <span className="bg-green-500 text-white text-[10px] font-bold uppercase px-2 py-1 rounded shrink-0">QUESTION</span>
                <p className="font-semibold text-brand-ink text-sm flex-1">{f.q}</p>
              </div>
              <p className="text-sm text-brand-muted leading-relaxed mb-3">
                <span className="text-brand-ink font-semibold">Answer:</span> {f.a}
              </p>
              <div className="flex items-center justify-between text-xs text-brand-muted border-t border-gray-100 pt-3">
                <span>| {f.date || todayStr}</span>
                <div className="flex items-center gap-4">
                  <ReactionBtn icon="up" initial={f.up} />
                  <ReactionBtn icon="down" initial={f.down} />
                </div>
              </div>
            </article>
          ))}
        </section>
      </div>
    </div>
  );
}

function ReactionBtn({ icon, initial = 0 }) {
  const [count, setCount] = useState(initial);
  return (
    <button onClick={() => setCount((c) => c + 1)} className="flex items-center gap-1 hover:text-[#3684bf]">
      {icon === "up" ? (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M7 10v12M15 5.88L14 10h5.83a2 2 0 011.92 2.56l-2.33 8A2 2 0 0117.5 22H7V10l5-9 1.88 1.88a2 2 0 01.62 1.45V5.5L13 10h2z" />
        </svg>
      ) : (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M17 14V2M9 18.12L10 14H4.17a2 2 0 01-1.92-2.56l2.33-8A2 2 0 016.5 2H17v12l-5 9-1.88-1.88a2 2 0 01-.62-1.45v0z" />
        </svg>
      )}
      <span>{count}</span>
    </button>
  );
}
