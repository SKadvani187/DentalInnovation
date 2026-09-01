import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useParams, useSearchParams } from "react-router-dom";
import { findProductById } from "../../data/products";
import { matchBySlug } from "../../lib/routes";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import { useProducts, useCombos, useEvents, useCategories, useReviews, useFaqs, useQuestions } from "../../hooks/useApiData";
import api from "../../lib/api";
import { useSettings } from "../../context/SettingsContext";
import { discountPct } from "../../lib/pricing";
import Seo from "../Seo";
import RichText from "../RichText";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

export default function ProductDetailPage() {
  const { openModal, setSelectedProduct } = useUI();
  const navigate = useAppNavigate();
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const { addToCart, items, updateQty, removeFromCart } = useCart();
  const { has, toggle } = useWishlist();

  const { data: apiProducts, loading: productsLoading } = useProducts();
  const { data: combos, loading: combosLoading } = useCombos();
  const { data: events, loading: eventsLoading } = useEvents();
  const { data: categories } = useCategories();
  const { company = {}, tierOffers = [], productDefaults = {} } = useSettings();

  const resolvedProduct = useMemo(() => {
    const ev = matchBySlug(events, id);
    if (ev) {
      const discount = discountPct(ev.mrp, ev.price);
      const gallery = ev.images?.length ? ev.images : [ev.image];
      return {
        id: ev.id,
        name: ev.name,
        image: ev.image,
        images: gallery,
        videoThumb: ev.videoThumb,
        videoUrl: ev.videoUrl,
        mrp: ev.mrp,
        price: ev.price,
        discount,
        rating: ev.rating || 0,
        reviews: ev.reviews || 0,
        category: "course",
        warranty: ev.type || "Course",
        inStock: true,
        description: ev.description,
        variants: [],
      };
    }
    // Resolve to null when the product isn't in the loaded data yet — never fall back
    // to a different product (that caused the wrong-image flash on first open).
    return (
      matchBySlug(apiProducts, id) ||
      findProductById(id) ||
      matchBySlug(combos, id) ||
      null
    );
  }, [id, apiProducts, combos, events]);

  // Safe placeholder (same id, blank media) so the hooks below never crash while the
  // real product is still loading; the actual render is gated on `resolvedProduct`.
  const product = resolvedProduct || {
    id, name: "", image: "", images: [], mrp: 0, price: 0, discount: 0,
    rating: 0, reviews: 0, category: "", warranty: "", inStock: true,
    description: "", variants: [],
  };

  // Real reviews from DB (approved only) + aggregate. No sample fallback — shows 0 until
  // a customer review is approved.
  const { reviews: dbReviews, summary: reviewSummary, reload: reloadReviews } = useReviews(product.id);
  const { faqs: dbFaqs } = useFaqs(product.id);
  const { questions: answeredQ } = useQuestions(product.id);
  // Answered customer questions + this product's own admin FAQs (no global fallback).
  // Same set the Q&A page shows. The FAQ section hides when empty.
  const faqList = [...answeredQ, ...dbFaqs];
  const reviewList = dbReviews;
  const ratingValue = reviewSummary.avg || 0;
  const reviewCount = reviewSummary.count || 0;

  const cartItem = items.find((i) => i.id === product.id && !i.variant);
  const qty = cartItem?.qty || 0;
  const outOfStock = product.inStock === false;
  // A product sold in two or more options is only ever bought THROUGH one of them, so the top card
  // defers its Add button to the variants list below. A single option is not a choice: the list
  // hides, the top Add works, and the order API auto-selects that option.
  const productVariants = usableVariants(product);
  const hasVariants = productVariants.length > 0;
  // The one option of a single-option product, named on the title badge — but only when no picker
  // is shown, since the picker already names it on its own card.
  const allVariants = Array.isArray(product.variants)
    ? product.variants.filter((v) => v && typeof v === "object" && v.label)
    : [];
  const soleVariant = !hasVariants && allVariants.length === 1 ? allVariants[0] : null;
  // Which option's pictures the gallery is showing (null = the product's own images).
  const [viewingVariant, setViewingVariant] = useState(null);
  const galleryImages = productVariants.find((v) => v.label === viewingVariant)?.images || null;

  const [pincode, setPincode] = useState("");
  const [pinMsg, setPinMsg] = useState("");
  const [pinInfo, setPinInfo] = useState(null);
  const [pinChecking, setPinChecking] = useState(false);
  const [reviewOpen, setReviewOpen] = useState(false);   // shared: Feedback link + Reviews card
  const [reviewWrite, setReviewWrite] = useState(false); // open review modal straight into write mode
  const [reviewsOpen, setReviewsOpen] = useState(false);
  const [crumbsOpen, setCrumbsOpen] = useState(false);
  const wished = has(product.id);

  const fromCategory = searchParams.get("from");
  const cats = Array.isArray(categories) ? categories : [];
  // Falls back to the product's own category id/name when the categories list is empty
  // (e.g. before the API responds), so the breadcrumb never reads from undefined.
  const crumbCategory =
    (fromCategory && cats.find((c) => c.id === fromCategory)) ||
    cats.find((c) => c.id === product.category) ||
    cats[0] ||
    { id: product.category || "", title: product.category || "Products" };
  const otherCategories = cats.filter((c) => c.id !== crumbCategory.id).slice(0, 8);


  const displayQty = qty > 0 ? qty : 1;
  const discount = product.discount || discountPct(product.mrp, product.price);
  // Per-product quantity tiers (the reference site's "Available Offers"). Only products that have
  // their own offers show a table — there is no global fallback.
  const productTiers = Array.isArray(product.bulkOffers) ? product.bulkOffers : [];
  const activeTier = useMemo(() => {
    return [...productTiers]
      .filter((t) => displayQty >= t.minQty)
      .sort((a, b) => b.minQty - a.minQty)[0];
  }, [displayQty, productTiers]);
  const effectivePrice = activeTier ? product.price * (1 - activeTier.rate) : product.price;
  const subtotal = Math.round(effectivePrice * displayQty);
  const mrpTotal = product.mrp * displayQty;
  const off = discountPct(mrpTotal, subtotal);
  const bulkSaved = activeTier ? Math.round((product.price - effectivePrice) * displayQty) : 0;

  const checkPin = async () => {
    if (!/^\d{6}$/.test(pincode)) {
      setPinMsg("Please enter a valid 6-digit pincode.");
      setPinInfo(null);
      return;
    }
    setPinChecking(true);
    setPinMsg("");
    setPinInfo(null);
    try {
      const r = await api.checkDelivery(pincode);
      if (r.serviceable) {
        setPinInfo({ ok: true, date: r.eta, cod: r.cod, label: r.label, days: r.days });
      } else {
        setPinInfo(null);
        setPinMsg("Sorry, we don't deliver to this pincode yet.");
      }
    } catch (err) {
      setPinInfo(null);
      setPinMsg(err.message || "Could not check delivery for this pincode.");
    } finally {
      setPinChecking(false);
    }
  };

  const onAdd = () => {
    addToCart(product, 1);
  };

  const inc = () => {
    if (cartItem) updateQty(cartItem.key, cartItem.qty + 1);
  };
  const dec = () => {
    if (!cartItem) return;
    if (cartItem.qty <= 1) removeFromCart(cartItem.key);
    else updateQty(cartItem.key, cartItem.qty - 1);
  };

  // Until the real product is found in the loaded data, show a loader instead of a
  // placeholder/other product. (All hooks are called above this line.) Once the source
  // collections have finished loading and the slug still isn't found (e.g. a stale link to
  // a deleted product), show a "not found" message instead of spinning forever.
  if (!resolvedProduct) {
    const stillLoading = productsLoading || combosLoading || eventsLoading;
    if (stillLoading) {
      return (
        <div className="max-w-[1400px] mx-auto px-3 sm:px-6 py-24 flex flex-col items-center justify-center text-brand-muted">
          <div className="w-10 h-10 border-2 border-gray-200 border-t-[#3684bf] rounded-full animate-spin" />
          <p className="mt-4 text-sm">Loading product…</p>
        </div>
      );
    }
    return (
      <div className="max-w-[1400px] mx-auto px-3 sm:px-6 py-24 flex flex-col items-center justify-center text-center text-brand-muted">
        <p className="text-lg font-semibold text-brand-ink">Product not found</p>
        <p className="mt-2 text-sm">This product may have been removed or is no longer available.</p>
        <button
          onClick={() => navigate("home")}
          className="mt-5 px-5 py-2.5 rounded-lg bg-[#3684bf] text-white text-sm font-semibold hover:bg-[#2a6a99]"
        >
          Continue shopping
        </button>
      </div>
    );
  }

  const seoDesc = (resolvedProduct.description || `Buy ${resolvedProduct.name} online at DentInno.`)
    .toString().replace(/\s+/g, " ").trim().slice(0, 160);
  const inStock = resolvedProduct.stock > 0 || resolvedProduct.inStock !== false;
  const productJsonLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: resolvedProduct.name,
    ...(resolvedProduct.image ? { image: resolvedProduct.image } : {}),
    description: seoDesc,
    ...(resolvedProduct.brand ? { brand: { "@type": "Brand", name: resolvedProduct.brand } } : {}),
    offers: {
      "@type": "Offer",
      price: Number(resolvedProduct.price) || 0,
      priceCurrency: "INR",
      availability: inStock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
    },
    ...(resolvedProduct.rating
      ? { aggregateRating: { "@type": "AggregateRating", ratingValue: resolvedProduct.rating, reviewCount: resolvedProduct.reviews || 1 } }
      : {}),
  };

  return (
    <div className="max-w-[1400px] mx-auto px-3 sm:px-6 py-5">
      <Seo
        title={resolvedProduct.name}
        description={seoDesc}
        image={resolvedProduct.image}
        jsonLd={productJsonLd}
      />
      <div className="flex items-center justify-between flex-wrap gap-2 mb-4 text-sm">
        <nav className="flex items-center gap-2 text-brand-muted relative">
          <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
          <span>/</span>
          <button onClick={() => navigate("category")} className="hover:text-[#3684bf]">Products</button>
          <span>/</span>
          <button
            onClick={() => navigate("category", { category: crumbCategory.id })}
            className="text-brand-ink font-semibold hover:text-[#3684bf]"
          >
            {crumbCategory.title}
          </button>

          <div className="relative">
            <button
              onClick={() => setCrumbsOpen((v) => !v)}
              className="bg-gray-200 text-xs px-2 py-0.5 rounded-full text-brand-ink hover:bg-gray-300"
            >
              +{otherCategories.length} more
            </button>
            {crumbsOpen && (
              <div
                className="absolute left-0 top-full mt-2 w-[230px] bg-white border border-gray-200 rounded-lg shadow-xl z-20 overflow-hidden"
                onMouseLeave={() => setCrumbsOpen(false)}
              >
                <div className="px-4 py-2 text-[11px] font-bold tracking-wider text-brand-muted bg-gray-50 border-b border-gray-100">
                  ALL CATEGORIES
                </div>
                <ul className="py-1 max-h-[280px] overflow-y-auto">
                  {[crumbCategory, ...otherCategories].map((c) => (
                    <li key={c.id}>
                      <button
                        onClick={() => {
                          setCrumbsOpen(false);
                          navigate("category", { category: c.id });
                        }}
                        className="w-full text-left px-4 py-2 text-sm text-brand-ink hover:bg-gray-50"
                      >
                        {c.title}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        </nav>
        <div className="text-sm text-brand-muted">
          Would you like to tell us about the product?{" "}
          <a onClick={() => { setReviewWrite(true); setReviewOpen(true); }} className="text-[#3684bf] font-semibold hover:underline cursor-pointer">Feedback →</a>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {/* LEFT image gallery */}
        <div className="lg:col-span-4 lg:sticky lg:top-[110px] lg:self-start">
          <ProductGallery key={product.id} product={product} images={galleryImages} wished={wished} onWish={() => toggle(product.id)} />
        </div>

        {/* CENTER details */}
        <div className="lg:col-span-4 space-y-4">
          <div className="border border-gray-200 rounded-xl p-5">
            <div className="flex items-start gap-2 mb-2 flex-wrap">
              <h1 className="text-2xl font-bold text-brand-ink leading-snug min-w-0">
                {product.name}
                {/* Names the option on the title when no picker is shown — a single-option product
                    still tells the buyer which one they are looking at. */}
                {soleVariant && (
                  <span className="align-middle ml-2 inline-block bg-[#3684bf] text-white text-xs font-semibold px-2.5 py-1 rounded-md whitespace-nowrap">
                    {soleVariant.label}
                  </span>
                )}
              </h1>
            </div>
            <div className="mb-3 flex items-center gap-2 flex-wrap">
              {outOfStock && (
                <span
                  className="inline-flex items-center text-white font-bold rounded-full"
                  style={{
                    background: "linear-gradient(135deg, rgb(220, 38, 38) 0%, rgb(153, 27, 27) 100%)",
                    fontSize: "13px",
                    padding: "4px 8px",
                  }}
                >
                  Out of Stock
                </span>
              )}
              {product.warranty && (
                <span className="inline-block bg-[#3684bf] text-white text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full">
                  {product.warranty}
                </span>
              )}
            </div>
            <p className="text-sm mb-3">
              <span className="text-brand-muted">Brand : </span>
              <a className="text-[#3684bf] font-semibold underline cursor-pointer">{company.name}</a>
            </p>

            {/* Rating pill: shown ONLY when this product has at least one approved review.
                No reviews -> nothing shown. */}
            {reviewCount > 0 && (
            <div className="relative mb-4">
              <button
                onClick={() => setReviewsOpen((v) => !v)}
                className="inline-flex items-center gap-2 border border-gray-300 rounded px-3 py-1.5 hover:border-gray-400 transition"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z" /></svg>
                <span className="text-sm font-semibold text-brand-ink">{Number(ratingValue) > 0 ? Number(ratingValue).toFixed(1) : "5.0"}</span>
                <span className="text-sm text-brand-muted">| {reviewCount} Reviews</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" className={`text-brand-muted transition ${reviewsOpen ? "rotate-180" : ""}`}><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" /></svg>
              </button>

              {reviewsOpen && (
                <div className="absolute z-20 mt-2 w-full max-w-md bg-white border border-gray-200 rounded-lg shadow-xl p-4">
                  <div className="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <div className="text-3xl font-bold text-brand-ink">{Number(ratingValue).toFixed(1)}</div>
                    <div className="flex-1">
                      <div className="flex text-amber-400 text-sm">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <span key={i}>{i < Math.round(ratingValue) ? "★" : "☆"}</span>
                        ))}
                      </div>
                      <p className="text-xs text-brand-muted mt-0.5">Based on {reviewCount} reviews</p>
                    </div>
                  </div>

                  <div className="space-y-3 mt-3 max-h-[260px] overflow-y-auto">
                    {reviewList.map((r) => (
                      <div key={r.id} className="text-sm">
                        <div className="flex items-center justify-between">
                          <span className="font-semibold text-brand-ink">{r.name}</span>
                          <span className="text-amber-400">{"★".repeat(r.stars)}{"☆".repeat(5 - r.stars)}</span>
                        </div>
                        <p className="text-xs text-brand-muted mt-0.5">{r.date}</p>
                        <p className="text-sm text-brand-ink mt-1">{r.text}</p>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
            )}

            <div className="flex items-center justify-between gap-3 mb-3">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-xl font-bold text-brand-ink">{fmt(product.price)}</span>
                <span className="text-sm text-brand-muted line-through">₹{product.mrp?.toLocaleString("en-IN")}</span>
                <span className="text-sm font-bold text-green-600">({discount}% OFF)</span>
              </div>
              {outOfStock ? (
                <button
                  disabled
                  className="px-5 py-2 bg-gray-300 text-white font-bold rounded-md cursor-not-allowed"
                >
                  OUT OF STOCK
                </button>
              ) : hasVariants ? (
                <span className="text-xs font-semibold text-brand-muted whitespace-nowrap">
                  Choose an option below ↓
                </span>
              ) : qty > 0 ? (
                <div className="inline-flex items-center border border-orange-500 rounded-lg overflow-hidden" style={{ borderRadius: 8 }}>
                  <button onClick={dec} className="w-8 h-8 text-orange-500 text-lg font-semibold hover:bg-orange-50 transition">−</button>
                  <span className="w-8 text-center font-semibold text-brand-ink text-sm">{qty}</span>
                  <button onClick={inc} className="w-8 h-8 text-orange-500 text-lg font-semibold hover:bg-orange-50 transition">+</button>
                </div>
              ) : (
                <button
                  onClick={onAdd}
                  title="Add to cart"
                  type="button"
                  className="text-orange-500 border border-orange-500 hover:bg-orange-50 font-medium uppercase text-sm tracking-wide transition"
                  style={{ borderRadius: 8, paddingBlock: 4, paddingInline: 22, minWidth: 64 }}
                >
                  add
                </button>
              )}
            </div>

            {/* Brief blurb under the price = short_description (plain text). The long Description
                lives in the accordion below; this top box must stay short. */}
            {(product.shortDescription || product.description) && (
              product.shortDescription
                ? <RichText html={product.shortDescription} className="text-[15px] text-[#556575] leading-relaxed whitespace-pre-line"/>
                : <RichText html={product.description} className="text-[15px] text-[#556575] leading-relaxed whitespace-pre-line" />
            )}

            {product.catalogueUrl && (
              <a
                href={product.catalogueUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold text-sm transition"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M14 3h7v7M10 14L21 3M21 14v5a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h5" /></svg>
                Open Catalogue
              </a>
            )}
          </div>

          {/* This product's own options, right under the main card — each adds its own price. */}
          <ProductVariants product={product} viewing={viewingVariant} onView={setViewingVariant} />

          <div className="border border-gray-200 rounded-xl p-4">
            <h3 className="font-bold text-brand-ink mb-3">Delivery Details</h3>
            <div className="flex items-center gap-2 bg-white border border-gray-300 rounded-md px-3 py-1.5 transition-colors duration-200 hover:border-gray-800 focus-within:border-[#3684bf] focus-within:ring-1 focus-within:ring-[#3684bf]">
              <svg width="20" height="20" viewBox="0 0 20 20" aria-label="India" className="shrink-0 rounded-full overflow-hidden">
                <defs>
                  <clipPath id="indFlagClip"><circle cx="10" cy="10" r="10" /></clipPath>
                </defs>
                <g clipPath="url(#indFlagClip)">
                  <rect x="0" y="0" width="20" height="6.67" fill="#FF9933" />
                  <rect x="0" y="6.67" width="20" height="6.66" fill="#FFFFFF" />
                  <rect x="0" y="13.33" width="20" height="6.67" fill="#138808" />
                  <circle cx="10" cy="10" r="1.6" fill="none" stroke="#000080" strokeWidth="0.5" />
                </g>
              </svg>
              <input
                type="text"
                inputMode="numeric"
                maxLength={6}
                placeholder="Enter pincode"
                value={pincode}
                onChange={(e) => {
                  setPincode(e.target.value.replace(/\D/g, "").slice(0, 6));
                  setPinInfo(null);
                  setPinMsg("");
                }}
                className="flex-1 min-w-0 text-sm text-brand-ink placeholder:text-gray-400 focus:outline-none bg-transparent"
              />
              <button
                onClick={checkPin}
                type="button"
                disabled={pinChecking}
                className="text-[#3684bf] hover:bg-blue-50 active:bg-blue-100 font-medium text-sm uppercase tracking-wide px-2 py-1 rounded transition-colors disabled:opacity-60"
              >
                {pinChecking ? "…" : "Check"}
              </button>
            </div>
            {pinInfo?.ok ? (
              <div className="mt-3 space-y-2 text-sm">
                <div className="flex items-center gap-2 text-brand-ink">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
                    <rect x="1" y="6" width="14" height="11" rx="1" />
                    <path d="M15 9h4l3 3v5h-7" />
                    <circle cx="6" cy="19" r="2" />
                    <circle cx="18" cy="19" r="2" />
                  </svg>
                  <span>Get it by <span className="font-semibold">{pinInfo.date}</span></span>
                </div>
                <div className="flex items-center gap-2 text-brand-ink">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
                    <path d="M3 7h13l-3-3M21 17H8l3 3" />
                  </svg>
                  <span>{productDefaults.replacementText || "Easy 7 days replacement available"}</span>
                </div>
                <div className="flex items-center gap-2 text-brand-ink">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke={pinInfo.cod ? "#16a34a" : "#9ca3af"} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
                    <rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20" />
                  </svg>
                  <span>{pinInfo.cod ? "Cash on Delivery available" : "Prepaid only (no COD)"}</span>
                </div>
                {pinInfo.label && (
                  <div className="text-xs text-brand-muted pl-7">Region: {pinInfo.label}</div>
                )}
              </div>
            ) : (
              <p className="text-xs text-brand-muted mt-2 transition-opacity duration-300 group-hover:opacity-90">
                Please enter PIN code to check delivery time & Pay on Delivery Availability
              </p>
            )}
            {pinMsg && (
              <p className={`text-xs mt-2 ${pinMsg.startsWith("Please") ? "text-red-600" : "text-brand-muted"}`}>{pinMsg}</p>
            )}
          </div>

          {/* <AvailableVariants product={product} /> */}

          <ProductHighlights highlights={product.highlights} />

          <ProductAccordions product={product} fallback={[]} />

          <FaqsSection faqs={faqList} productId={product.id} productName={product.name} />
        </div>

        {/* RIGHT — Available Offers */}
        <div className="lg:col-span-4 space-y-4 lg:sticky lg:top-20 lg:self-start">
          <div className="border border-gray-200 rounded-xl p-5">
            <h3 className="text-center text-sm font-bold tracking-wider text-brand-ink mb-3 pb-3 border-b border-gray-100">
              AVAILABLE OFFERS
            </h3>

            <div className="space-y-2 mb-4">
              <div className="text-base">
                <span className="text-brand-muted">Subtotal: </span>
                <span className="font-bold text-[#ff6b1a] text-lg">{fmt(subtotal)}</span>
              </div>
              <div className="text-sm">
                <span className="text-brand-muted">MRP Total - </span>
                <span className="line-through text-brand-muted">₹{mrpTotal.toLocaleString("en-IN")}</span>
                <span className="ml-2 text-green-600 font-bold">{off}% off</span>
              </div>
              {bulkSaved > 0 && (
                <div className="text-sm text-green-700 font-semibold flex items-center gap-1">
                  <span>🔥</span> You saved {fmt(bulkSaved)} with bulk pricing!
                </div>
              )}
              <div className="flex items-center gap-3 text-sm">
                <span className="text-[#3684bf] font-semibold">Item - {displayQty}</span>
                {qty > 0 && (
                  <div className="ml-auto inline-flex items-center border border-gray-300 rounded">
                    <button onClick={dec} className="w-7 h-7 hover:bg-gray-50">−</button>
                    <span className="w-8 text-center text-sm">{qty}</span>
                    <button onClick={inc} className="w-7 h-7 hover:bg-gray-50">+</button>
                  </div>
                )}
              </div>
            </div>

            {productTiers.length > 0 && (
            <div className="border border-gray-200 rounded-lg overflow-hidden">
              <div className="grid grid-cols-2 bg-blue-50 text-xs font-bold text-brand-ink">
                <div className="px-3 py-2 border-r border-gray-200">Offer</div>
                <div className="px-3 py-2">Add on Savings</div>
              </div>
              {productTiers.map((tier) => {
                const isActive = activeTier?.minQty === tier.minQty;
                return (
                  <div
                    key={tier.minQty}
                    className={`grid grid-cols-2 text-sm border-t transition ${isActive ? "bg-orange-100 border-orange-200" : "border-gray-100"}`}
                  >
                    <div className={`px-3 py-3 border-r relative ${isActive ? "border-orange-200 text-orange-700 font-semibold" : "border-gray-200 text-brand-ink"}`}>
                      {isActive && <span className="absolute left-0 top-0 bottom-0 w-1 bg-orange-500" />}
                      {tier.label} for{" "}
                      <span className="font-bold">{fmt(Math.round(product.price * (1 - tier.rate)))}</span> each
                    </div>
                    <div className={`px-3 py-3 font-bold ${isActive ? "text-orange-700" : ""}`}>{Math.round(tier.rate * 100)}%</div>
                  </div>
                );
              })}
            </div>
            )}

            <div className="grid grid-cols-2 gap-2 mt-4">
              <button
                onClick={() => {
                  const msg = encodeURIComponent(`Hi, I'm interested in ${product.name} (₹${product.price}). Is it available?`);
                  const wa = (company.phoneSales || company.phone || "").replace(/\D/g, "");
                  window.open(`https://wa.me/${wa}?text=${msg}`, "_blank");
                }}
                className="relative flex items-center justify-center bg-white border border-gray-200 rounded-lg h-[45px] w-full hover:border-green-500 transition cursor-pointer"
              >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="#16a34a" className="mr-2">
                  <path d="M20.52 3.48A11.94 11.94 0 0012 0C5.37 0 0 5.37 0 12c0 2.11.55 4.16 1.6 5.97L0 24l6.18-1.62A11.95 11.95 0 0012 24c6.63 0 12-5.37 12-12 0-3.2-1.25-6.21-3.48-8.52zM12 22a9.9 9.9 0 01-5.05-1.38l-.36-.21-3.67.96.98-3.58-.23-.37A9.93 9.93 0 012 12c0-5.52 4.48-10 10-10s10 4.48 10 10-4.48 10-10 10zm5.42-7.46c-.3-.15-1.76-.87-2.03-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.46-.88-.78-1.47-1.75-1.65-2.05-.17-.3-.02-.46.13-.6.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.06 2.87 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z" />
                </svg>
                <div className="flex flex-col items-start leading-none gap-[2px]">
                  <span className="text-[11px] font-normal text-brand-muted">Buy on</span>
                  <span className="text-[14.5px] font-bold text-brand-ink">WhatsApp</span>
                </div>
              </button>
              {outOfStock ? (
                <div className="buy-now-btn buy-now-btn--disabled" style={{ animation: "none" }}>
                  <div className="buy-now-btn__shadow" />
                  <div className="buy-now-btn__face">

                    <span className="buy-now-btn__shimmer" />
                    <span className="relative flex items-center justify-center select-none pointer-events-none" style={{ color: "black" }}>
                      {"Out of Stock".split("").map((c, i) => (
                        <span key={i}>{c === " " ? " " : c}</span>
                      ))}
                    </span>
                  </div>
                </div>
              ) : (
                <div className="buy-now-btn">
                  <div className="buy-now-btn__shadow" />
                  <button
                    type="button"
                    onClick={() => { onAdd(); openModal("cart"); }}
                    className="buy-now-btn__face"
                  >
                    <span className="buy-now-btn__shimmer" />
                    <span className="relative">Buy Now</span>
                  </button>
                </div>
              )}
            </div>
          </div>

          <div className="border border-gray-200 rounded-xl p-5 text-center">
            <p className="text-sm text-brand-ink mb-3">Want to buy even more quantity ?</p>
            <button
              onClick={() => { setSelectedProduct(product); openModal("bulk"); }}
              className="w-full border border-[#3684bf] text-[#3684bf] font-bold text-sm py-2.5 rounded-md uppercase hover:bg-[#3684bf] hover:text-white transition"
            >
              Get Bulk Quote Now
            </button>
          </div>

          <PaymentOptionsCard />

          <SmartBenefitsCard />

          <RatingsReviewsCard
            product={product}
            rating={ratingValue}
            count={reviewCount}
            reviews={reviewList}
            onSubmitted={reloadReviews}
            open={reviewOpen}
            setOpen={(v) => { setReviewOpen(v); if (!v) setReviewWrite(false); }}
            autoWrite={reviewWrite}
          />
        </div>
      </div>

      <RelatedProducts product={product} />


    </div>
  );
}

function ProductGallery({ product, wished, onWish, images: imagesProp }) {
  // `imagesProp` lets the variant list narrow the gallery to the option being viewed; without it
  // the gallery shows the product's own images, as before.
  const images = (imagesProp?.length ? imagesProp : product.images?.length ? product.images : [product.image]) || [];
  const [idx, setIdx] = useState(0);
  const [zoom, setZoom] = useState(null);
  const [hovering, setHovering] = useState(false);
  const [panelRect, setPanelRect] = useState(null);

  const current = images[idx] || product.image;
  const prev = () => { setIdx((i) => (i - 1 + images.length) % images.length); setZoom(null); setHovering(false); };
  const next = () => { setIdx((i) => (i + 1) % images.length); setZoom(null); setHovering(false); };

  // Back to the first frame on a new product AND on a variant switch, so the gallery never opens
  // on an index the new image set doesn't have.
  useEffect(() => { setIdx(0); setZoom(null); }, [product.id, images.join("|")]);

  useEffect(() => {
    if (images.length <= 1) return;
    if (hovering) return;
    const t = setInterval(() => {
      setIdx((i) => (i + 1) % images.length);
    }, 3000);
    return () => clearInterval(t);
  }, [images.length, hovering, product.id]);

  const onMove = (e) => {
    if (!hovering) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    setZoom({ x, y });
    setPanelRect({ top: rect.top, left: rect.right + 12 });
  };
  const onEnter = () => setHovering(true);
  const onLeave = () => { setHovering(false); setZoom(null); setPanelRect(null); };

  return (
    <div className="flex flex-col gap-3">
      <div className="relative bg-white border border-gray-200 rounded-xl aspect-square group">
        <button
          onClick={onWish}
          aria-label="Wishlist"
          className="absolute top-3 left-3 z-20 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center hover:bg-gray-50"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill={wished ? "#ef4444" : "none"} stroke={wished ? "#ef4444" : "#374151"} strokeWidth="2">
            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
          </svg>
        </button>
        <button
          aria-label="Share"
          className="absolute top-3 right-3 z-20 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center hover:bg-gray-50"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#374151" strokeWidth="2">
            <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
            <path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
          </svg>
        </button>

        {images.length > 1 && (
          <>
            <button
              onClick={prev}
              aria-label="Previous"
              className="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-[#ef4444] hover:bg-[#dc2626] text-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 6 8 12l6 6 1.41-1.41L10.83 12l4.58-4.59z" /></svg>
            </button>
            <button
              onClick={next}
              aria-label="Next"
              className="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-[#ef4444] hover:bg-[#dc2626] text-white shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" /></svg>
            </button>
          </>
        )}

        <div
          className="absolute inset-0 cursor-zoom-in"
          onMouseEnter={onEnter}
          onMouseMove={onMove}
          onMouseLeave={onLeave}
        >
          <img src={current} alt={product.name} className="w-full h-full object-contain p-6 select-none pointer-events-none rounded-xl" />

          {zoom && (
            <div
              className="absolute w-[40%] h-[40%] border-2 border-[#3684bf] bg-[#3684bf]/20 pointer-events-none"
              style={{
                left: `${Math.max(0, Math.min(60, zoom.x - 20))}%`,
                top: `${Math.max(0, Math.min(60, zoom.y - 20))}%`,
              }}
            />
          )}
        </div>
        {zoom && panelRect && createPortal(
          <div
            className="hidden lg:block fixed w-[480px] h-[480px] bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden z-[9999] pointer-events-none"
            style={{ top: panelRect.top, left: panelRect.left }}
          >
            <div
              className="w-full h-full bg-no-repeat"
              style={{
                backgroundImage: `url("${current}")`,
                backgroundSize: "250%",
                backgroundPosition: `${zoom.x}% ${zoom.y}%`,
              }}
            />
          </div>,
          document.body
        )}

        {images.length > 1 && (
          <div className="absolute bottom-3 left-0 right-0 flex items-center justify-center gap-1.5 z-20">
            {images.map((_, i) => (
              <span
                key={i}
                className={`w-1.5 h-1.5 rounded-full transition ${i === idx ? "bg-[#3684bf] w-4" : "bg-gray-300"}`}
              />
            ))}
          </div>
        )}
      </div>

      {(images.length > 1 || product.videoUrl) && (
        <div className="grid grid-cols-6 gap-2">
          {images.map((src, i) => (
            <div
              key={i}
              className="relative group/thumb"
              onMouseEnter={() => setIdx(i)}
            >
              <button
                onClick={() => setIdx(i)}
                className={`w-full aspect-square bg-white border-2 rounded-md p-1 transition ${i === idx ? "border-[#3684bf]" : "border-gray-200 hover:border-gray-400"}`}
              >
                <img src={src} alt={`thumb ${i + 1}`} className="w-full h-full object-contain" />
              </button>
              <div className="hidden md:group-hover/thumb:block absolute left-full ml-3 top-1/2 -translate-y-1/2 w-[260px] h-[260px] bg-white border border-gray-200 rounded-xl shadow-2xl p-3 z-[9999] pointer-events-none">
                <img src={src} alt="" className="w-full h-full object-contain" />
              </div>
            </div>
          ))}
          {product.videoUrl && (
            <button
              onClick={() => window.open(product.videoUrl, "_blank")}
              className="relative w-full aspect-square bg-black rounded-md overflow-hidden border-2 border-gray-200 hover:border-[#3684bf]"
              aria-label="Play video"
            >
              {product.videoThumb && (
                <img src={product.videoThumb} alt="Video" className="w-full h-full object-cover opacity-70" />
              )}
              <span className="absolute inset-0 flex items-center justify-center">
                <span className="w-7 h-7 rounded-full bg-red-500 flex items-center justify-center shadow-lg">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="white">
                    <path d="M8 5v14l11-7z" />
                  </svg>
                </span>
              </span>
            </button>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * This product's own variants ({label, price, mrp, discount, qty}) as a selectable list, each
 * line adding ITS OWN price/label to the cart. `qty` is the variant's own stock: null/undefined
 * means the option isn't stock-tracked, so only a tracked 0 blocks the sale.
 * (Not to be confused with RelatedProducts below, which is same-category cross-sell.)
 */
/**
 * The options worth showing a picker for.
 *
 * Two or more options is always a choice. A single option usually is not — most of the catalogue
 * carries one "Generic" row that just restates the product's own price, and a one-row picker for
 * that is noise. But a single option priced differently from the product IS the thing being sold,
 * and hiding it would hide its price, so that one is shown.
 */
function usableVariants(product) {
  const list = Array.isArray(product.variants)
    ? product.variants.filter((v) => v && typeof v === "object" && v.label)
    : [];
  if (list.length > 1) return list;
  if (list.length === 1) {
    const v = list[0];
    const pricedApart =
      (Number(v.price) || 0) !== (Number(product.price) || 0) ||
      (Number(v.mrp) || 0) !== (Number(product.mrp) || 0);
    return pricedApart ? list : [];
  }
  return [];
}

function ProductVariants({ product, viewing, onView }) {
  const { addToCart, items, updateQty, removeFromCart } = useCart();
  const { openModal } = useUI();
  const { productDefaults = {} } = useSettings();

  const variants = usableVariants(product);
  if (variants.length === 0) return null;

  const getCartItem = (label) => items.find((i) => i.id === product.id && i.variant === label);
  const isTracked = (v) => v.qty !== null && v.qty !== undefined;
  const isSoldOut = (v) => isTracked(v) && v.qty <= 0;
  const hasOwnImages = (v) => Array.isArray(v.images) && v.images.length > 0;

  const onAdd = (v) => {
    addToCart({ ...product, price: v.price, mrp: v.mrp, stock: v.qty }, 1, v.label);
    openModal("cart");
  };
  const inc = (v) => {
    const ci = getCartItem(v.label);
    if (ci) updateQty(ci.key, ci.qty + 1);
    else onAdd(v);
  };
  const dec = (v) => {
    const ci = getCartItem(v.label);
    if (!ci) return;
    if (ci.qty <= 1) removeFromCart(ci.key);
    else updateQty(ci.key, ci.qty - 1);
  };

  const prices = variants.map((v) => Number(v.price) || 0).filter((p) => p > 0);
  const low = prices.length ? Math.min(...prices) : 0;
  const high = prices.length ? Math.max(...prices) : 0;

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <div className="flex items-baseline justify-between gap-2 mb-3">
        <h3 className="font-bold text-brand-ink">Available Variants</h3>
        <span className="text-xs text-brand-muted">
          {variants.length} option{variants.length > 1 ? "s" : ""}
          {low > 0 && <> · {low === high ? fmt(low) : `${fmt(low)} – ${fmt(high)}`}</>}
        </span>
      </div>

      <div className="space-y-3">
        {variants.map((v) => {
          const ci = getCartItem(v.label);
          const qty = ci?.qty || 0;
          const soldOut = isSoldOut(v);
          const atLimit = isTracked(v) && qty >= v.qty;
          const isViewing = viewing === v.label;
          return (
            <div
              key={v.label}
              className={`rounded-lg border p-3 transition ${
                isViewing ? "border-[#3684bf] bg-blue-50/40" : "border-gray-200"
              } ${soldOut ? "opacity-50" : ""}`}
            >
              <div className="flex items-start gap-2 flex-wrap">
                <h4 className="font-bold text-brand-ink text-sm">{product.name}</h4>
                <span className="inline-block bg-[#3684bf] text-white text-[11px] font-semibold px-2 py-0.5 rounded-md whitespace-nowrap">
                  {v.label}
                </span>
              </div>

              <div className="flex items-center gap-2 flex-wrap mt-1">
                <span className="text-sm font-bold text-brand-ink">{fmt(v.price)}</span>
                {Number(v.mrp) > Number(v.price) && (
                  <span className="text-xs text-brand-muted line-through">
                    ₹{Number(v.mrp).toLocaleString("en-IN")}
                  </span>
                )}
                {v.discount > 0 && (
                  <span className="text-xs font-bold text-green-600">{v.discount}% off</span>
                )}
              </div>

              {soldOut ? (
                <p className="text-xs font-semibold text-red-600 mt-1">Out of stock</p>
              ) : isTracked(v) && v.qty <= 5 ? (
                <p className="text-xs font-semibold text-orange-600 mt-1">Only {v.qty} left</p>
              ) : (
                <p className="text-xs text-brand-muted mt-1">
                  {productDefaults.variantDeliveryNote || "📦 Get it by 3–5 days"}
                </p>
              )}

              <div className="flex items-center justify-between gap-2 mt-2">
                {soldOut ? (
                  <span className="text-xs font-bold text-brand-muted uppercase tracking-wider">Sold out</span>
                ) : qty > 0 ? (
                  <div className="inline-flex items-center border border-orange-500 rounded-lg overflow-hidden">
                    <button onClick={() => dec(v)} className="w-8 h-8 text-orange-500 text-lg font-semibold hover:bg-orange-50 transition">−</button>
                    <span className="w-8 text-center font-semibold text-brand-ink text-sm">{qty}</span>
                    <button
                      onClick={() => inc(v)}
                      disabled={atLimit}
                      title={atLimit ? `Only ${v.qty} in stock` : undefined}
                      className="w-8 h-8 text-orange-500 text-lg font-semibold hover:bg-orange-50 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                    >+</button>
                  </div>
                ) : (
                  <button
                    onClick={() => onAdd(v)}
                    type="button"
                    title={`Add ${v.label} to cart`}
                    className="text-orange-500 border border-orange-500 hover:bg-orange-50 font-medium uppercase text-sm tracking-wide transition"
                    style={{ borderRadius: 8, paddingBlock: 4, paddingInline: 22, minWidth: 64 }}
                  >
                    add
                  </button>
                )}

                {/* Only an option with its own pictures can change the gallery. */}
                {hasOwnImages(v) && (
                  <button
                    type="button"
                    onClick={() => onView(isViewing ? null : v.label)}
                    className={`inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border transition ${
                      isViewing
                        ? "bg-[#3684bf] text-white border-[#3684bf]"
                        : "text-[#3684bf] border-gray-300 hover:border-[#3684bf]"
                    }`}
                  >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" />
                      <circle cx="8.5" cy="8.5" r="1.5" />
                      <path d="M21 15l-5-5L5 21" />
                    </svg>
                    {isViewing ? "Viewing" : "View images"}
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function AvailableVariants({ product }) {
  const { addToCart } = useCart();
  const { openModal } = useUI();
  const navigate = useAppNavigate();
  const { productDefaults = {} } = useSettings();
  const { data: allProducts } = useProducts();

  // Same-category products (excluding this one) as related cross-sell suggestions.
  const variantList = useMemo(() => {
    return allProducts
      .filter((p) => p.id !== product.id && p.category === product.category)
      .slice(0, 5);
  }, [product, allProducts]);

  if (variantList.length === 0) return null;

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <h3 className="font-bold text-brand-ink mb-4">Related Products</h3>

      <div className="space-y-3">
        {variantList.map((v) => (
          <div key={v.id} className="border border-gray-200 rounded-lg p-3">
            <h4 className="font-bold text-brand-ink text-sm">{v.name}</h4>
            {v.warranty && (
              <span className="inline-block mt-1 bg-[#3684bf] text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">
                {v.warranty}
              </span>
            )}
            <div className="flex items-baseline gap-2 mt-1">
              <span className="text-sm font-bold text-brand-ink">{v.price.toLocaleString("en-IN")}</span>
              <span className="text-xs text-brand-muted line-through">₹{v.mrp.toLocaleString("en-IN")}</span>
              <span className="text-xs font-bold text-green-600">{v.discount}% off</span>
            </div>
            <div className="flex items-center justify-between mt-2 gap-2">
              <p className="text-xs text-brand-muted">{productDefaults.variantDeliveryNote || "📦 Get it by 3–5 days"}</p>
              <div className="flex gap-2">
                <button
                  onClick={() => navigate("product", { id: v.id, name: v.name })}
                  className="px-4 py-1 border border-[#ff6b1a] text-[#ff6b1a] text-xs font-semibold rounded-lg hover:bg-orange-50 transition"
                >
                  View
                </button>
                <button
                  onClick={() => { addToCart(v, 1); openModal("cart"); }}
                  className="px-4 py-1 border border-[#ff6b1a] text-[#ff6b1a] text-xs font-semibold rounded-lg hover:bg-[#ff6b1a] hover:text-white transition"
                >
                  Add
                </button>
              </div>
            </div>
            <p className="text-xs text-brand-muted mt-1">{productDefaults.variantCodNote || "💳 COD available"}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function ProductHighlights({ highlights }) {
  const [expanded, setExpanded] = useState(false);
  const list = Array.isArray(highlights) ? highlights : [];
  if (list.length === 0) return null;

  return (
    <div className="w-full flex flex-col items-start border border-[#ff6b1a] rounded-[10px] overflow-hidden">
      <span className="text-[16px] font-semibold text-brand-ink px-2.5 py-2">
        Product Highlights
      </span>
      <div className="w-full h-px bg-gray-100" />
      <div
        className="text-[14px] text-brand-muted w-full transition-all duration-300 overflow-y-auto px-3 py-2"
        style={{ maxHeight: expanded ? "1000px" : "100px" }}
      >
        <ul className="m-0 list-disc pl-5 space-y-1">
          {list.map((h, i) => (
            <li key={i} className="leading-relaxed">
              {h.title && <strong className="text-brand-muted">{h.title}: </strong>}{h.text}
            </li>
          ))}
        </ul>
      </div>
      {list.length > 2 && (
        <button
          onClick={() => setExpanded((v) => !v)}
          className="w-full flex items-center justify-between px-[15px] py-[9px] font-semibold text-[#ff6b1a] text-sm mt-[7px]"
          style={{ backgroundImage: "linear-gradient(to right, transparent, rgba(255, 107, 26, 0.7))" }}
        >
          {expanded ? "Read Less" : "Read More"}
          <svg width="20" height="20" viewBox="0 0 24 24" fill="white" className={`transition duration-300 ${expanded ? "rotate-180" : ""}`}>
            <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
          </svg>
        </button>
      )}
    </div>
  );
}

// Product detail accordions built from per-product DB fields. Any field left blank in
// admin is skipped; if a product has NO content at all, we fall back to the global set.
function ProductAccordions({ product, fallback = [] }) {
  const [open, setOpen] = useState(null);

  const specs = Array.isArray(product.keySpecifications) ? product.keySpecifications.filter((s) => s.key) : [];

  const sections = [
    { id: "desc", title: "Description", body: product.fullDescription || product.description },
    { id: "specs", title: "Key Specifications", specs: product.keySpecificationsHtml ? [] : specs, body: product.keySpecificationsHtml || null },
    { id: "directions", title: "Directions to Use", body: product.directions },
    { id: "packing", title: "Packaging Info", body: product.packingInfo },
    { id: "additional", title: "Additional Information", body: product.additionalInfo },
    { id: "warranty", title: "Warranty", body: product.warrantyInfo },
    { id: "keyfeatures", title: "Key Features", body: product.keyFeatures },
    { id: "warrantyno", title: "Warranty No", body: product.warrantyNo },
    { id: "directionuse", title: "Direction of Use", body: product.directionOfUse },
  ].filter((s) => (s.specs && s.specs.length > 0) || !!(s.body && String(s.body).trim()));

  // Only this product's own content (fallback is empty now — no global default accordions).
  const list = sections.length
    ? sections
    : (fallback || []).map((s) => ({
        id: s.id,
        title: s.title,
        body: s.id === "desc" && product.description ? product.description : s.body,
      }));

  if (!list.length) return null;

  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
      {list.map((s) => (
        <div key={s.id}>
          <button
            onClick={() => setOpen(open === s.id ? null : s.id)}
            className="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition"
          >
            <span className="font-bold text-brand-ink">{s.title}</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" className={`text-brand-muted transition ${open === s.id ? "rotate-180" : ""}`}><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" /></svg>
          </button>
          {open === s.id && (
            <div className="px-5 pb-4 text-[15px] text-brand-ink leading-relaxed">
              {s.specs && s.specs.length > 0 ? (
                <table className="w-full text-sm">
                  <tbody>
                    {s.specs.map((sp, i) => (
                      <tr key={i} className="border-b border-gray-100 last:border-0">
                        <td className="py-2 pr-4 font-semibold text-brand-ink align-top w-2/5">{sp.key}</td>
                        <td className="py-2 text-brand-ink">{sp.value}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <RichText html={s.body} />
              )}
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

function FaqsSection({ faqs, productId, productName }) {
  const navigate = useAppNavigate();
  const visible = faqs.slice(0, 2);   // preview; "View all" opens the full Q&A page

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-bold text-brand-ink">FAQs</h3>
        <button
          onClick={() => navigate("qna", { id: productId, name: productName })}
          className="text-xs font-bold border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white px-3 py-1.5 rounded transition"
        >
          Get Instant Answer
        </button>
      </div>
      {faqs.length === 0 ? (
        <p className="text-sm text-brand-muted py-2">
          No questions yet. Have a doubt? Tap <span className="font-semibold text-orange-500">Get Instant Answer</span> to ask.
        </p>
      ) : (
      <div className="space-y-5">
        {visible.map((f) => (
          <div key={f.id}>
            <div className="flex items-start gap-2">
              <span className="bg-green-500 text-white text-[10px] font-bold uppercase px-2 py-1 rounded shrink-0">QUESTION</span>
              <p className="font-semibold text-brand-ink text-sm flex-1">{f.q}</p>
            </div>
            <p className="text-sm text-brand-muted mt-2 leading-relaxed">
              <span className="text-brand-ink font-semibold">Answer:</span> {f.a}
            </p>
          </div>
        ))}
      </div>
      )}
      {faqs.length > 2 && (
        <button
          onClick={() => navigate("qna", { id: productId, name: productName })}
          className="mt-4 w-full text-orange-500 font-bold text-sm flex items-center justify-center gap-1 hover:underline"
        >
          View all {faqs.length} questions →
        </button>
      )}
    </div>
  );
}

function InstantAnswerModal({ faqs, onClose }) {
  const [question, setQuestion] = useState("");
  const [answer, setAnswer] = useState(null);
  const [loading, setLoading] = useState(false);

  const ask = (e) => {
    e?.preventDefault();
    if (!question.trim()) return;
    setLoading(true);
    setAnswer(null);
    setTimeout(() => {
      const q = question.toLowerCase();
      const match = faqs.find((f) => {
        const keys = f.q.toLowerCase().split(/\W+/).filter((w) => w.length > 3);
        return keys.some((k) => q.includes(k));
      });
      setAnswer(match ? match.a : "Sorry, we don't have a direct answer for that yet. Our team will follow up at your registered email within 24 hours.");
      setLoading(false);
    }, 600);
  };

  const suggestions = faqs.slice(0, 3);

  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-center justify-center p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200">
          <div className="flex items-center gap-2">
            <span className="text-xl">🤖</span>
            <h3 className="font-bold text-brand-ink">Get Instant Answer</h3>
          </div>
          <button onClick={onClose} aria-label="Close" className="text-red-500 hover:bg-red-50 rounded-full p-1.5">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>

        <div className="p-5">
          <form onSubmit={ask} className="space-y-3">
            <label className="block text-sm font-semibold text-brand-ink">Ask anything about this product</label>
            <div className="flex items-stretch border border-gray-300 rounded-md overflow-hidden focus-within:border-orange-500">
              <input
                autoFocus
                type="text"
                placeholder="e.g. Is foot control included?"
                value={question}
                onChange={(e) => setQuestion(e.target.value)}
                className="flex-1 px-3 py-2.5 text-sm focus:outline-none"
              />
              <button
                type="submit"
                disabled={loading || !question.trim()}
                className="px-4 bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm disabled:opacity-60"
              >
                {loading ? "..." : "Ask"}
              </button>
            </div>
          </form>

          {!answer && !loading && (
            <div className="mt-4">
              <p className="text-xs text-brand-muted mb-2">Try one of these:</p>
              <div className="flex flex-wrap gap-2">
                {suggestions.map((s) => (
                  <button
                    key={s.id}
                    onClick={() => setQuestion(s.q)}
                    className="text-xs border border-gray-300 rounded-full px-3 py-1 hover:border-orange-500 hover:text-orange-600 text-brand-ink"
                  >
                    {s.q}
                  </button>
                ))}
              </div>
            </div>
          )}

          {loading && (
            <div className="mt-4 text-sm text-brand-muted flex items-center gap-2">
              <svg className="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 12a9 9 0 11-6.219-8.56" />
              </svg>
              Thinking...
            </div>
          )}

          {answer && (
            <div className="mt-4 bg-orange-50 border border-orange-200 rounded-lg p-4">
              <p className="text-xs font-bold text-orange-700 mb-1">ANSWER</p>
              <p className="text-sm text-brand-ink leading-relaxed">{answer}</p>
            </div>
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}

function PaymentOptionsCard() {
  const { paymentOptions = [] } = useSettings();
  const [modalOpen, setModalOpen] = useState(false);
  const [focusId, setFocusId] = useState(null);
  const items = (paymentOptions && paymentOptions.length ? paymentOptions : []);
  if (!items.length) return null;
  // Modal lists every option except the catch-all card row; preserves admin order.
  const modalItems = items.filter((i) => i.id !== "card");
  const renderIcon = (kind) => {
    switch (kind) {
      case "rupee":
        return <path d="M13.66 7c-.56-1.18-1.76-2-3.16-2H6V3h12v2h-3.26c.48.58.84 1.26 1.05 2H18v2h-2.02c-.25 2.8-2.61 5-5.48 5h-.73l6.73 7h-2.77L7 14v-2h3.5c1.76 0 3.22-1.3 3.46-3H6V7z" />;
      case "bank":
        return <path d="M4 10h3v7H4zm6.5 0h3v7h-3zM2 19h20v3H2zm15-9h3v7h-3zm-5-9L2 6v2h20V6z" />;
      case "card":
        return <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2m0 14H4v-6h16zm0-10H4V6h16z" />;
      default:
        return <path d="M13.66 7c-.56-1.18-1.76-2-3.16-2H6V3h12v2h-3.26c.48.58.84 1.26 1.05 2H18v2h-2.02c-.25 2.8-2.61 5-5.48 5h-.73l6.73 7h-2.77L7 14v-2h3.5c1.76 0 3.22-1.3 3.46-3H6V7z" />;
    }
  };
  return (
    <div className="border border-gray-200 rounded-[10px] px-[11px] py-[13px] flex flex-col gap-2 cursor-pointer">
      <label className="m-0 p-0 text-[16px] font-semibold text-brand-ink">Payment Options</label>
      <div className="grid grid-cols-12 gap-1">
        {items.map((p) => (
          <div
            key={p.id}
            onClick={() => { setFocusId(p.id); setModalOpen(true); }}
            className="h-[30px] border border-gray-200 flex items-center gap-1 px-1.5 cursor-pointer hover:border-[#3684bf]"
            style={{ gridColumn: `span ${p.span} / span ${p.span}` }}
          >
            {p.icon === "upi" ? (
              <span className="text-[10px] font-extrabold text-brand-ink tracking-wider">UPI</span>
            ) : (
              <svg className="h-5 w-5 text-brand-ink shrink-0" viewBox="0 0 24 24" fill="currentColor">
                {renderIcon(p.icon)}
              </svg>
            )}
            <span className="text-[12px] text-brand-muted truncate">{p.label}</span>
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setFocusId(p.id); setModalOpen(true); }}
              aria-label="Info"
              className="ml-auto shrink-0"
            >
              <svg className="h-3.5 w-3.5 text-brand-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6.02V6" />
                <path d="M12 10v8" />
              </svg>
            </button>
          </div>
        ))}
      </div>
      {modalOpen && <PaymentOptionsModal items={modalItems} initialId={focusId} onClose={() => setModalOpen(false)} />}
    </div>
  );
}

function PaymentOptionsModal({ items, initialId, onClose }) {
  const [open, setOpen] = useState(initialId || items[0]?.id || null);
  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-start justify-center p-4 overflow-y-auto"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="w-full max-w-lg bg-white rounded-2xl shadow-2xl my-8 overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex items-center justify-between px-5 py-4">
          <h2 className="text-xl font-bold text-brand-ink">Payment Options</h2>
          <button onClick={onClose} aria-label="Close" className="text-red-500 p-1 hover:bg-red-50 rounded-full">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </header>
        <div className="px-5 pb-5 space-y-3 max-h-[70vh] overflow-y-auto">
          {items.map((it) => (
            <div key={it.id} className="border border-gray-200 rounded-lg overflow-hidden">
              <button
                onClick={() => setOpen(open === it.id ? null : it.id)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50"
              >
                <span className="font-semibold text-brand-ink">{it.label}</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" className={`text-brand-ink transition ${open === it.id ? "rotate-180" : ""}`}>
                  <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
                </svg>
              </button>
              {open === it.id && (
                <div className="px-4 pb-4 text-sm text-brand-muted leading-relaxed">{it.desc}</div>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>,
    document.body
  );
}

function SmartBenefitsCard() {
  const { productBenefits = [], company = {} } = useSettings();
  const navigate = useAppNavigate();
  const brand = company.name || "";
  const icons = {
    shield: <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" />,
    x: <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />,
    refresh: <path d="M17.65 6.35A7.95 7.95 0 0012 4a8 8 0 108 8h-2a6 6 0 11-1.76-4.24L13 11h7V4l-2.35 2.35z" />,
    check: <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z" />,
  };

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      {/* Header: title can be a long brand name, so let it wrap and keep "Know more" pinned
          top-right without being squished. */}
      <div className="flex items-start justify-between gap-3 mb-4">
        <h3 className="font-bold text-brand-ink text-sm leading-snug">{brand} Benefits</h3>
        <button
          onClick={() => navigate("about")}
          className="shrink-0 inline-flex items-center gap-1 bg-transparent border-none text-[#f97316] font-semibold text-sm cursor-pointer normal-case whitespace-nowrap hover:opacity-80"
        >
          Know more
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
            <path d="M6.23 20.23 8 22l10-10L8 2 6.23 3.77 14.46 12z" />
          </svg>
        </button>
      </div>
      {/* 4 across, matching the reference: a soft rounded-square icon tile, then a centered
          2-line label. min-w-0 keeps each label inside its own column (no overlap). */}
      <div className="grid grid-cols-4 gap-x-3 gap-y-3">
        {productBenefits.map((b) => (
          <div key={b.id} className="flex flex-col items-center text-center min-w-0">
            <div className="w-12 h-12 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center mb-2">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="#0b1d3a">{icons[b.icon]}</svg>
            </div>
            <span className="block w-full text-[11px] font-medium text-brand-muted leading-tight break-words">{b.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function RatingsReviewsCard({ product, rating = 0, count = 0, reviews = [], onSubmitted, open: openProp, setOpen: setOpenProp, autoWrite = false }) {
  const [openLocal, setOpenLocal] = useState(false);
  // Controlled when the parent passes open/setOpen (e.g. the "Feedback" link), else local.
  const open = openProp !== undefined ? openProp : openLocal;
  const setOpen = setOpenProp || setOpenLocal;
  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <h3 className="font-bold text-brand-ink mb-3">Ratings & Reviews</h3>
      {/* Rating summary only when there's at least one review; otherwise prompt to be the first. */}
      {count > 0 ? (
        <div className="flex items-center gap-3 mb-4">
          <div className="flex items-center gap-1 bg-green-50 px-2 py-1 rounded">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z" /></svg>
            <span className="text-sm font-bold text-brand-ink">{Number(rating).toFixed(1)}</span>
          </div>
          <span className="text-sm text-brand-muted">{count} Reviews</span>
        </div>
      ) : (
        <p className="text-sm text-brand-muted mb-4">No reviews yet. Be the first to review this product.</p>
      )}
      <button
        onClick={() => setOpen(true)}
        className="w-full border border-gray-300 hover:border-[#3684bf] hover:text-[#3684bf] text-brand-ink font-bold py-2.5 rounded-md transition text-sm"
      >
        {count > 0 ? "View All Reviews" : "Write a Review"}
      </button>
      {open && (
        <ReviewsModal
          product={product}
          rating={rating}
          count={count}
          reviews={reviews}
          onSubmitted={onSubmitted}
          autoWrite={autoWrite}
          onClose={() => setOpen(false)}
        />
      )}
    </div>
  );
}

function ReviewsModal({ product, rating = 0, count = 0, reviews = [], onSubmitted, onClose, autoWrite = false }) {
  const { showToast } = useUI();
  const { company = {} } = useSettings();
  const brand = company.name || "";
  const [writing, setWriting] = useState(autoWrite);   // Feedback link opens straight into the form
  const totalReviews = count || reviews.length;
  const distribution = useMemo(() => {
    const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    reviews.forEach((r) => { counts[r.stars] = (counts[r.stars] || 0) + 1; });
    return counts;
  }, [reviews]);
  const totalCount = Object.values(distribution).reduce((s, c) => s + c, 0) || 1;

  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-start justify-center p-4 overflow-y-auto"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="w-full max-w-2xl bg-white rounded-2xl shadow-2xl my-8 overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex items-center justify-between px-6 py-4">
          <h2 className="text-2xl font-bold text-brand-ink">Ratings & Reviews</h2>
          <button onClick={onClose} aria-label="Close" className="text-brand-ink p-1 hover:bg-gray-100 rounded-full">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </header>

        <div className="bg-gray-100 px-6 py-5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center mb-4">
            <div>
              <div className="text-5xl font-bold text-brand-ink">{Number(rating).toFixed(1)}</div>
              <div className="flex text-amber-400 text-lg mt-1">
                {Array.from({ length: 5 }).map((_, i) => (
                  <span key={i}>{i < Math.round(rating) ? "★" : "☆"}</span>
                ))}
              </div>
              <p className="text-xs text-brand-muted mt-1">Based on {totalReviews} reviews</p>
            </div>
            <div className="space-y-1.5">
              {[5, 4, 3, 2, 1].map((star) => {
                const count = distribution[star] || 0;
                const pct = Math.round((count / totalCount) * 100);
                return (
                  <div key={star} className="flex items-center gap-2 text-xs">
                    <span className="w-3 text-brand-ink">{star}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z" /></svg>
                    <div className="flex-1 h-2 bg-white rounded-full overflow-hidden">
                      <div className="h-full bg-amber-400" style={{ width: `${pct}%` }} />
                    </div>
                    <span className="w-8 text-right text-brand-muted">{pct}%</span>
                  </div>
                );
              })}
            </div>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={() => setWriting((v) => !v)}
              className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold px-5 py-2 rounded-md text-sm"
            >
              {writing ? "Cancel" : "Write a Review"}
            </button>
          </div>
        </div>

        {writing && (
          <WriteReviewForm
            productId={product.id}
            showToast={showToast}
            onDone={() => { setWriting(false); onSubmitted?.(); }}
          />
        )}

        {reviews.length === 0 ? (
          <div className="px-6 py-10 text-center text-sm text-brand-muted">
            No reviews yet. Be the first to review this product.
          </div>
        ) : (
        <ul className="px-6 py-4 space-y-3 max-h-[55vh] overflow-y-auto">
          {reviews.map((r) => (
            <li key={r.id} className="bg-gray-50 rounded-xl p-4">
              <div className="flex items-start gap-3">
                <div className="relative shrink-0">
                  <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-bold text-[#3684bf] text-sm">
                    {r.name.split(" ").map((w) => w[0]).slice(0, 3).join("").toUpperCase()}
                  </div>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="#374151" className="absolute -bottom-0.5 -right-0.5 bg-white rounded-full p-0.5">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-4-4 1.41-1.41L11 12.17l5.59-5.59L18 8l-7 7z" />
                  </svg>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-brand-ink">{r.name}</p>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span className="text-amber-400 text-sm">{"★".repeat(r.stars)}{"☆".repeat(5 - r.stars)}</span>
                    <span className="text-xs text-brand-muted">{r.date}</span>
                  </div>
                  <p className="font-bold text-brand-ink mt-2">{r.title || `Happy with ${brand}`}</p>
                  <p className="text-sm text-brand-ink mt-1">{r.text}</p>
                  <div className="flex items-center gap-3 mt-3 text-xs text-brand-muted">
                    <span>Was this helpful?</span>
                    <button aria-label="Helpful" className="hover:text-[#3684bf]">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3zM7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3" />
                      </svg>
                    </button>
                    <button aria-label="Not helpful" className="hover:text-red-500">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M10 15v4a3 3 0 003 3l4-9V2H5.72a2 2 0 00-2 1.7l-1.38 9a2 2 0 002 2.3zM17 2h2.67A2.31 2.31 0 0122 4v7a2.31 2.31 0 01-2.33 2H17" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </li>
          ))}
        </ul>
        )}
      </div>
    </div>,
    document.body
  );
}

function WriteReviewForm({ productId, showToast, onDone }) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [stars, setStars] = useState(5);
  const [title, setTitle] = useState("");
  const [text, setText] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    if (!name.trim() || !text.trim()) {
      showToast?.("Please enter your name and review.", "error");
      return;
    }
    setBusy(true);
    try {
      const r = await api.submitReview({
        product: productId,
        name: name.trim(),
        email: email.trim() || undefined,
        rating: stars,
        title: title.trim() || undefined,
        review: text.trim(),
      });
      showToast?.(r.message || "Review submitted for moderation.", "success");
      onDone?.();
    } catch (err) {
      showToast?.(err.message || "Could not submit review.", "error");
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="px-6 py-4 border-b border-gray-100 space-y-3 bg-white">
      <div className="flex items-center gap-1">
        {[1, 2, 3, 4, 5].map((s) => (
          <button
            key={s}
            type="button"
            onClick={() => setStars(s)}
            className="text-2xl leading-none"
            aria-label={`${s} star`}
          >
            <span className={s <= stars ? "text-amber-400" : "text-gray-300"}>★</span>
          </button>
        ))}
        <span className="ml-2 text-sm text-brand-muted">{stars}/5</span>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Your name *"
          className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-[#3684bf]"
        />
        <input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="Email (optional)"
          type="email"
          className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-[#3684bf]"
        />
      </div>
      <input
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        placeholder="Review title (optional)"
        className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-[#3684bf]"
      />
      <textarea
        value={text}
        onChange={(e) => setText(e.target.value)}
        placeholder="Share your experience *"
        rows={3}
        className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-[#3684bf]"
      />
      <button
        type="submit"
        disabled={busy}
        className="bg-[#3684bf] hover:bg-[#1f5f96] disabled:opacity-60 text-white font-semibold px-5 py-2 rounded-md text-sm"
      >
        {busy ? "Submitting…" : "Submit Review"}
      </button>
      <p className="text-xs text-brand-muted">Reviews appear after admin approval.</p>
    </form>
  );
}

function RelatedProducts({ product }) {
  const { openModal } = useUI();
  const navigate = useAppNavigate();
  const { addToCart } = useCart();
  const { data: allProducts } = useProducts();
  const scroller = useRef(null);

  const onAdd = (p) => {
    addToCart(p, 1);
    openModal("cart");
  };

  const list = useMemo(() => {
    const others = allProducts.filter((p) => p.id !== product.id);
    // Prefer same-category products; if there aren't enough, top up with the rest.
    const sameCat = others.filter((p) => p.category === product.category);
    const merged = sameCat.length >= 8 ? sameCat : [...sameCat, ...others.filter((p) => p.category !== product.category)];
    return merged.slice(0, 8);
  }, [product, allProducts]);

  const scroll = (dir) => {
    paused.current = true;                       // manual nudge pauses auto-scroll briefly
    scroller.current?.scrollBy({ left: dir * 320, behavior: "smooth" });
    setTimeout(() => { paused.current = false; }, 1500);
  };

  // Auto-scroll the carousel. Pauses on hover; loops back to start at the end.
  const paused = useRef(false);
  useEffect(() => {
    const el = scroller.current;
    if (!el || list.length <= 1) return;
    const id = setInterval(() => {
      if (paused.current) return;
      const maxLeft = el.scrollWidth - el.clientWidth;
      if (maxLeft <= 0) return;
      if (el.scrollLeft >= maxLeft - 2) el.scrollTo({ left: 0, behavior: "smooth" });
      else el.scrollBy({ left: 1, behavior: "auto" });
    }, 30);
    return () => clearInterval(id);
  }, [list.length]);

  return (
    <section className="mt-10 pt-10 border-t border-gray-200">
      <h2 className="text-2xl font-bold text-brand-ink mb-5">
        <span className="text-brand-ink">You May Also</span> <span className="text-[#3684bf]">Like</span>
      </h2>

      <div className="relative">
        <button
          onClick={() => scroll(-1)}
          className="hidden md:flex absolute -left-3 top-[40%] -translate-y-1/2 w-10 h-10 bg-white border border-gray-200 rounded-full shadow-md items-center justify-center z-10 hover:bg-gray-50"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 6 8 12l6 6 1.41-1.41L10.83 12l4.58-4.59z" /></svg>
        </button>

        <div
          ref={scroller}
          onMouseEnter={() => { paused.current = true; }}
          onMouseLeave={() => { paused.current = false; }}
          className="flex gap-4 overflow-x-auto no-scrollbar pb-3"
        >
          {list.map((p) => (
            <article key={p.id} className="shrink-0 w-[260px] border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col">
              <div className="relative aspect-square bg-gray-50">
                <button onClick={() => navigate("product", { id: p.id, name: p.name })} className="w-full h-full flex items-center justify-center p-4">
                  <img src={p.image} alt={p.name} className="max-w-full max-h-full object-contain" />
                </button>
                {!p.inStock && (
                  <div className="absolute top-3 left-0 right-0 flex justify-center pointer-events-none">
                    <span className="bg-[#e57373] text-white font-bold text-xs tracking-wider px-6 py-2 uppercase shadow-sm">
                      Out of Stock
                    </span>
                  </div>
                )}
              </div>
              <div className="p-4 flex flex-col flex-1">
                <h3
                  onClick={() => navigate("product", { id: p.id, name: p.name })}
                  className="text-sm font-bold text-brand-ink line-clamp-2 mb-3 cursor-pointer hover:text-[#3684bf]"
                >
                  {p.name}
                </h3>
                <div className="flex items-center gap-2 flex-wrap mb-3">
                  <span className="text-xs text-brand-muted line-through">₹{p.mrp.toLocaleString("en-IN")}</span>
                  <span className="text-base font-bold text-brand-ink">₹{p.price.toLocaleString("en-IN")}</span>
                  {p.discount > 0 && (
                    <span className="text-xs font-bold text-green-600">| {p.discount}% OFF</span>
                  )}
                </div>
                {p.inStock === false ? (
                  <button
                    disabled
                    className="mt-auto w-full bg-gray-300 text-white font-bold text-sm py-2.5 rounded-md uppercase cursor-not-allowed"
                  >
                    Out of Stock
                  </button>
                ) : (
                  <button
                    onClick={() => onAdd(p)}
                    className="mt-auto w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold text-sm py-2.5 rounded-md uppercase"
                  >
                    Add to Cart
                  </button>
                )}
              </div>
            </article>
          ))}
        </div>

        <button
          onClick={() => scroll(1)}
          className="hidden md:flex absolute -right-3 top-[40%] -translate-y-1/2 w-10 h-10 bg-white border border-gray-200 rounded-full shadow-md items-center justify-center z-10 hover:bg-gray-50"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" /></svg>
        </button>
      </div>
    </section>
  );
}
