import React, { useRef } from 'react';
import { useUI } from '../../context/UIContext';
import { useCart } from '../../context/CartContext';

// FULL Dataset with all 10 products
const PRODUCTS = [
  {
    id: 1,
    productId: "i-001",
    name: "Implant S Plus",
    price: "₹41,990",
    priceNum: 41990,
    mrpNum: 53000,
    oldPrice: "₹53,000",
    discount: "↓ 21% Off",
    image: "https://merchant-cdn.storedum.com/ai_img_39_220px_(1).png"
  },
  {
    id: 2,
    productId: "i-002",
    name: "Implant S Lite Physio - Implant Physio Dispenser",
    price: "₹29,990",
    priceNum: 29990,
    mrpNum: 35000,
    oldPrice: "₹35,000",
    discount: "↓ 14% Off",
    image: "https://merchant-cdn.storedum.com/73aebf1a-a7db-4944-b5e0-6fa26ce105f9_220px_(8).png"
  },
  {
    id: 3,
    productId: "p-006",
    name: "Photography Mirror With Led Anti Fog - (Set of 4 Mirror) + Contraster Steel (Pack of 3)",
    price: "₹3,790",
    priceNum: 3790,
    mrpNum: 4200,
    oldPrice: "₹4,200",
    discount: "↓ 10% Off",
    image: "https://merchant-cdn.storedum.com/plain_image_21_220px_(1).png"
  },
  {
    id: 4,
    productId: "i-003",
    name: "Motor Hex Driver - Electric Implant Motor Driver",
    price: "₹400",
    priceNum: 400,
    mrpNum: 490,
    oldPrice: "₹490",
    discount: "↓ 18% Off",
    image: "https://merchant-cdn.storedum.com/358_220px_(4).png"
  },
  {
    id: 5,
    productId: "h-001",
    name: "Flex Premium Plus",
    price: "₹4,000",
    priceNum: 4000,
    mrpNum: 4999,
    oldPrice: "₹4,999",
    discount: "↓ 20% Off",
    image: "https://merchant-cdn.storedum.com/Copy_of_plain_image_2_17_220px_(2).png"
  },
  {
    id: 6,
    productId: "p-007",
    name: "L3 Thermoforming Machine",
    price: "₹1,20,000",
    priceNum: 120000,
    mrpNum: 129000,
    oldPrice: "₹1,29,000",
    discount: "↓ 7% Off",
    image: "https://merchant-cdn.storedum.com/Copy_of_plain_image_2_16_220px_(1).png"
  },
  {
    id: 7,
    productId: "m-001",
    name: "Twin Force Kit Without Handle + Universal Matrix Plier",
    price: "₹2,100",
    priceNum: 2100,
    mrpNum: 2300,
    oldPrice: "₹2,300",
    discount: "↓ 9% Off",
    image: "https://merchant-cdn.storedum.com/Copy_of_plain_image_2_13_220px_(4).png"
  },
  {
    id: 8,
    productId: "p-008",
    name: "Tack Screw",
    price: "₹350",
    priceNum: 350,
    mrpNum: 400,
    oldPrice: "₹400",
    discount: "↓ 13% Off",
    image: "https://merchant-cdn.storedum.com/plain_image_16_220px_(1).png"
  },
  {
    id: 9,
    productId: "m-002",
    name: "Ring O Matrix kit + Universal Matrix Plier",
    price: "₹3,400",
    priceNum: 3400,
    mrpNum: 3700,
    oldPrice: "₹3,700",
    discount: "↓ 8% Off",
    image: "https://merchant-cdn.storedum.com/plain_image_14_220px_(4).png"
  },
  {
    id: 10,
    productId: "m-003",
    name: "ProxyMaster Kit + Universal Matrix Plier",
    price: "₹10,900",
    priceNum: 10900,
    mrpNum: 11200,
    oldPrice: "₹11,200",
    discount: "↓ 3% Off",
    image: "https://merchant-cdn.storedum.com/plain_image_13_220px_(4).png"
  }
];

export function ProsthodonticsCarousel() {
  const scrollRef = useRef(null);
  const { navigate, openModal } = useUI();
  const { addToCart } = useCart();
  const open = (p) => navigate("product", { id: p.productId });
  const onAdd = (e, p) => {
    e.stopPropagation();
    addToCart(
      { id: p.productId, name: p.name, image: p.image, price: p.priceNum, mrp: p.mrpNum, category: "prosthodontics" },
      1
    );
    openModal("cart");
  };

  // Scroll handler — step adapts to viewport
  const scroll = (direction) => {
    if (scrollRef.current) {
      const w = typeof window !== "undefined" ? window.innerWidth : 1280;
      const scrollAmount = w < 640 ? 200 : w < 1024 ? 280 : 380;
      scrollRef.current.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
    }
  };

  return (
    <div className="w-full max-w-[1400px] mx-auto bg-white py-6 px-3 sm:px-6">
      
      {/* HEADER BAR SECTION */}
      <div className="flex items-center justify-between pb-3 mb-5 border-b border-gray-200">
        <div className="relative px-4 py-1">
          {/* Blue trapezoid layout background badge */}
          <div className="absolute inset-0 bg-[#E6F5FF] [clip-path:polygon(0_0,100%_0,calc(100%-15px)_100%,0_100%)] z-0"></div>
          <h2 className="relative z-10 m-0 text-xl font-bold text-gray-900 tracking-tight">
            Prosthodontics
          </h2>
        </div>
        <button
          onClick={() => navigate("category", { category: "implantology", title: "Prosthodontics" })}
          className="text-sm font-bold text-[#1976d2] hover:underline whitespace-nowrap"
        >
          View All &gt;&gt;
        </button>
      </div>

      {/* TRACK OVERFLOW CONSTRAINTS */}
      <div className="relative group">
        
        {/* Left Arrow Button Controls */}
        <button 
          onClick={() => scroll('left')}
          className="hidden md:flex absolute top-[40%] -translate-y-1/2 left-1 w-9 h-9 bg-white border border-gray-200 rounded-full shadow-md items-center justify-center z-10 text-gray-600 hover:text-black hover:bg-gray-50 cursor-pointer"
        >
          <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"></path>
          </svg>
        </button>

        {/* Scrollable Track - Clean overflow structure without layout breaking points */}
        <div
          ref={scrollRef}
          className="flex flex-nowrap gap-4 overflow-x-auto snap-x snap-mandatory pb-4 pt-1"
          style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
        >
          {/* Internal block layout tracker targeting hide bar triggers across global Webkit frameworks */}
          <style dangerouslySetInnerHTML={{__html: `div::-webkit-scrollbar { display: none; }`}} />

          {PRODUCTS.map((product) => (
            <div
              key={product.id}
              onClick={() => open(product)}
              className="w-[160px] sm:w-[180px] flex-none snap-start bg-white border border-gray-200 rounded-lg p-2.5 flex flex-col hover:shadow-md transition-shadow cursor-pointer"
            >
              
              {/* IMAGE ASSETS ENVELOPE: Squares container stops stretching */}
              <div className="relative w-full aspect-square bg-[#F8F9FA] rounded flex items-center justify-center p-2 shrink-0">
                <img 
                  src={product.image} 
                  alt={product.name} 
                  className="max-w-full max-h-full object-contain mix-blend-multiply"
                />
                {/* Save/Favorite Heart Icon */}
                <button
                  onClick={(e) => e.stopPropagation()}
                  className="absolute top-1.5 right-1.5 p-1 text-gray-400 hover:text-red-500 bg-transparent rounded-full transition-colors"
                >
                  <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3m-4.4 15.55-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05"></path>
                  </svg>
                </button>
              </div>

              {/* PRODUCT INFO BLOCKS */}
              <div className="mt-3 flex flex-col flex-grow">
                {/* Fixed-height name wrapper with line truncation */}
                <h3 className="text-[13px] font-semibold text-gray-800 leading-snug line-clamp-2 h-[36px]">
                  {product.name}
                </h3>
                
                {/* Pricing Metrics Group */}
                <div className="mt-2 flex items-center gap-1.5 flex-wrap">
                  <span className="text-[15px] font-bold text-black">{product.price}</span>
                  <span className="text-[11px] font-medium text-gray-400 line-through">{product.oldPrice}</span>
                </div>
                {/* Green Discount Text tag */}
                <div className="text-[12px] font-bold text-[#2e7d32] mt-0.5">
                  {product.discount}
                </div>

                {/* Base aligned Add Action Button */}
                <div className="mt-auto pt-3">
                  <button
                    onClick={(e) => onAdd(e, product)}
                    className="w-full py-1.5 bg-white border border-[#1976d2] text-[#1976d2] rounded text-[13px] font-bold hover:bg-[#1976d2] hover:text-white transition-colors cursor-pointer"
                  >
                    ADD
                  </button>
                </div>
              </div>

            </div>
          ))}
        </div>

        {/* Right Arrow Button Controls */}
        <button 
          onClick={() => scroll('right')}
          className="hidden md:flex absolute top-[40%] -translate-y-1/2 right-1 w-9 h-9 bg-white border border-gray-200 rounded-full shadow-md items-center justify-center z-10 text-gray-600 hover:text-black hover:bg-gray-50 cursor-pointer"
        >
          <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"></path>
          </svg>
        </button>

      </div>
    </div>
  );
}

export default ProsthodonticsCarousel;