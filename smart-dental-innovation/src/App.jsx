import { CartProvider } from "./context/CartContext";
import { WishlistProvider } from "./context/WishlistContext";
import { AuthProvider } from "./context/AuthContext";
import { UIProvider } from "./context/UIContext";
import { SettingsProvider } from "./context/SettingsContext";
import { useEffect, lazy, Suspense } from "react";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import ScrollToTop from "./components/ScrollToTop";
import ErrorBoundary from "./components/ErrorBoundary";

import Navbar from "./components/layout/Navbar";
import Footer from "./components/layout/Footer";
import HeroCarousel from "./components/home/HeroCarousel";
import CategoryGrid from "./components/home/CategoryGrid";
import ProductSection from "./components/home/ProductSection";
import { RFCauterySection, PremiumCategories, HomeBanner } from "./components/home/ProductSection";
import FeaturedCards from "./components/home/FeaturedCards";
import Testimonials from "./components/home/Testimonials";

import ProductDetailModal from "./components/modals/ProductDetailModal";
import CartDrawer from "./components/modals/CartDrawer";
import WishlistDrawer from "./components/modals/WishlistDrawer";
import CheckoutDrawer from "./components/modals/CheckoutDrawer";
import AuthModal from "./components/modals/AuthModal";
import SearchModal from "./components/modals/SearchModal";
import BulkQuoteModal from "./components/modals/BulkQuoteModal";
// Route pages are code-split (React.lazy) so a first visit doesn't ship every page's JS in
// one bundle — each route's chunk loads on demand. See <Suspense> around <Routes> below.
const CategoryPage = lazy(() => import("./components/pages/CategoryPage"));
const ShopByPricePage = lazy(() => import("./components/pages/ShopByPricePage"));
const GreatValuePage = lazy(() => import("./components/pages/GreatValuePage"));
const CombosPage = lazy(() => import("./components/pages/CombosPage"));
const EventsPage = lazy(() => import("./components/pages/EventsPage"));
const AboutPage = lazy(() => import("./components/pages/AboutPage"));
const ContactPage = lazy(() => import("./components/pages/ContactPage"));
const ProductDetailPage = lazy(() => import("./components/pages/ProductDetailPage"));
const QnaPage = lazy(() => import("./components/pages/QnaPage"));
const AccountPage = lazy(() => import("./components/pages/AccountPage"));
const OrdersPage = lazy(() => import("./components/pages/OrdersPage"));
const OrderDetailPage = lazy(() => import("./components/pages/OrderDetailPage"));
const WishlistPage = lazy(() => import("./components/pages/WishlistPage"));
const AddressPage = lazy(() => import("./components/pages/AddressPage"));
const OfferZonePage = lazy(() => import("./components/pages/OfferZonePage"));
const PolicyPage = lazy(() => import("./components/pages/PolicyPage"));
import ToastHost from "./components/ui/ToastHost";
import WhatsAppFab from "./components/layout/WhatsAppFab";

import { useHomeData } from "./hooks/useHomeData";
import { useSettings } from "./context/SettingsContext";
import NavigationHeader from "./components/layout/Navbar copy";
import ResponsiveImageBanner from "./components/home/ResponsiveImageBanner";
import PromoBannerGrid from "./components/home/PromoBannerGrid";
import ReviewsSection from "./components/home/ReviewSection";
import ProsthodonticsCarousel from "./components/home/ProsthodonticsCarousel";

export default function App() {
  return (
    <BrowserRouter>
      <ScrollToTop />
      <SettingsProvider>
        <AuthProvider>
          <CartProvider>
            <WishlistProvider>
              <UIProvider>
                <ErrorBoundary>
                  <Shell />
                </ErrorBoundary>
              </UIProvider>
            </WishlistProvider>
          </CartProvider>
        </AuthProvider>
      </SettingsProvider>
    </BrowserRouter>
  );
}

function Shell() {
  const { sections, categories, testimonials } = useHomeData();
  const { premiumCategories = [], homeSections = [] } = useSettings();

  // Global: pressing Esc must NOT close any popup/drawer/modal anywhere in the storefront.
  // A single capture-phase listener swallows Escape before each modal's own keydown handler runs,
  // so we don't have to touch every modal individually.
  useEffect(() => {
    const blockEsc = (e) => {
      if (e.key === "Escape" || e.key === "Esc") e.stopImmediatePropagation();
    };
    window.addEventListener("keydown", blockEsc, true); // capture phase = runs first
    return () => window.removeEventListener("keydown", blockEsc, true);
  }, []);

  // Render a single home block by its config type.
  const renderBlock = (s) => {
    switch (s.type) {
      case "hero": return <HeroCarousel key={s.key} />;
      case "categoryGrid": return <CategoryGrid key={s.key} items={categories} />;
      case "trustBadges": return <ResponsiveImageBanner key={s.key} />;
      case "promoGrid": return <PromoBannerGrid key={s.key} />;
      case "rfCautery": return <RFCauterySection key={s.key} />;
      case "premium": return <PremiumCategories key={s.key} products={premiumCategories} />;
      case "homeBanner": return <HomeBanner key={s.key} />;
      case "reviews": return <ReviewsSection key={s.key} />;
      case "prosthodontics": return <ProsthodonticsCarousel key={s.key} />;
      case "productSection":
        return (
          <ProductSection
            key={s.key}
            eyebrow={s.eyebrow}
            title={s.label}
            products={(sections || {})[s.key] || []}
            accent={s.accent}
          />
        );
      default: return null;
    }
  };

  const blocks = (homeSections || []).filter((s) => s.enabled !== false);

  return (
    <>
      <NavigationHeader />
      <main>
        <Suspense fallback={<div className="min-h-[60vh] flex items-center justify-center text-brand-muted text-sm">Loading…</div>}>
        <Routes>
          <Route path="/" element={<>{blocks.map(renderBlock)}</>} />
          <Route path="/category/:category?" element={<CategoryPage />} />
          <Route path="/shop-by-price" element={<ShopByPricePage />} />
          <Route path="/great-value" element={<GreatValuePage />} />
          <Route path="/combos" element={<CombosPage />} />
          <Route path="/events/:id?" element={<EventsPage />} />
          <Route path="/product/:id" element={<ProductDetailPage />} />
          <Route path="/qna/:id?" element={<QnaPage />} />
          <Route path="/about" element={<AboutPage />} />
          <Route path="/contact" element={<ContactPage />} />
          <Route path="/account" element={<AccountPage />} />
          <Route path="/orders" element={<OrdersPage />} />
          <Route path="/order/:id" element={<OrderDetailPage />} />
          <Route path="/wishlist" element={<WishlistPage />} />
          <Route path="/address" element={<AddressPage />} />
          <Route path="/offers" element={<OfferZonePage />} />
          <Route path="/policy/:type?" element={<PolicyPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        </Suspense>
      </main>
      <Footer />

      <ProductDetailModal />
      <CartDrawer />
      <WishlistDrawer />
      <CheckoutDrawer />
      <AuthModal />
      <SearchModal />
      <BulkQuoteModal />
      <WhatsAppFab />
      <ToastHost />
    </>
  );
}
