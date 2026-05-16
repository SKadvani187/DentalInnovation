import ProductCard from "./ProductCard";

export default function ProductSection({ title, eyebrow, products, accent = "navy" }) {
  return (
    <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">
      <div className="flex items-end justify-between mb-4 sm:mb-6">
        <div>
          {eyebrow && (
            <p className="text-[11px] sm:text-xs uppercase tracking-[0.18em] font-semibold text-brand-orange mb-1">
              {eyebrow}
            </p>
          )}
          <h2 className={`text-lg sm:text-2xl font-bold ${accent === "orange" ? "text-brand-orange" : "text-brand-ink"}`}>
            {title}
          </h2>
        </div>
        <a href="#" className="text-xs sm:text-sm font-semibold text-brand-navy hover:text-brand-orange whitespace-nowrap">
          View All →
        </a>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 md:gap-6">
        {products.map((p) => (
          <ProductCard key={p.id} product={p} />
        ))}
      </div>
    </section>
  );
}

export function RFCauterySection(){
  return (
    <div className="px-2 sm:px-0">
      {/* Spacer matching height:100px on desktop */}
      <div className="hidden sm:flex h-[100px]"></div>

      {/* Main Container Wrapper */}
      <div className="hidden sm:flex flex-col w-full relative">
        {/* Background Overlay */}
        <div className="absolute inset-0 bg-[#007AFF] opacity-[0.15] top-0 left-0 w-full h-full pointer-events-none"></div>

        {/* Content Container */}
        <div className="homepage-container-large px-[80px] w-full">
          <div className="relative pt-[35px] flex flex-col items-start">
            
            {/* Main Product Image */}
            <img
              src="https://merchant-cdn.storedum.com/Untitled_design_6_(1).png"
              alt="RF Advance Cautery"
              className="absolute right-0 left-auto top-[-100px] w-[28%] select-none cursor-pointer"
            />

            {/* Main Product Info Panel */}
            <div className="text-left w-[58%] flex flex-col items-start min-h-[300px]">
              <label className="text-[56px] font-bold uppercase text-[#08070D] cursor-pointer select-none leading-tight">
                RF Advance Cautery
              </label>
              <label className="text-[18px] font-light text-[var(--text-primary-2)] mt-2">
                The Radio Frequency Advance Electro Cautery by Younique Dental Innovations is a high-performance surgical
                unit designed to deliver precise, smooth, and bloodless soft-tissue management in dental procedures.
                Powered by advanced high-frequency radio waves, it enables clean scalpel-like cutting with excellent
                coagulation, ensuring faster healing and superior clinical outcomes.
              </label>
              
              {/* View Details Button */}
              <div className="h-[50px] w-[200px] bg-[#007AFF] mt-[20px] rounded-[100px] flex items-center justify-center text-white font-extrabold cursor-pointer hover:bg-[#006ce0] transition-colors">
                <span className="select-none">VIEW DETAILS</span>
              </div>
            </div>

            {/* Features Grid (Replacing MuiGrid structure) */}
            <div className="mt-[30px] w-full">
              <div className="grid grid-cols-3 gap-8">
                
                {/* Feature 1: Active Handle */}
                <div className="flex flex-col items-start text-left">
                  <img
                    src="https://merchant-cdn.storedum.com/Untitled_design_9_(5).png"
                    alt="Active Handle"
                    className="w-[100px]"
                  />
                  <label className="text-[22px] font-medium text-[#08070D] mt-[10px]">
                    Active Handle
                  </label>
                  <label className="text-[16px] text-[var(--text-primary-2)] mt-[3px]">
                    A durable and ergonomically designed cautery active handle that ensures precise energy delivery and
                    comfortable control during electrosurgical procedures.
                  </label>
                </div>

                {/* Feature 2: Hand Piece Pencil */}
                <div className="flex flex-col items-start text-left">
                  <img
                    src="https://merchant-cdn.storedum.com/Untitled_design_10_(15).png"
                    alt="Hand Piece Pencil"
                    className="w-[100px]"
                  />
                  <label className="text-[22px] font-medium text-[#08070D] mt-[10px]">
                    Hand Piece Pencil
                  </label>
                  <label className="text-[16px] text-[var(--text-primary-2)] mt-[3px]">
                    A lightweight, ergonomically designed cautery hand switch pencil that provides precise, fingertip
                    control for safe and efficient electrosurgical procedures.
                  </label>
                </div>

                {/* Feature 3: Bio Polar Tweezer */}
                <div className="flex flex-col items-start text-left">
                  <img
                    src="https://merchant-cdn.storedum.com/Untitled_design_11_(11).png"
                    alt="Bio Polar Tweezer"
                    className="w-[100px]"
                  />
                  <label className="text-[22px] font-medium text-[#08070D] mt-[10px]">
                    Bio Polar Tweezer
                  </label>
                  <label className="text-[16px] text-[var(--text-primary-2)] mt-[3px]">
                    A high-precision bipolar cautery tweezer designed for controlled coagulation with minimal thermal
                    spread and maximum surgical accuracy.
                  </label>
                </div>

              </div>
            </div>

          </div>
        </div>

        {/* Bottom Navigation Control Action */}
        <div className="h-[60px] mt-[60px] mb-[40px] w-full flex items-center relative justify-center">
          {/* Background Accent Rule Line */}
          <div className="w-1/2 absolute right-0 left-auto h-[4px] bg-white"></div>
          
          {/* Outer Rounded Button Container */}
          <div className="h-full rounded-[100px] w-[16%] bg-white flex relative items-center justify-center cursor-pointer shadow-sm hover:shadow-md transition-shadow">
            
            {/* Embedded Action Icon Badge */}
            <div className="h-[45px] aspect-square absolute rounded-[100px] overflow-hidden left-[7.5px] flex items-center justify-center select-none pointer-events-none">
              <div className="h-full w-full bg-[#007AFF] opacity-[0.18] absolute"></div>
              <div className="h-[70%] w-[70%] bg-[#007AFF] rounded-[100px] flex items-center justify-center">
                <svg
                  className="w-6 h-6 text-white"
                  focusable="false"
                  aria-hidden="true"
                  viewBox="0 0 24 24"
                >
                  <path fill="currentColor" d="m10 17 5-5-5-5z"></path>
                </svg>
              </div>
            </div>

            {/* Call to Action Label Text */}
            <span className="text-[16px] uppercase font-bold text-[var(--text-primary-2)] select-none pl-[30px]">
              view RF Cautery
            </span>
          </div>
        </div>

      </div>
    </div>
  );
};

const CategoryCard = ({ item }) => (
    <div className="premium-category-view-a-view_categoryView___Ouhr w-full bg-[rgba(var(--main-rgb),0.1)] relative overflow-hidden cursor-pointer min-h-[280px] group rounded-sm">
      {/* MuiBox placeholder block */}
      <div className="MuiBox-root"></div>
      
      {/* Content wrapper with scaling effects */}
      <div className="relative transition-all duration-300 transform scale-100 origin-top-left select-none">
        {/* Title Container */}
        <div className="w-[300px] absolute top-5 left-0 px-5">
          <p className="premium-category-view-a-view_title__JdJ5k text-[16px] font-semibold overflow-hidden text-ellipsis line-clamp-1 transition-all duration-300 text-[var(--text-primary)]">
            {item.title}
          </p>
        </div>
        
        {/* Subtitle / Description Container */}
        <div className="premium-category-view-a-view_subTitleContainer__4ZH0l w-[60%] absolute top-[60px] left-0 px-5">
          <p className="premium-category-view-a-view_subTitle__WnU7y text-[14px] overflow-hidden text-ellipsis line-clamp-6 transition-all duration-300 text-[var(--text-primary)] opacity-90 leading-relaxed">
            {item.description}
          </p>
        </div>
      </div>

      {/* Product Image */}
      <img 
        src={item.imgSrc} 
        alt={item.title} 
        className="w-[45%] aspect-square object-cover absolute bottom-0 right-0 transition-all duration-300 transform scale-100 origin-bottom center select-none group-hover:scale-105"
      />

      {/* Decorative Bottom Circle Elements */}
      <div className="absolute bottom-5 left-5 h-10 w-10 rounded-full flex items-center justify-center overflow-hidden bg-white"></div>
      <div className="absolute bottom-5 left-5 h-10 w-10 rounded-full flex items-center justify-center overflow-hidden bg-[rgba(var(--main-rgb),0.1)]"></div>
      
      {/* Interactive Border Circle with SVG Arrow */}
      <div className="absolute bottom-5 left-5 h-10 w-10 rounded-full border-2 border-solid border-[var(--main)] transition-all duration-300 flex items-center justify-center overflow-hidden select-none group-hover:bg-[var(--main)]">
        <svg 
          className="absolute w-5 h-5 text-[var(--text-primary)] transition-colors duration-300" 
          focusable="false" 
          aria-hidden="true" 
          viewBox="0 0 24 24"
        >
          <path fill="currentColor" d="M16.01 11H4v2h12.01v3L20 12l-3.99-4z"></path>
        </svg>
      </div>
    </div>
  );
export function PremiumCategories({products}){
  // Reusable Card Component mapping styles exactly
  return (
    <div className="px-2 sm:px-0">
      <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">
        
        {/* DESKTOP VIEW: Displays as a clean 3-column grid structure */}
        <div className="hidden sm:grid grid-cols-3 gap-6 w-full">
          {products.map((item, index) => (
            <div key={index} className="w-full">
              <CategoryCard item={item} />
            </div>
          ))}
        </div>

        {/* MOBILE VIEW: Displays as a smooth horizontally scrollable swipe row */}
        <div className="flex sm:hidden overflow-x-auto gap-[10px] w-full scrollbar-none pb-2">
          {products.map((item, index) => (
            <div key={index} className="w-[80vw] shrink-0 overflow-hidden">
              <CategoryCard item={item} />
            </div>
          ))}
        </div>

      </section>
    </div>
  );
}

export function HomeBanner(){
  const mobileBanners = [
    "https://merchant-cdn.storedum.com/new_website_banner_mobile_2_(1).png",
    "https://merchant-cdn.storedum.com/new_banner_2.webp",
    "https://merchant-cdn.storedum.com/new_website_banner_mobile_1_1.webp"
  ];
  return(
    <div className="px-2 sm:px-0">
      <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">
        
        {/* DESKTOP VIEW: Split Layout (Visible on sm screens and up) */}
        <div className="hidden sm:flex flex-row gap-5 w-full px-3">
          
          {/* Left Block: Massive Featured Banner */}
          <div className="aspect-[8/5] w-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group">
            <img 
              src="https://merchant-cdn.storedum.com/new_website_banner_mobile_2.png" 
              alt="Featured Promotion" 
              loading="lazy"
              className="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-102"
            />
          </div>

          {/* Right Block: Stacked Column Banners */}
          <div className="aspect-[8/5] w-[calc(50%-10px)] flex flex-col gap-5">
            
            {/* Top Right Banner */}
            <div className="w-full h-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group">
              <img 
                src="https://merchant-cdn.storedum.com/new_website_banner_desktop_(2).webp" 
                alt="Secondary Offer" 
                loading="lazy"
                className="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-102"
              />
            </div>
            
            {/* Bottom Right Banner */}
            <div className="w-full h-[calc(50%-10px)] relative overflow-hidden rounded-[15px] cursor-pointer group">
              <img 
                src="https://merchant-cdn.storedum.com/new_website_banner_desktop.png" 
                alt="Tertiary Offer" 
                loading="lazy"
                className="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-102"
              />
            </div>

          </div>
        </div>

        {/* MOBILE VIEW: Horizontal Swipe Track (Visible on extra-small screens only) */}
        <div className="flex sm:hidden flex-row gap-[10px] overflow-x-auto whitespace-nowrap thin-scroller scrollbar-none pb-1">
          {mobileBanners.map((src, index) => (
            <div 
              key={index} 
              className="shrink-0 w-[80vw] aspect-[8/5] relative rounded-[10px] overflow-hidden cursor-pointer"
            >
              <img 
                src={src} 
                alt={`Mobile Slide ${index + 1}`} 
                loading="lazy"
                className="absolute inset-0 w-full h-full object-cover"
              />
            </div>
          ))}
        </div>

      </section>
    </div>
  );
}

export function RFCauterySection2() {
  return (
    <div className="px-2 sm:px-0">
      <section className="max-w-[1400px] mx-auto px-3 sm:px-6 py-6 sm:py-10">
        
        {/* Unified Responsive Container */}
        <div className="flex flex-col sm:flex-row gap-6 sm:gap-8 items-center w-full">
          
          {/* TEXT BLOCK: Displays on the left for desktop, moves below video on mobile */}
          <div className="w-full sm:w-1/2 order-2 sm:order-1 text-[var(--text-primary)]">
            <h4 className="text-[20px] sm:text-[24px] font-bold leading-snug tracking-tight mb-3">
              Radio Frequency Advance Cautery — Precise, Safe, and Ideal for Modern Dental Procedures
            </h4>
            <p className="text-[14px] sm:text-[16px] opacity-90 leading-relaxed">
              A high-performance radio frequency cautery unit designed for precise, 
              bloodless soft-tissue procedures in dentistry, delivering scalpel-like 
              cutting with superior coagulation control.
            </p>
          </div>

          {/* VIDEO BLOCK: Displays on the right for desktop, floats to the top on mobile */}
          <div className="w-full sm:w-1/2 order-1 sm:order-2">
            <div className="w-full aspect-video rounded-xl overflow-hidden shadow-sm border border-neutral-100 bg-black">
              <iframe 
                src="https://www.youtube.com/embed/9RJuESYo6jw"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
                className="w-full h-full object-cover"
                title="Radio Frequency Advance Cautery Demo"
              />
            </div>
          </div>

        </div>

      </section>
    </div>
  );
}