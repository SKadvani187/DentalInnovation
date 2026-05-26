import React, { useState, useRef, useEffect } from 'react';
import { useUI } from "../../context/UIContext";
import { useAuth } from "../../context/AuthContext";
import { useCart } from "../../context/CartContext";
import { pricePresets } from "../../data/site";

export default function NavigationHeader() {
  const { openModal, navigate } = useUI();
  const { user, logout } = useAuth();
  const { itemCount } = useCart();
  const [priceOpen, setPriceOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [mobilePriceOpen, setMobilePriceOpen] = useState(false);
  const closeTimer = useRef(null);

  const openPrice = () => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    setPriceOpen(true);
  };
  const schedulePriceClose = () => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
    closeTimer.current = setTimeout(() => setPriceOpen(false), 150);
  };

  useEffect(() => {
    document.body.style.overflow = mobileOpen ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [mobileOpen]);

  const subNavButtonStyle = "text-[15px] font-semibold flex items-center gap-[5px] whitespace-nowrap border-0 border-solid border-[var(--border-color-light)] px-[10px] py-[2px] rounded-[8px] bg-none cursor-pointer";

  const SEARCH_PHRASES = [
    "Search over 1,000 Dental Products",
    "Find Endodontics, Implants, Burs...",
    "Shop Handpieces, Cautery & more",
    "Best deals on Restorative Kits",
  ];
  const [phraseIdx, setPhraseIdx] = useState(0);
  const [phase, setPhase] = useState("in"); // "in" | "out"

  useEffect(() => {
    const phrase = SEARCH_PHRASES[phraseIdx];
    const lettersDuration = 30 * phrase.length;
    const inHold = 450 + lettersDuration;
    const visibleHold = 2200;
    const outAnim = 350 + lettersDuration;

    const outTimer = setTimeout(() => setPhase("out"), inHold + visibleHold);
    const nextTimer = setTimeout(() => {
      setPhase("in");
      setPhraseIdx((i) => (i + 1) % SEARCH_PHRASES.length);
    }, inHold + visibleHold + outAnim);

    return () => { clearTimeout(outTimer); clearTimeout(nextTimer); };
  }, [phraseIdx]);

  const currentPhrase = SEARCH_PHRASES[phraseIdx];

  const goAndClose = (fn) => () => { setMobileOpen(false); fn(); };

  return (
    <div className="sticky top-0 flex flex-col z-[1000] w-full">

      {/* ROW 1: Main Top Bar */}
      <div className="relative w-full h-[60px] sm:h-[65px] border-0 border-b border-solid border-[rgba(var(--border-color-1-rgb),0.5)] flex items-center justify-between z-10 transition-all duration-300 bg-[rgba(var(--background-primary-rgb),0.7)] backdrop-blur-[30px] px-3 sm:px-6 lg:px-[35px] gap-2 sm:gap-[10px]">

        {/* Mobile hamburger */}
        <button
          onClick={() => setMobileOpen(true)}
          className="lg:hidden w-9 h-9 flex items-center justify-center text-[var(--text-primary)] shrink-0"
          aria-label="Open menu"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round">
            <path d="M3 6h18M3 12h18M3 18h18" />
          </svg>
        </button>

        {/* Logo & Brand Name Container */}
        <div
          onClick={() => navigate("home")}
          className="flex items-center gap-2 h-[40px] cursor-pointer select-none shrink-0"
        >
          <img
            src="./src/assets/logo.png"
            alt="Logo Icon"
            className="h-[30px] sm:h-[38px] w-auto object-contain opacity-100 transition-opacity duration-500 ease-in-out"
          />
          <span className="hidden sm:inline text-xl font-extrabold tracking-tight text-gray-900 whitespace-nowrap">
            Dent
            <span className="text-[#1976d2]">Inno</span>
          </span>
        </div>

        {/* Desktop centered search */}
        <div
          onClick={() => openModal("search")}
          role="button"
          tabIndex={0}
          onKeyDown={(e) => (e.key === "Enter" || e.key === " ") && openModal("search")}
          className="hidden md:flex absolute left-1/2 -translate-x-1/2 w-[45%] lg:w-[40%] h-[44px] lg:h-[52px] border border-solid border-[var(--text-primary-2)] rounded-[100px] min-w-[260px] items-center px-[16px] lg:px-[20px] gap-[10px] bg-[var(--background-primary)] cursor-pointer transition-all duration-300 hover:shadow-md"
        >
          <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" className="h-[18px] lg:h-[20px] shrink-0">
            <path
              d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z"
              stroke="black"
            />
          </svg>
          <div className="inline-flex flex-wrap perspective-[1000px] min-h-[1.2em] overflow-hidden text-sm lg:text-base whitespace-nowrap">
            <div key={`${phraseIdx}-${phase}`} className="inline-flex flex-nowrap">
              {currentPhrase.split("").map((char, index) => (
                <span
                  key={index}
                  className={`search-letter ${phase}`}
                  style={{ animationDelay: `${index * 30}ms` }}
                >
                  {char === " " ? " " : char}
                </span>
              ))}
            </div>
          </div>
        </div>

        {/* Mobile search icon */}
        <button
          onClick={() => openModal("search")}
          className="md:hidden w-9 h-9 ml-auto flex items-center justify-center text-[var(--text-primary)] shrink-0"
          aria-label="Search"
        >
          <svg viewBox="0 0 15 15" fill="none" className="h-[18px]">
            <path
              d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z"
              stroke="currentColor"
            />
          </svg>
        </button>

        {/* Right Actions Block */}
        <div className="flex items-center gap-1 sm:gap-[10px] shrink-0">
          {/* Account Button */}
          <button
            onClick={() => (user ? navigate("account") : openModal("auth"))}
            className="font-bold rounded-[8px] flex items-center cursor-pointer px-2 sm:px-3 py-1 text-[var(--text-primary)]"
            aria-label="Account"
          >
            <span className="sm:mr-2">
              <div className="bg-[var(--main)] h-[25px] w-[25px] flex items-center justify-center rounded-[100px]">
                <svg className="text-white h-[18px]" focusable="false" aria-hidden="true" viewBox="0 0 24 24">
                  <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
                </svg>
              </div>
            </span>
            {user ? (
              <span className="hidden sm:flex flex-col items-start leading-tight text-left">
                <span className="text-[11px] text-brand-muted normal-case">Hi {user.name.split(" ")[0].toUpperCase()},</span>
                <span className="text-sm font-bold text-brand-ink">Account</span>
              </span>
            ) : (
              <span className="hidden sm:inline normal-case font-medium text-[var(--text-primary)]">You</span>
            )}
          </button>

          {/* Wishlist Button */}
          <button
            onClick={() => (user ? navigate("wishlist") : openModal("auth"))}
            className="hidden sm:inline-flex text-[var(--text-primary)] p-2 cursor-pointer"
            aria-label="Wishlist"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24">
              <path fillRule="evenodd" d="M15.99 3.75c-1.311-.018-2.54.427-3.366 1.667a.75.75 0 01-1.248 0C10.554 4.184 9.303 3.75 8 3.75 5.373 3.75 2.75 5.955 2.75 9c0 3.178 2.055 5.99 4.375 8.065a20.921 20.921 0 003.27 2.397c.474.278.881.486 1.19.622a3.82 3.82 0 00.415.157l.05-.015c.088-.027.21-.074.365-.142.309-.136.716-.344 1.19-.622a20.92 20.92 0 003.27-2.397C19.195 14.99 21.25 12.18 21.25 9c0-3.037-2.616-5.211-5.26-5.25zm-3.992.06c1.13-1.165 2.6-1.58 4.013-1.56C19.366 2.3 22.75 5.039 22.75 9c0 3.822-2.445 7.01-4.875 9.184a22.424 22.424 0 01-3.51 2.572c-.512.3-.972.537-1.346.702-.187.082-.36.15-.515.2-.136.042-.32.092-.504.092-.183 0-.368-.05-.504-.093a5.262 5.262 0 01-.515-.199 13.403 13.403 0 01-1.345-.702 22.422 22.422 0 01-3.511-2.572C3.695 16.01 1.25 12.822 1.25 9c0-3.953 3.377-6.75 6.75-6.75 1.375 0 2.86.397 3.998 1.56z" />
            </svg>
          </button>

          {/* Cart Button */}
          <button
            onClick={() => openModal("cart")}
            className="relative font-bold rounded-[8px] flex items-center bg-[var(--main)] text-white px-2.5 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm uppercase tracking-wider cursor-pointer"
          >
            <span className="sm:mr-2 relative">
              <svg className="h-[16px] w-[16px] sm:h-[18px] sm:w-[18px]" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2m-8 4c0 .55-.45 1-1 1s-1-.45-1-1V8h2zm2-6c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2m4 6c0 .55-.45 1-1 1s-1-.45-1-1V8h2z" />
              </svg>
            </span>
            <span className="hidden sm:inline">cart</span>
            {itemCount > 0 ? <span className="ml-1">({itemCount})</span> : null}
          </button>
        </div>
      </div>

      {/* ROW 2: Sub-Navigation (desktop only) */}
      <div className="hidden lg:flex h-[40px] bg-[rgba(var(--background-primary-rgb),0.7)] backdrop-blur-[30px] border-0 border-b border-solid border-[rgba(var(--border-color-1-rgb),0.2)] items-center justify-center gap-[24px] xl:gap-[45px] w-full overflow-visible px-[10px] no-scrollbar">
        <button onClick={() => navigate("category")} className={subNavButtonStyle}>Category</button>
        <button onClick={() => navigate("offers")} className={subNavButtonStyle}>Offer Zone</button>
        <button onClick={() => navigate("combos")} className={subNavButtonStyle}>Combos</button>
        <button onClick={() => navigate("gvp")} className={subNavButtonStyle}>Great Value Products</button>

        <div
          className="relative"
          onMouseEnter={openPrice}
          onMouseLeave={schedulePriceClose}
        >
          <button className={subNavButtonStyle}>
            <img
              src="https://merchant-cdn.storedum.com/istockphoto-1309295716-612x612.jpg"
              alt=""
              className="h-[16px] w-[16px] object-contain"
            />
            Shop by Price
            <svg className={`h-[14px] transition-transform duration-200 ${priceOpen ? "-rotate-180" : ""}`} viewBox="0 0 24 24">
              <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
            </svg>
          </button>

          <div className={`absolute left-1/2 -translate-x-1/2 top-full pt-3 z-[1100] ${priceOpen ? "block" : "hidden"}`}>
            <div className="w-[230px] max-w-[calc(100vw-20px)] bg-white rounded-[14px] shadow-[0_12px_34px_rgba(0,0,0,0.16)] p-2">
              {pricePresets.map((item, idx) => {
                const icons = [
                  "M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z",
                  "M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z",
                  "M20 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM10 4h4v2h-4V4z",
                ];
                const itemIcon = icons[idx % icons.length];
                return (
                <button
                  key={item.label}
                  onClick={() => { setPriceOpen(false); navigate("category", { priceMax: item.max }); }}
                  className="w-full flex items-center gap-[12px] px-[14px] py-[12px] rounded-[10px] text-[15px] font-semibold text-[var(--text-primary)] cursor-pointer hover:bg-[rgba(var(--border-color-1-rgb),0.6)] transition-colors"
                >
                  <svg className="h-[18px] w-[18px] shrink-0 text-[var(--text-primary)]" fill="currentColor" viewBox="0 0 24 24">
                    <path d={itemIcon} />
                  </svg>
                  {item.label}
                </button>
              );
              })}
            </div>
          </div>
        </div>

        <button onClick={() => navigate("product", { id: "ev-001" })} className={subNavButtonStyle}>Events</button>
        <button onClick={() => (user ? navigate("wishlist") : openModal("auth"))} className={subNavButtonStyle}>Wishlist</button>
        <button onClick={() => navigate("about")} className={subNavButtonStyle}>About Us</button>
        <button onClick={() => navigate("contact")} className={subNavButtonStyle}>Contact Us</button>
      </div>

      {/* Mobile drawer */}
      {mobileOpen && (
        <>
          <div
            className="lg:hidden fixed inset-0 z-[1099] bg-black/50"
            onClick={() => setMobileOpen(false)}
          />
          <aside className="lg:hidden fixed top-0 left-0 z-[1100] h-full w-[280px] max-w-[85vw] bg-white shadow-2xl flex flex-col">
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200">
              <span className="text-lg font-bold text-brand-ink">Menu</span>
              <button
                onClick={() => setMobileOpen(false)}
                className="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-ink"
                aria-label="Close menu"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M6 6l12 12M6 18L18 6" />
                </svg>
              </button>
            </div>
            <nav className="flex-1 overflow-y-auto py-2">
              {[
                { label: "Category", fn: () => navigate("category") },
                { label: "Offer Zone", fn: () => navigate("offers") },
                { label: "Combos", fn: () => navigate("combos") },
                { label: "Great Value Products", fn: () => navigate("gvp") },
              ].map((it) => (
                <button
                  key={it.label}
                  onClick={goAndClose(it.fn)}
                  className="w-full text-left px-5 py-3 text-sm font-semibold text-brand-ink hover:bg-gray-50"
                >
                  {it.label}
                </button>
              ))}

              <div className="border-t border-gray-100 my-1" />

              <button
                onClick={() => setMobilePriceOpen((v) => !v)}
                className="w-full flex items-center justify-between px-5 py-3 text-sm font-semibold text-brand-ink hover:bg-gray-50"
              >
                Shop by Price
                <svg className={`h-[14px] transition-transform ${mobilePriceOpen ? "-rotate-180" : ""}`} viewBox="0 0 24 24" fill="currentColor">
                  <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
                </svg>
              </button>
              {mobilePriceOpen && (
                <div className="bg-gray-50">
                  {pricePresets.map((item) => (
                    <button
                      key={item.label}
                      onClick={goAndClose(() => navigate("category", { priceMax: item.max }))}
                      className="w-full text-left px-8 py-2.5 text-sm text-brand-ink hover:bg-gray-100"
                    >
                      {item.label}
                    </button>
                  ))}
                </div>
              )}

              <div className="border-t border-gray-100 my-1" />

              {[
                { label: "Events", fn: () => navigate("product", { id: "ev-001" }) },
                { label: "Wishlist", fn: () => (user ? navigate("wishlist") : openModal("auth")) },
                { label: "About Us", fn: () => navigate("about") },
                { label: "Contact Us", fn: () => navigate("contact") },
              ].map((it) => (
                <button
                  key={it.label}
                  onClick={goAndClose(it.fn)}
                  className="w-full text-left px-5 py-3 text-sm font-semibold text-brand-ink hover:bg-gray-50"
                >
                  {it.label}
                </button>
              ))}

              {user && (
                <>
                  <div className="border-t border-gray-100 my-1" />
                  <button
                    onClick={goAndClose(() => logout?.())}
                    className="w-full text-left px-5 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                  >
                    Logout
                  </button>
                </>
              )}
            </nav>
          </aside>
        </>
      )}

    </div>
  );
}
