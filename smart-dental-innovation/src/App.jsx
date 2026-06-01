import { CartProvider } from "./context/CartContext";
import { WishlistProvider } from "./context/WishlistContext";
import { AuthProvider } from "./context/AuthContext";
import { UIProvider, useUI } from "./context/UIContext";

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

import {
  bestsellers,
  newArrivals,
  implantology,
  handpieces,
  matrixSystem,
  endodontics,
  premiumCategories
} from "./data/products";
import NavigationHeader from "./components/layout/Navbar copy";
import ResponsiveImageBanner from "./components/home/ResponsiveImageBanner";
import PromoBannerGrid from "./components/home/PromoBannerGrid";
import ReviewsSection from "./components/home/ReviewSection";
import ProsthodonticsCarousel from "./components/home/ProsthodonticsCarousel";

export default function App() {
  return (
    <AuthProvider>
      <CartProvider>
        <WishlistProvider>
          <UIProvider>
            <Shell />
          </UIProvider>
        </WishlistProvider>
      </CartProvider>
    </AuthProvider>
  );
}

function Shell() {
  const { view } = useUI();
  return (
    <>
      <NavigationHeader />
      <main>
        {view.name === "category" || view.name === "gvp" ? (
          <CategoryPage />
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
          <>
            <HeroCarousel />
            <CategoryGrid />
            <ResponsiveImageBanner/>
            <ProductSection eyebrow="Top Picks" title="Bestsellers" products={bestsellers} />
            <PromoBannerGrid/>
            <ProductSection eyebrow="Fresh In" title="New Arrivals" products={newArrivals} accent="orange" />
            <RFCauterySection />
            <ProductSection title="Implantology" products={implantology} />
            <PremiumCategories products={premiumCategories} />
            <ProductSection title="Handpiece" products={handpieces} />
            <HomeBanner/>
            <ProductSection title="Matrix System" products={matrixSystem} />
            <ProductSection title="Endodontics" products={endodontics} />
            <ReviewsSection />
            <ProsthodonticsCarousel/>
          </>
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
