import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { allProducts, findProductById } from "../../data/products";
import { combos } from "../../data/combos";
import { events } from "../../data/events";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import { payments, company, tierOffers, productDefaults, sampleReviews as SAMPLE_REVIEWS, productBenefits, productContent } from "../../data/site";
import { categories } from "../../data/categories";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

export default function ProductDetailPage() {
  const { view, navigate, openModal, setSelectedProduct, showToast } = useUI();
  const { addToCart, items, updateQty, removeFromCart } = useCart();
  const { has, toggle } = useWishlist();

  const id = view?.params?.id;
  const product = useMemo(() => {
    const ev = events.find((e) => e.id === id);
    if (ev) {
      const discount = Math.round(((ev.mrp - ev.price) / ev.mrp) * 100);
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
    return (
      findProductById(id) ||
      combos.find((c) => c.id === id) ||
      allProducts[0]
    );
  }, [id]);

  const cartItem = items.find((i) => i.id === product.id && !i.variant);
  const qty = cartItem?.qty || 0;
  const outOfStock = product.inStock === false;

  const [pincode, setPincode] = useState("");
  const [pinMsg, setPinMsg] = useState("");
  const [pinInfo, setPinInfo] = useState(null);
  const [catalogueDownloaded, setCatalogueDownloaded] = useState(false);
  const [showDownloadModal, setShowDownloadModal] = useState(false);
  const [reviewsOpen, setReviewsOpen] = useState(false);
  const [crumbsOpen, setCrumbsOpen] = useState(false);
  const wished = has(product.id);

  const fromCategory = view?.params?.fromCategory;
  const crumbCategory =
    (fromCategory && categories.find((c) => c.id === fromCategory)) ||
    categories.find((c) => c.id === product.category) ||
    categories[0];
  const otherCategories = categories.filter((c) => c.id !== crumbCategory.id).slice(0, 8);

  const catalogueFile = `${product.name.split(" ")[0]}_${product.name.split(" ")[1] || "Catalogue"}.pdf`;
  const onDownloadCatalogue = () => {
    setCatalogueDownloaded(true);
    setShowDownloadModal(true);
  };
  const onOpenCatalogue = () => setShowDownloadModal(true);

  const displayQty = qty > 0 ? qty : 1;
  const discount = product.discount || Math.round(((product.mrp - product.price) / product.mrp) * 100);
  const activeTier = useMemo(() => {
    return [...tierOffers]
      .filter((t) => displayQty >= t.minQty)
      .sort((a, b) => b.minQty - a.minQty)[0];
  }, [displayQty]);
  const effectivePrice = activeTier ? product.price * (1 - activeTier.rate) : product.price;
  const subtotal = Math.round(effectivePrice * displayQty);
  const mrpTotal = product.mrp * displayQty;
  const off = Math.round(((mrpTotal - subtotal) / mrpTotal) * 100);
  const bulkSaved = activeTier ? Math.round((product.price - effectivePrice) * displayQty) : 0;

  const checkPin = () => {
    if (!/^\d{6}$/.test(pincode)) {
      setPinMsg("Please enter a valid 6-digit pincode.");
      setPinInfo(null);
      return;
    }
    const daysStr = String(productDefaults.deliveryDays || "");
    const m = daysStr.match(/(\d+)\s*-\s*(\d+)/) || daysStr.match(/(\d+)/);
    const baseDays = m ? parseInt(m[2] || m[1], 10) : 5;
    const pinSum = pincode.split("").reduce((s, d) => s + parseInt(d, 10), 0);
    const addDays = baseDays + (pinSum % 3);
    const eta = new Date();
    eta.setDate(eta.getDate() + addDays);
    const iso = `${eta.getFullYear()}-${String(eta.getMonth() + 1).padStart(2, "0")}-${String(eta.getDate()).padStart(2, "0")}`;
    setPinInfo({ ok: true, date: iso });
    setPinMsg("");
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

  return (
    <div className="max-w-[1400px] mx-auto px-4 py-5">
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
          <a className="text-[#3684bf] font-semibold hover:underline cursor-pointer">Feedback →</a>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {/* LEFT image gallery */}
        <div className="lg:col-span-5 lg:sticky lg:top-[110px] lg:self-start">
          <ProductGallery product={product} wished={wished} onWish={() => toggle(product.id)} />
        </div>

        {/* CENTER details */}
        <div className="lg:col-span-4 space-y-4">
          <div className="border border-gray-200 rounded-xl p-5">
            <div className="flex items-start gap-2 mb-2 flex-wrap">
              <h1 className="text-2xl font-bold text-brand-ink leading-snug flex-1 min-w-0">{product.name}</h1>
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

            <div className="relative mb-4">
              <button
                onClick={() => setReviewsOpen((v) => !v)}
                className="inline-flex items-center gap-2 border border-gray-300 rounded px-3 py-1.5 hover:border-gray-400 transition"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z" /></svg>
                <span className="text-sm font-semibold text-brand-ink">{product.rating?.toFixed(1) || productDefaults.rating.toFixed(1)}</span>
                <span className="text-sm text-brand-muted">| {product.reviews ?? productDefaults.reviews} Reviews</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" className={`text-brand-muted transition ${reviewsOpen ? "rotate-180" : ""}`}><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" /></svg>
              </button>

              {reviewsOpen && (
                <div className="absolute z-20 mt-2 w-full max-w-md bg-white border border-gray-200 rounded-lg shadow-xl p-4">
                  <div className="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <div className="text-3xl font-bold text-brand-ink">{product.rating?.toFixed(1) || productDefaults.rating.toFixed(1)}</div>
                    <div className="flex-1">
                      <div className="flex text-amber-400 text-sm">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <span key={i}>{i < Math.round(product.rating || 0) ? "★" : "☆"}</span>
                        ))}
                      </div>
                      <p className="text-xs text-brand-muted mt-0.5">Based on {product.reviews ?? 0} verified reviews</p>
                    </div>
                  </div>

                  <div className="space-y-3 mt-3 max-h-[260px] overflow-y-auto">
                    {SAMPLE_REVIEWS.map((r) => (
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

            {product.description && (
              <p className="text-sm text-brand-muted leading-relaxed">{product.description}</p>
            )}

            {catalogueDownloaded ? (
              <button
                onClick={onOpenCatalogue}
                className="mt-3 inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold text-sm transition"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M14 3h7v7M10 14L21 3M21 14v5a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h5" /></svg>
                Open Catalogue
              </button>
            ) : (
              <button
                onClick={onDownloadCatalogue}
                className="mt-3 inline-flex items-center gap-2 border border-[#3684bf] text-[#3684bf] px-3 py-1.5 rounded font-semibold text-sm hover:bg-blue-50"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2zM19 9h-4V3H9v6H5l7 7 7-7z" /></svg>
                Catalogue
              </button>
            )}
          </div>

          <div className="group border border-gray-200 rounded-xl p-5 transition-all duration-300 hover:border-[#3684bf] hover:shadow-lg hover:-translate-y-0.5 cursor-pointer">
            <h3 className="font-bold text-brand-ink mb-3 transition-colors duration-300 group-hover:text-[#3684bf]">Delivery Details</h3>
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
                className="text-[#3684bf] hover:bg-blue-50 active:bg-blue-100 font-medium text-sm uppercase tracking-wide px-2 py-1 rounded transition-colors"
              >
                Check
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
                  <span>Easy 7 days replacement available</span>
                </div>
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

          <AvailableVariants product={product} />

          <ProductHighlights highlights={productContent.highlights} />

          <ProductAccordions sections={productContent.accordions} description={product.description} />

          <FaqsSection faqs={productContent.faqs} productId={product.id} />
        </div>

        {/* RIGHT — Available Offers */}
        <div className="lg:col-span-3 space-y-4 lg:sticky lg:top-20 lg:self-start">
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

            <div className="border border-gray-200 rounded-lg overflow-hidden">
              <div className="grid grid-cols-2 bg-blue-50 text-xs font-bold text-brand-ink">
                <div className="px-3 py-2 border-r border-gray-200">Offer</div>
                <div className="px-3 py-2">Add on Savings</div>
              </div>
              {tierOffers.map((tier) => {
                const isActive = activeTier?.minQty === tier.minQty;
                return (
                  <div
                    key={tier.minQty}
                    className={`grid grid-cols-2 text-sm border-t border-gray-100 transition ${isActive ? "bg-orange-50" : ""}`}
                  >
                    <div className="px-3 py-3 border-r border-gray-200 text-brand-ink">
                      {tier.label} for{" "}
                      <span className="font-bold">{fmt(Math.round(product.price * (1 - tier.rate)))}</span> each
                    </div>
                    <div className="px-3 py-3 font-bold">{Math.round(tier.rate * 100)}%</div>
                  </div>
                );
              })}
            </div>

            <div className="grid grid-cols-2 gap-2 mt-4">
              <button
                onClick={() => {
                  const msg = encodeURIComponent(`Hi, I'm interested in ${product.name} (₹${product.price}). Is it available?`);
                  window.open(`https://wa.me/919328762586?text=${msg}`, "_blank");
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

          <RatingsReviewsCard product={product} />
        </div>
      </div>

      <RelatedProducts product={product} />


      {showDownloadModal && (
        <DownloadModal filename={catalogueFile} onClose={() => setShowDownloadModal(false)} />
      )}
    </div>
  );
}

function DownloadModal({ filename, onClose }) {
  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-center justify-center p-4"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-center"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="text-lg font-bold text-brand-ink text-left mb-4">Download Complete</h3>
        <div className="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-3">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="#16a34a">
            <path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
          </svg>
        </div>
        <p className="text-sm text-brand-ink mb-5">{filename} is ready!</p>
        <div className="flex items-center justify-center gap-3">
          <button
            onClick={onClose}
            className="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2 rounded transition"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M14 3h7v7M10 14L21 3M21 14v5a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h5" /></svg>
            Open File
          </button>
          <button
            onClick={onClose}
            className="border border-gray-300 hover:border-gray-400 text-brand-ink font-semibold px-5 py-2 rounded transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}

function ProductGallery({ product, wished, onWish }) {
  const images = (product.images?.length ? product.images : [product.image]) || [];
  const [idx, setIdx] = useState(0);
  const [zoom, setZoom] = useState(null);
  const [hovering, setHovering] = useState(false);
  const [panelRect, setPanelRect] = useState(null);

  const current = images[idx] || product.image;
  const prev = () => { setIdx((i) => (i - 1 + images.length) % images.length); setZoom(null); setHovering(false); };
  const next = () => { setIdx((i) => (i + 1) % images.length); setZoom(null); setHovering(false); };

  useEffect(() => { setIdx(0); }, [product.id]);

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
              <div className="hidden group-hover/thumb:block absolute left-1/2 -translate-x-1/2 bottom-full mb-2 w-[260px] h-[260px] bg-white border border-gray-200 rounded-xl shadow-2xl p-3 z-40 pointer-events-none">
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

function AvailableVariants({ product }) {
  const { addToCart } = useCart();
  const { openModal, navigate } = useUI();
  const [tab, setTab] = useState("All");

  const variantList = useMemo(() => {
    return allProducts
      .filter((p) => p.id !== product.id && p.category === product.category)
      .slice(0, 5);
  }, [product]);

  if (variantList.length === 0) return null;

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <h3 className="font-bold text-brand-ink mb-3">Available Variants</h3>
      <div className="flex items-center gap-2 mb-4">
        {["All", "Others"].map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`text-xs font-bold px-3 py-1.5 rounded-full transition ${tab === t ? "bg-[#3684bf] text-white" : "text-brand-ink hover:bg-gray-100"}`}
          >
            {t}
          </button>
        ))}
      </div>

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
              <p className="text-xs text-brand-muted">📦 Get it by Thu, May 28</p>
              <div className="flex gap-2">
                <button
                  onClick={() => navigate("product", { id: v.id })}
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
            <p className="text-xs text-brand-muted mt-1">💳 COD available</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function ProductHighlights({ highlights }) {
  const [expanded, setExpanded] = useState(false);

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
          {highlights.map((h, i) => (
            <li key={i} className="leading-relaxed">
              <strong className="text-brand-ink">{h.title}: </strong>{h.text}
            </li>
          ))}
        </ul>
      </div>
      {highlights.length > 2 && (
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

function ProductAccordions({ sections, description }) {
  const [open, setOpen] = useState(null);
  return (
    <div className="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
      {sections.map((s) => (
        <div key={s.id}>
          <button
            onClick={() => setOpen(open === s.id ? null : s.id)}
            className="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition"
          >
            <span className="font-bold text-brand-ink">{s.title}</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" className={`text-brand-muted transition ${open === s.id ? "rotate-180" : ""}`}><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" /></svg>
          </button>
          {open === s.id && (
            <div className="px-5 pb-4 text-sm text-brand-muted leading-relaxed">
              {s.id === "desc" && description ? description : s.body}
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

function FaqsSection({ faqs, productId }) {
  const { navigate } = useUI();
  const [showAll, setShowAll] = useState(false);
  const visible = showAll ? faqs : faqs.slice(0, 2);

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-bold text-brand-ink">FAQs</h3>
        <button
          onClick={() => navigate("qna", { id: productId })}
          className="text-xs font-bold border border-orange-500 text-orange-500 hover:bg-orange-500 hover:text-white px-3 py-1.5 rounded transition"
        >
          Get Instant Answer
        </button>
      </div>
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
      <button
        onClick={() => navigate("qna", { id: productId })}
        className="mt-4 w-full text-orange-500 font-bold text-sm flex items-center justify-center gap-1 hover:underline"
      >
        View all {faqs.length + 3} questions →
      </button>
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
  const [modalOpen, setModalOpen] = useState(false);
  const [focusId, setFocusId] = useState(null);
  const items = [
    { id: "cod", label: "COD", icon: "rupee", span: 5, desc: "Experience Convenience and Trust with Our Cash on Delivery (COD) Payment Service" },
    { id: "nb", label: "Net Banking", icon: "bank", span: 7, desc: "Net banking, also known as online banking or internet banking, is a digital platform that allows customers to perform various financial transactions and manage their bank accounts through the internet." },
    { id: "upi", label: "UPI", icon: "upi", span: 5, desc: "UPI (Unified Payments Interface) is a real-time payment system that allows you to link multiple bank accounts to a single mobile application, enabling seamless and instant money transfers and payments." },
    { id: "partial", label: "Partial Payment", icon: "rupee", span: 7, desc: "You can partially pay for your order now and the remaining amount can be paid at the time of delivery." },
    { id: "card", label: "Credit / Debit cards", icon: "card", span: 12, desc: "Pay securely with your Credit or Debit card via our trusted payment gateway." },
  ];
  const modalOrder = ["cod", "partial", "upi", "nb"];
  const modalItems = modalOrder.map((id) => items.find((i) => i.id === id)).filter(Boolean);
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
  const { navigate } = useUI();
  const icons = {
    shield: <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" />,
    x: <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />,
    refresh: <path d="M17.65 6.35A7.95 7.95 0 0012 4a8 8 0 108 8h-2a6 6 0 11-1.76-4.24L13 11h7V4l-2.35 2.35z" />,
    check: <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z" />,
  };

  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-bold text-brand-ink">Smart Dental Innovation Benefits</h3>
        <button
          onClick={() => navigate("about")}
          className="inline-flex items-center gap-1.5 bg-transparent border-none text-[#f97316] font-semibold cursor-pointer normal-case whitespace-nowrap hover:opacity-80"
        >
          Know more
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
            <path d="M6.23 20.23 8 22l10-10L8 2 6.23 3.77 14.46 12z" />
          </svg>
        </button>
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
        {productBenefits.map((b) => (
          <div key={b.id} className="flex flex-col items-center text-center">
            <div className="w-12 h-12 rounded-full bg-orange-50 flex items-center justify-center mb-2">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="#0b1d3a">{icons[b.icon]}</svg>
            </div>
            <span className="text-[11px] font-semibold text-brand-ink leading-tight">{b.label}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function RatingsReviewsCard({ product }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="border border-gray-200 rounded-xl p-5">
      <h3 className="font-bold text-brand-ink mb-3">Ratings & Reviews</h3>
      <div className="flex items-center gap-3 mb-4">
        <div className="flex items-center gap-1 bg-green-50 px-2 py-1 rounded">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z" /></svg>
          <span className="text-sm font-bold text-brand-ink">{product.rating?.toFixed(1) || "0.0"}</span>
        </div>
        <span className="text-sm text-brand-muted">{product.reviews ?? 0} Reviews</span>
      </div>
      <button
        onClick={() => setOpen(true)}
        className="w-full border border-gray-300 hover:border-[#3684bf] hover:text-[#3684bf] text-brand-ink font-bold py-2.5 rounded-md transition text-sm"
      >
        View All Reviews
      </button>
      {open && <ReviewsModal product={product} onClose={() => setOpen(false)} />}
    </div>
  );
}

function ReviewsModal({ product, onClose }) {
  const rating = product.rating || 0;
  const totalReviews = product.reviews ?? SAMPLE_REVIEWS.length;
  const distribution = useMemo(() => {
    const counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    SAMPLE_REVIEWS.forEach((r) => { counts[r.stars] = (counts[r.stars] || 0) + 1; });
    return counts;
  }, []);
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
              <div className="text-5xl font-bold text-brand-ink">{rating.toFixed(1)}</div>
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
            <button className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-semibold px-5 py-2 rounded-md text-sm">
              Write a Review
            </button>
            <button className="bg-white border border-gray-300 hover:border-gray-400 text-brand-ink font-semibold px-5 py-2 rounded-md text-sm inline-flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2L9.91 8.84 3 9.27l5.46 4.73L6.91 21 12 17.27 17.09 21l-1.55-6.99L21 9.27l-6.91-.43z" />
              </svg>
              Review Summary
            </button>
          </div>
        </div>

        <ul className="px-6 py-4 space-y-3 max-h-[55vh] overflow-y-auto">
          {SAMPLE_REVIEWS.map((r) => (
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
                  <p className="font-bold text-brand-ink mt-2">{r.title || "Happy with Smartdental Innovations"}</p>
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
      </div>
    </div>,
    document.body
  );
}

function RelatedProducts({ product }) {
  const { navigate, openModal } = useUI();
  const { addToCart } = useCart();
  const scroller = useRef(null);

  const onAdd = (p) => {
    addToCart(p, 1);
    openModal("cart");
  };

  const list = useMemo(() => {
    return allProducts.filter((p) => p.id !== product.id).slice(0, 8);
  }, [product]);

  const scroll = (dir) => {
    scroller.current?.scrollBy({ left: dir * 320, behavior: "smooth" });
  };

  return (
    <section className="mt-10 pt-10 border-t border-gray-200">
      <h2 className="text-2xl font-bold text-brand-ink mb-5">
        <span className="text-brand-ink">Related</span> <span className="text-[#3684bf]">Products</span>
      </h2>

      <div className="relative">
        <button
          onClick={() => scroll(-1)}
          className="hidden md:flex absolute -left-3 top-[40%] -translate-y-1/2 w-10 h-10 bg-white border border-gray-200 rounded-full shadow-md items-center justify-center z-10 hover:bg-gray-50"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 6 8 12l6 6 1.41-1.41L10.83 12l4.58-4.59z" /></svg>
        </button>

        <div ref={scroller} className="flex gap-4 overflow-x-auto no-scrollbar pb-3">
          {list.map((p) => (
            <article key={p.id} className="shrink-0 w-[260px] border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col">
              <div className="relative aspect-square bg-gray-50">
                <button onClick={() => navigate("product", { id: p.id })} className="w-full h-full flex items-center justify-center p-4">
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
                  onClick={() => navigate("product", { id: p.id })}
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
