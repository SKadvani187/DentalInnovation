import { CartProvider } from "./context/CartContext";
import { WishlistProvider } from "./context/WishlistContext";
import { AuthProvider } from "./context/AuthContext";
import { UIProvider } from "./context/UIContext";

import Navbar from "./components/layout/Navbar";
import Footer from "./components/layout/Footer";
import HeroCarousel from "./components/home/HeroCarousel";
import CategoryGrid from "./components/home/CategoryGrid";
import ProductSection from "./components/home/ProductSection";
import { RFCauterySection, PremiumCategories, HomeBanner,RFCauterySection2 } from "./components/home/ProductSection";
import FeaturedCards from "./components/home/FeaturedCards";
import Testimonials from "./components/home/Testimonials";

import ProductDetailModal from "./components/modals/ProductDetailModal";
import CartDrawer from "./components/modals/CartDrawer";
import WishlistDrawer from "./components/modals/WishlistDrawer";
import CheckoutModal from "./components/modals/CheckoutModal";
import AuthModal from "./components/modals/AuthModal";

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
            {/* <Navbar /> */}
            <NavigationHeader/>
            <main>
              <HeroCarousel />
              <CategoryGrid />
              <ResponsiveImageBanner/>
              <ProductSection eyebrow="Top Picks" title="Bestsellers" products={bestsellers} />
              <PromoBannerGrid/>
              <ProductSection eyebrow="Fresh In" title="New Arrivals" products={newArrivals} accent="orange" />
              {/* <FeaturedCards /> */}
              <RFCauterySection />
              <ProductSection title="Implantology" products={implantology} />
              <PremiumCategories products={premiumCategories} />
              <ProductSection title="Handpiece" products={handpieces} />
              <HomeBanner/>
              <ProductSection title="Matrix System" products={matrixSystem} />
              <ProductSection title="Endodontics" products={endodontics} />
              <RFCauterySection2 />
              {/* <Testimonials /> */}
              <ReviewsSection />
              <ProsthodonticsCarousel/>
            </main>
            <Footer />

            <ProductDetailModal />
            <CartDrawer />
            <WishlistDrawer />
            <CheckoutModal />
            <AuthModal />
          </UIProvider>
        </WishlistProvider>
      </CartProvider>
    </AuthProvider>
  );
}
