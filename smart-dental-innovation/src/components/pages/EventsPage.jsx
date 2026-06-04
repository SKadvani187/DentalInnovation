import { useEffect, useRef, useState } from "react";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import { useEvents } from "../../hooks/useApiData";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;

export default function EventsPage() {
  const { view, navigate } = useUI();
  const { data: events } = useEvents();
  const id = view?.params?.id;
  const event = events.find((e) => e.id === id) || events[0];

  if (!id) {
    return <EventsList events={events} onPick={(e) => navigate("events", { id: e.id })} />;
  }
  return <EventDetail event={event} />;
}

function EventsList({ events, onPick }) {
  return (
    <div className="max-w-[1400px] mx-auto px-4 py-8">
      <h1 className="text-2xl font-bold text-brand-ink mb-6">Events & Courses</h1>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        {events.map((e) => (
          <article
            key={e.id}
            onClick={() => onPick(e)}
            className="border border-gray-200 rounded-xl bg-white overflow-hidden flex flex-col hover:shadow-md transition cursor-pointer"
          >
            <div className="aspect-video bg-gray-50">
              <img src={e.image} alt={e.name} className="w-full h-full object-cover" />
            </div>
            <div className="p-4">
              <span className="inline-block bg-[#3684bf] text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full mb-2">
                {e.type}
              </span>
              <h3 className="text-base font-bold text-brand-ink line-clamp-2 mb-2">{e.name}</h3>
              <div className="flex items-center gap-2">
                <span className="text-xs text-brand-muted line-through">₹{e.mrp.toLocaleString("en-IN")}</span>
                <span className="text-base font-bold text-brand-ink">{fmt(e.price)}</span>
                <span className="text-xs font-bold text-green-600">
                  ({Math.round(((e.mrp - e.price) / e.mrp) * 100)}% OFF)
                </span>
              </div>
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}

function EventDetail({ event }) {
  const { navigate, openModal } = useUI();
  const { addToCart } = useCart();
  const { toggle: toggleWish, has: hasWish } = useWishlist();
  const [qty, setQty] = useState(1);
  const [pincode, setPincode] = useState("");
  const [pinMsg, setPinMsg] = useState("");
  const [crumbOpen, setCrumbOpen] = useState(false);
  const crumbRef = useRef(null);
  const extras = event.extraCategories || [];

  useEffect(() => {
    if (!crumbOpen) return;
    const onClick = (e) => {
      if (crumbRef.current && !crumbRef.current.contains(e.target)) setCrumbOpen(false);
    };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, [crumbOpen]);

  const discount = Math.round(((event.mrp - event.price) / event.mrp) * 100);
  const subtotal = event.price * qty;
  const mrpTotal = event.mrp * qty;
  const offTotal = Math.round(((mrpTotal - subtotal) / mrpTotal) * 100);
  const wished = hasWish(event.id);

  const checkPincode = () => {
    if (!/^\d{6}$/.test(pincode)) {
      setPinMsg("Please enter a valid 6-digit pincode.");
      return;
    }
    setPinMsg(`Delivery available to ${pincode} in 3–5 business days.`);
  };

  return (
    <div className="max-w-[1400px] mx-auto px-4 py-5">
      <div className="flex items-center justify-between flex-wrap gap-2 mb-4 text-sm">
        <nav className="flex items-center gap-2 text-brand-muted">
          <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">{event.breadcrumb[0]}</button>
          <span>/</span>
          <button onClick={() => navigate("category")} className="hover:text-[#3684bf]">{event.breadcrumb[1]}</button>
          <span>/</span>
          <span className="text-brand-ink font-semibold">{event.breadcrumb[2]}</span>
          {extras.length > 0 && (
            <div ref={crumbRef} className="relative">
              <button
                onClick={() => setCrumbOpen((v) => !v)}
                className="bg-gray-200 text-xs px-2 py-0.5 rounded-full text-brand-ink hover:bg-gray-300 cursor-pointer"
              >
                +{extras.length} more
              </button>
              {crumbOpen && (
                <div className="absolute left-0 top-full mt-2 w-[220px] bg-white border border-gray-200 rounded-lg shadow-xl z-[1200] overflow-hidden">
                  <div className="px-4 py-2 text-[11px] font-bold text-brand-muted uppercase tracking-wider border-b border-gray-100">
                    All Categories
                  </div>
                  <ul className="py-1">
                    {extras.map((c) => (
                      <li key={c}>
                        <button
                          onClick={() => {
                            setCrumbOpen(false);
                            navigate("category", { category: c.toLowerCase().replace(/\s+/g, "-") });
                          }}
                          className="w-full text-left px-4 py-2 text-sm text-brand-ink hover:bg-gray-50"
                        >
                          {c}
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          )}
        </nav>
        <div className="text-sm text-brand-muted">
          Would you like to tell us about the product?{" "}
          <a className="text-[#3684bf] font-semibold hover:underline cursor-pointer">Feedback →</a>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {/* LEFT: image */}
        <div className="lg:col-span-5">
          <div className="relative bg-white border border-gray-200 rounded-xl overflow-hidden aspect-square">
            <button
              onClick={() => toggleWish(event.id)}
              aria-label="Wishlist"
              className="absolute top-3 left-3 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center hover:bg-gray-50"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill={wished ? "#ef4444" : "none"} stroke={wished ? "#ef4444" : "#374151"} strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
              </svg>
            </button>
            <button
              aria-label="Share"
              className="absolute top-3 right-3 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center hover:bg-gray-50"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#374151" strokeWidth="2">
                <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
                <path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
              </svg>
            </button>
            <img src={event.image} alt={event.name} className="w-full h-full object-cover" />
          </div>
        </div>

        {/* CENTER: details */}
        <div className="lg:col-span-4 space-y-4">
          <div className="border border-gray-200 rounded-xl p-5">
            <div className="flex items-start gap-2 mb-2">
              <h1 className="text-xl font-bold text-brand-ink leading-snug">{event.name}</h1>
            </div>
            <div className="mb-3">
              <span className="inline-block bg-[#3684bf] text-white text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full">
                {event.type}
              </span>
            </div>
            <p className="text-sm mb-3">
              <span className="text-brand-muted">Brand : </span>
              <a className="text-[#3684bf] font-semibold underline cursor-pointer">{event.brand}</a>
            </p>

            <div className="inline-flex items-center gap-2 border border-gray-300 rounded px-3 py-1.5 mb-4">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.27 5.82 22 7 14.14l-5-4.87 6.91-1.01z" /></svg>
              <span className="text-sm font-semibold text-brand-ink">{event.rating.toFixed(1)}</span>
              <span className="text-sm text-brand-muted">| {event.reviews} Reviews</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" className="text-brand-muted"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" /></svg>
            </div>

            <div className="flex items-center justify-between gap-3 mb-3">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-xl font-bold text-brand-ink">{fmt(event.price)}</span>
                <span className="text-sm text-brand-muted line-through">₹{event.mrp.toLocaleString("en-IN")}</span>
                <span className="text-sm font-bold text-green-600">({discount}% OFF)</span>
              </div>
              <button
                onClick={() => { addToCart(event, qty); openModal("cart"); }}
                className="px-5 py-2 border-2 border-orange-500 text-orange-500 font-bold rounded-md hover:bg-orange-500 hover:text-white transition"
              >
                ADD
              </button>
            </div>

            <p className="text-sm text-brand-muted italic">"{event.description}"</p>
          </div>

          <div className="border border-gray-200 rounded-xl p-5">
            <h3 className="font-bold text-brand-ink mb-3">Delivery Details</h3>
            <div className="flex items-stretch border border-gray-300 rounded-md overflow-hidden">
              <span className="flex items-center px-2 bg-gray-50 text-xs">🇮🇳</span>
              <input
                type="tel"
                inputMode="numeric"
                maxLength={6}
                placeholder="Enter pincode"
                value={pincode}
                onChange={(e) => setPincode(e.target.value.replace(/\D/g, "").slice(0, 6))}
                className="flex-1 px-3 py-2 text-sm focus:outline-none"
              />
              <button onClick={checkPincode} className="px-4 text-[#3684bf] font-bold text-sm hover:bg-blue-50">
                CHECK
              </button>
            </div>
            <p className={`text-xs mt-2 ${pinMsg.startsWith("Please") ? "text-red-600" : "text-brand-muted"}`}>
              {pinMsg || "Please enter PIN code to check delivery time & Pay on Delivery Availability"}
            </p>
          </div>
        </div>

        {/* RIGHT: order summary */}
        <div className="lg:col-span-3 space-y-4">
          <div className="border border-gray-200 rounded-xl p-5">
            <div className="space-y-2 mb-4">
              <div className="text-base">
                <span className="text-brand-muted">Subtotal: </span>
                <span className="font-bold text-[#ff6b1a] text-lg">{fmt(subtotal)}</span>
              </div>
              <div className="text-sm">
                <span className="text-brand-muted">MRP Total - </span>
                <span className="line-through text-brand-muted">₹{mrpTotal.toLocaleString("en-IN")}</span>
                <span className="ml-2 text-green-600 font-bold">{offTotal}% off</span>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <span className="text-[#3684bf] font-semibold">Item - {qty}</span>
                <div className="ml-auto inline-flex items-center border border-gray-300 rounded">
                  <button onClick={() => setQty((q) => Math.max(1, q - 1))} className="w-7 h-7 hover:bg-gray-50">−</button>
                  <span className="w-8 text-center text-sm">{qty}</span>
                  <button onClick={() => setQty((q) => q + 1)} className="w-7 h-7 hover:bg-gray-50">+</button>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <button className="flex items-center justify-center gap-1 border border-gray-300 rounded-md px-2 py-2.5 text-xs font-semibold hover:border-green-500 hover:text-green-600">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>
                <div className="leading-tight text-left">
                  <div className="text-[10px] text-brand-muted">Buy on</div>
                  <div className="font-bold">WhatsApp</div>
                </div>
              </button>
              <button className="relative overflow-hidden bg-yellow-400 hover:bg-yellow-500 text-black font-bold text-sm py-2.5 rounded-md transition">
                BUY NOW
              </button>
            </div>
          </div>

          <div className="border border-gray-200 rounded-xl p-5 text-center">
            <p className="text-sm text-brand-ink mb-3">Want to buy even more quantity ?</p>
            <button className="w-full border border-[#3684bf] text-[#3684bf] font-bold text-sm py-2.5 rounded-md uppercase hover:bg-[#3684bf] hover:text-white transition">
              Get Bulk Quote Now
            </button>
          </div>

          <div className="border border-gray-200 rounded-xl p-5">
            <h3 className="font-bold text-brand-ink mb-3">Payment Options</h3>
            <div className="grid grid-cols-2 gap-2">
              {[
                { label: "COD", icon: "₹" },
                { label: "Net Banking", icon: "🏦" },
                { label: "UPI", icon: "UPI" },
                { label: "Partial Payment", icon: "₹" },
                { label: "Credit / Debit cards", icon: "💳", span: true },
              ].map((p) => (
                <button
                  key={p.label}
                  className={`flex items-center justify-center gap-1 border border-gray-300 rounded-md px-2 py-2 text-xs font-semibold hover:border-[#3684bf] ${p.span ? "col-span-2" : ""}`}
                >
                  <span>{p.icon}</span>
                  <span>{p.label}</span>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" className="text-brand-muted">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                  </svg>
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
