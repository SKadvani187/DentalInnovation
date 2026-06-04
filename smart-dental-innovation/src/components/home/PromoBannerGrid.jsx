import React from 'react';
import { useUI } from '../../context/UIContext';
import { useSettings } from '../../context/SettingsContext';

export default function PromoBannerGrid(props = {}) {
  const { navigate } = useUI();
  const { banners } = useSettings();
  const promo = banners?.promo || {};
  const leftId = props.leftId ?? promo.leftId ?? "i-001";
  const topRightId = props.topRightId ?? promo.topRightId ?? "i-002";
  const bottomRightId = props.bottomRightId ?? promo.bottomRightId ?? "i-003";
  const go = (id) => navigate("product", { id });

  return (
    <div className="w-full px-4 sm:px-8 md:px-12 py-4">
      <div className="max-w-[1400px] mx-auto">

        {/* DESKTOP VIEW GRID LAYOUT */}
        <div className="hidden sm:flex flex-row gap-5 w-full">

          {/* Left Side: One Large Main Banner */}
          <div
            onClick={() => go(leftId)}
            className="aspect-[8/5] w-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group shadow-sm"
          >
            <img
              alt="Featured Promo Left"
              loading="lazy"
              decoding="async"
              className="absolute inset-0 h-full w-full object-cover text-transparent transition-transform duration-300 group-hover:scale-[1.02]"
              src={promo.leftImg || "https://merchant-cdn.storedum.com/new_website_banner_mobile_2.png"}
            />
          </div>

          {/* Right Side: Two Stacked Half-Height Banners */}
          <div className="aspect-[8/5] w-[calc(50%-10px)] flex flex-col gap-5">

            {/* Top Right Banner */}
            <div
              onClick={() => go(topRightId)}
              className="w-full h-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group shadow-sm"
            >
              <img
                alt="Promo Top Right"
                loading="lazy"
                decoding="async"
                className="absolute inset-0 h-full w-full object-cover text-transparent transition-transform duration-300 group-hover:scale-[1.02]"
                src={promo.topRightImg || "https://merchant-cdn.storedum.com/new_website_banner_desktop_(2).webp"}
              />
            </div>

            {/* Bottom Right Banner */}
            <div
              onClick={() => go(bottomRightId)}
              className="w-full h-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group shadow-sm"
            >
              <img
                alt="Promo Bottom Right"
                loading="lazy"
                decoding="async"
                className="absolute inset-0 h-full w-full object-cover text-transparent transition-transform duration-300 group-hover:scale-[1.02]"
                src={promo.bottomRightImg || "https://merchant-cdn.storedum.com/new_website_banner_desktop.png"}
              />
            </div>

          </div>
        </div>

        {/* MOBILE VIEW SCROLLABLE CAROUSEL TRACK */}
        <div className="flex sm:hidden flex-row gap-3 overflow-x-auto whitespace-nowrap no-scrollbar scroll-smooth -mx-4 px-4 py-1">

          {/* Mobile Card 1 */}
          <div
            onClick={() => go(leftId)}
            style={{ backgroundImage: `url('https://merchant-cdn.storedum.com/new_website_banner_mobile_2_220px_(1).png')` }}
            className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer bg-cover bg-center bg-no-repeat shadow-sm"
          >
            <img
              alt="Mobile Banner 1"
              loading="lazy"
              decoding="async"
              className="absolute inset-0 h-full w-full object-cover text-transparent"
              src={promo.leftImgM || "https://merchant-cdn.storedum.com/new_website_banner_mobile_2_(1).png"}
            />
          </div>

          {/* Mobile Card 2 */}
          <div
            onClick={() => go(topRightId)}
            style={{ backgroundImage: `url('https://merchant-cdn.storedum.com/new_banner_2_220px.webp')` }}
            className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer bg-cover bg-center bg-no-repeat shadow-sm"
          >
            <img
              alt="Mobile Banner 2"
              loading="lazy"
              decoding="async"
              className="absolute inset-0 h-full w-full object-cover text-transparent"
              src={promo.topRightImgM || "https://merchant-cdn.storedum.com/new_banner_2.webp"}
            />
          </div>

          {/* Mobile Card 3 */}
          <div
            onClick={() => go(bottomRightId)}
            style={{ backgroundImage: `url('https://merchant-cdn.storedum.com/new_website_banner_mobile_1_1_220px.webp')` }}
            className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer bg-cover bg-center bg-no-repeat shadow-sm"
          >
            <img
              alt="Mobile Banner 3"
              loading="lazy"
              decoding="async"
              className="absolute inset-0 h-full w-full object-cover text-transparent"
              src={promo.bottomRightImgM || "https://merchant-cdn.storedum.com/new_website_banner_mobile_1_1.webp"}
            />
          </div>

        </div>

      </div>
    </div>
  );
}
