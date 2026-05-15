import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";

const navLinks = ["Category", "Combos", "Great Value Products", "Shop by Price", "Events", "About Us", "Contact Us"];

export default function Navbar() {
  const { itemCount } = useCart();
  const { count: wishCount } = useWishlist();
  const { user, logout } = useAuth();
  const { openModal } = useUI();

  return (
    <header className="sticky top-0 z-[900] bg-white/80 backdrop-blur-xl border-b border-gray-200">
      {/* Top row */}
      <div className="flex items-center h-16 px-3 sm:px-6 gap-2 sm:gap-4">
        <a href="#" className="flex items-center gap-2 shrink-0">
          <div className="w-9 h-9 rounded-lg bg-brand-navy text-white flex items-center justify-center font-bold">
            SD
          </div>
          <div className="hidden sm:flex flex-col leading-tight">
            <span className="text-sm font-bold text-brand-navy">Smart Dental</span>
            <span className="text-[10px] uppercase tracking-wider text-brand-muted">Innovations</span>
          </div>
        </a>

        {/* Search bar (desktop) */}
        <div className="flex-1 max-w-2xl hidden md:block">
          <button
            type="button"
            className="w-full h-10 flex items-center gap-3 px-4 border border-gray-300 rounded-lg text-left text-brand-muted hover:border-brand-navy transition"
          >
            <svg width="18" height="18" viewBox="0 0 15 15" fill="none" stroke="currentColor" strokeWidth="1.2">
              <path d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z" />
            </svg>
            <span className="text-sm">Search over 1,000 Dental Products</span>
          </button>
        </div>

        <div className="flex-1 md:hidden" />

        {/* Account */}
        {user ? (
          <div className="hidden sm:flex items-center gap-2">
            <span className="text-sm font-medium text-brand-ink">Hi, {user.name.split(" ")[0]}</span>
            <button onClick={logout} className="text-xs text-brand-muted hover:text-brand-navy">Logout</button>
          </div>
        ) : (
          <button
            onClick={() => openModal("auth")}
            className="hidden sm:flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100"
          >
            <span className="w-7 h-7 rounded-full bg-brand-navy flex items-center justify-center">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
              </svg>
            </span>
            <span className="text-sm font-medium">Sign In</span>
          </button>
        )}

        {/* Wishlist */}
        <button
          onClick={() => openModal("wishlist")}
          aria-label="Wishlist"
          className="relative p-2 rounded-lg hover:bg-gray-100"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
            <path fillRule="evenodd" d="M15.99 3.75c-1.311-.018-2.54.427-3.366 1.667a.75.75 0 01-1.248 0C10.554 4.184 9.303 3.75 8 3.75 5.373 3.75 2.75 5.955 2.75 9c0 3.178 2.055 5.99 4.375 8.065a20.921 20.921 0 003.27 2.397c.474.278.881.486 1.19.622.158.07.296.122.415.157l.05-.015c.088-.027.21-.074.365-.142.309-.136.716-.344 1.19-.622a20.92 20.92 0 003.27-2.397C19.195 14.99 21.25 12.18 21.25 9c0-3.037-2.616-5.211-5.26-5.25z" />
          </svg>
          {wishCount > 0 && (
            <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-brand-orange text-white text-[10px] font-bold flex items-center justify-center">
              {wishCount}
            </span>
          )}
        </button>

        {/* Cart */}
        <button
          onClick={() => openModal("cart")}
          aria-label="Cart"
          className="relative p-2 rounded-lg hover:bg-gray-100"
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path fillRule="evenodd" clipRule="evenodd" d="M2.24875 2.29266C1.85798 2.15527 1.42983 2.36068 1.29245 2.75145C1.15506 3.14221 1.36047 3.57036 1.75123 3.70775L2.01244 3.79958C2.68005 4.0343 3.1188 4.18985 3.44165 4.34826C3.74487 4.49704 3.87854 4.61747 3.9666 4.74634C4.05686 4.87842 4.12657 5.05984 4.1659 5.42319C4.20705 5.8034 4.20807 6.29862 4.20807 7.03856V9.7602C4.20807 11.2127 4.2217 12.2601 4.35875 13.0603C4.50508 13.9146 4.79721 14.5263 5.34344 15.1024C5.9373 15.7288 6.69011 16.0015 7.58635 16.1285C8.44458 16.2502 9.53443 16.2502 10.8801 16.2502L16.2859 16.2502C17.0275 16.2502 17.6516 16.2502 18.1566 16.1884C18.6923 16.1229 19.1809 15.9796 19.6074 15.632C20.0339 15.2844 20.2729 14.8349 20.4453 14.3234C20.6077 13.8413 20.7337 13.2301 20.8834 12.5037L21.3923 10.0344L21.4037 9.97747C21.5684 9.15259 21.7069 8.4587 21.7413 7.90058C21.7775 7.31438 21.7108 6.73637 21.3289 6.23998C21.094 5.93456 20.7636 5.76166 20.4631 5.65607C20.1567 5.54838 19.8101 5.48608 19.4604 5.44698C18.7733 5.37018 17.9386 5.37019 17.12 5.3702L5.66787 5.3702L5.65718 5.26177C5.60345 4.76539 5.48704 4.31268 5.20506 3.90003C4.92088 3.48417 4.54303 3.21784 4.1024 3.00163C3.69031 2.79943 3.16668 2.61536 2.55015 2.39862L2.24875 2.29266Z" fill="currentColor" />
            <circle cx="7.5" cy="19.5" r="1.5" fill="currentColor" />
            <circle cx="16.5" cy="19.5" r="1.5" fill="currentColor" />
          </svg>
          {itemCount > 0 && (
            <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-brand-orange text-white text-[10px] font-bold flex items-center justify-center">
              {itemCount}
            </span>
          )}
        </button>
      </div>

      {/* Mobile search */}
      <div className="md:hidden px-3 pb-3">
        <button
          type="button"
          className="w-full h-10 flex items-center gap-3 px-4 border border-gray-300 rounded-lg text-left text-brand-muted"
        >
          <svg width="16" height="16" viewBox="0 0 15 15" fill="none" stroke="currentColor" strokeWidth="1.2">
            <path d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z" />
          </svg>
          <span className="text-xs">Search over 1,000 Dental Products</span>
        </button>
      </div>

      {/* Nav links row */}
      <nav className="flex items-center gap-8 px-3 sm:px-6 py-2 overflow-x-auto no-scrollbar">
        {navLinks.map((l) => (
          <button
            key={l}
            className="text-base font-semibold whitespace-nowrap px-3 py-1.5 rounded-md hover:text-brand-navy transition"
          >
            {l}
          </button>
        ))}
      </nav>
    </header>
  );
}
