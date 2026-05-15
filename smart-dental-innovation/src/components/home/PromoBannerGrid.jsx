import React from 'react';

export default function PromoBannerGrid() {
  return (
    /* Outer wrapper with responsive horizontal spacing matching your layout preference */
    <div className="w-full px-4 sm:px-8 md:px-12 py-4">
      <div className="max-w-[1400px] mx-auto">
        
        {/* DESKTOP VIEW GRID LAYOUT (visible on sm screens and larger) */}
        <div className="hidden sm:flex flex-row gap-5 w-full">
          
          {/* Left Side: One Large Main Banner */}
          <div className="aspect-[8/5] w-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group shadow-sm">
            <img 
              alt="Featured Promo Left" 
              loading="lazy" 
              decoding="async" 
              className="absolute inset-0 h-full w-full object-cover text-transparent transition-transform duration-300 group-hover:scale-[1.02]"
              src="https://merchant-cdn.storedum.com/new_website_banner_mobile_2.png" 
            />
          </div>

          {/* Right Side: Two Stacked Half-Height Banners */}
          <div className="aspect-[8/5] w-[calc(50%-10px)] flex flex-col gap-5">
            
            {/* Top Right Banner */}
            <div className="w-full h-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group shadow-sm">
              <img 
                alt="Promo Top Right" 
                loading="lazy" 
                decoding="async" 
                className="absolute inset-0 h-full w-full object-cover text-transparent transition-transform duration-300 group-hover:scale-[1.02]"
                src="https://merchant-cdn.storedum.com/new_website_banner_desktop_(2).webp" 
              />
            </div>

            {/* Bottom Right Banner */}
            <div className="w-full h-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group shadow-sm">
              <img 
                alt="Promo Bottom Right" 
                loading="lazy" 
                decoding="async" 
                className="absolute inset-0 h-full w-full object-cover text-transparent transition-transform duration-300 group-hover:scale-[1.02]"
                src="https://merchant-cdn.storedum.com/new_website_banner_desktop.png" 
              />
            </div>

          </div>
        </div>

        {/* MOBILE VIEW SCROLLABLE CAROUSEL TRACK (visible only on mobile) */}
        <div className="flex sm:hidden flex-row gap-3 overflow-x-auto whitespace-nowrap no-scrollbar scroll-smooth -mx-4 px-4 py-1">
          
          {/* Mobile Card 1 */}
          <div 
            style={{ backgroundImage: `url('https://merchant-cdn.storedum.com/new_website_banner_mobile_2_220px_(1).png')` }}
            className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer bg-cover bg-center bg-no-repeat shadow-sm"
          >
            <img 
              alt="Mobile Banner 1" 
              loading="lazy" 
              decoding="async" 
              className="absolute inset-0 h-full w-full object-cover text-transparent"
              src="https://merchant-cdn.storedum.com/new_website_banner_mobile_2_(1).png" 
            />
          </div>

          {/* Mobile Card 2 */}
          <div 
            style={{ backgroundImage: `url('https://merchant-cdn.storedum.com/new_banner_2_220px.webp')` }}
            className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer bg-cover bg-center bg-no-repeat shadow-sm"
          >
            <img 
              alt="Mobile Banner 2" 
              loading="lazy" 
              decoding="async" 
              className="absolute inset-0 h-full w-full object-cover text-transparent"
              src="https://merchant-cdn.storedum.com/new_banner_2.webp" 
            />
          </div>

          {/* Mobile Card 3 */}
          <div 
            style={{ backgroundImage: `url('https://merchant-cdn.storedum.com/new_website_banner_mobile_1_1_220px.webp')` }}
            className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer bg-cover bg-center bg-no-repeat shadow-sm"
          >
            <img 
              alt="Mobile Banner 3" 
              loading="lazy" 
              decoding="async" 
              className="absolute inset-0 h-full w-full object-cover text-transparent"
              src="https://merchant-cdn.storedum.com/new_website_banner_mobile_1_1.webp" 
            />
          </div>

        </div>

      </div>
    </div>
  );
}