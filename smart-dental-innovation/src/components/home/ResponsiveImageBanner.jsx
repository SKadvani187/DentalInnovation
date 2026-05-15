import React from 'react';

export default function ResponsiveImageBanner() {
  return (
    /* Increased horizontal padding: px-4 (16px) on mobile, sm:px-8 (32px) on tablet, and md:px-12 (48px) on desktop */
    <div className="w-full px-4 sm:px-8 md:px-12 py-4">
      <div className="flex flex-col gap-3 max-w-[1400px] mx-auto">
        
        {/* Desktop View Banner */}
        <div className="hidden sm:flex rounded-[10px] w-full overflow-hidden bg-cover bg-center bg-no-repeat shadow-sm">
          <div className="overflow-hidden rounded-[10px] w-full">
            <img 
              alt="Website Promo Desktop Banner" 
              fetchPriority="high" 
              width="1200"
              height="600" 
              decoding="async" 
              className="text-transparent w-full h-auto block"
              src="https://merchant-cdn.storedum.com/website_patti_slider_desktop_(2).png" 
            />
          </div>
        </div>

        {/* Mobile View Banner */}
        <div className="block sm:hidden rounded-[10px] w-full overflow-hidden bg-cover bg-center bg-no-repeat shadow-sm">
          <div className="overflow-hidden rounded-[10px] w-full">
            <img 
              alt="Quick Service Support Mobile Banner" 
              fetchPriority="high" 
              width="600"
              height="800" 
              decoding="async" 
              className="text-transparent w-full h-auto block"
              src="https://merchant-cdn.storedum.com/Quick_Service_Support.png" 
            />
          </div>
        </div>

      </div>
    </div>
  );
}