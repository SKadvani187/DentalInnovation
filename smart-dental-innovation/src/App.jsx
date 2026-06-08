import { CartProvider } from "./context/CartContext";
import { WishlistProvider } from "./context/WishlistContext";
import { AuthProvider } from "./context/AuthContext";
import { UIProvider, useUI } from "./context/UIContext";
import { SettingsProvider } from "./context/SettingsContext";
import { useEffect } from "react";

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
import CheckoutModal from "./components/modals/CheckoutModal";
import AuthModal from "./components/modals/AuthModal";
import SearchModal from "./components/modals/SearchModal";
import BulkQuoteModal from "./components/modals/BulkQuoteModal";
import CategoryPage from "./components/pages/CategoryPage";
import ShopByPricePage from "./components/pages/ShopByPricePage";
import GreatValuePage from "./components/pages/GreatValuePage";
import CombosPage from "./components/pages/CombosPage";
import EventsPage from "./components/pages/EventsPage";
import AboutPage from "./components/pages/AboutPage";
import ContactPage from "./components/pages/ContactPage";
import ProductDetailPage from "./components/pages/ProductDetailPage";
import QnaPage from "./components/pages/QnaPage";
import AccountPage from "./components/pages/AccountPage";
import OrdersPage from "./components/pages/OrdersPage";
import WishlistPage from "./components/pages/WishlistPage";
import AddressPage from "./components/pages/AddressPage";
import OfferZonePage from "./components/pages/OfferZonePage";
import PolicyPage from "./components/pages/PolicyPage";
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
    <SettingsProvider>
      <AuthProvider>
        <CartProvider>
          <WishlistProvider>
            <UIProvider>
              <Shell />
            </UIProvider>
          </WishlistProvider>
        </CartProvider>
      </AuthProvider>
    </SettingsProvider>
  );
}

function Shell() {
  const { view } = useUI();
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
        {view.name === "category" ? (
          <CategoryPage />
        ) : view.name === "gvp" ? (
          <GreatValuePage />
        ) : view.name === "shopByPrice" ? (
          <ShopByPricePage />
        ) : view.name === "combos" ? (
          <CombosPage />
        ) : view.name === "events" ? (
          <EventsPage />
        ) : view.name === "about" ? (
          <AboutPage />
        ) : view.name === "contact" ? (
          <ContactPage />
        ) : view.name === "product" ? (
          <ProductDetailPage />
        ) : view.name === "qna" ? (
          <QnaPage />
        ) : view.name === "account" ? (
          <AccountPage />
        ) : view.name === "orders" ? (
          <OrdersPage />
        ) : view.name === "wishlist" ? (
          <WishlistPage />
        ) : view.name === "address" ? (
          <AddressPage />
        ) : view.name === "offers" ? (
          <OfferZonePage />
        ) : view.name === "policy" ? (
          <PolicyPage />
        ) : (
          <>{blocks.map(renderBlock)}</>
        )}
      </main>
      <Footer />

      <ProductDetailModal />
      <CartDrawer />
      <WishlistDrawer />
      <CheckoutModal />
      <AuthModal />
      <SearchModal />
      <BulkQuoteModal />
      <WhatsAppFab />
      <ToastHost />
    </>
  );
}
