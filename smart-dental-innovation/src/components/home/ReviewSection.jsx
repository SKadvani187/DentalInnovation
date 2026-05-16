import React from 'react';

const REVIEWS_DATA = [
  {
    id: 1,
    reviewer: "Aaradhya Dubey",
    avatar: "https://ecommerce-static-assets.s3.ap-south-1.amazonaws.com/dummy-avatar.jpg",
    productName: "Implant Hex Driver",
    productImage: "https://merchant-cdn.storedum.com/HexDriver_(4).pngv1753787187width1946",
    comment: "I recently used this implant hex driver and was impressed by its quality. It felt sturdy in my hand and provided a secure grip during the procedure. Highly recommend!",
    rating: 5
  },
  {
    id: 2,
    reviewer: "Neha Sharma",
    avatar: "https://ecommerce-static-assets.s3.ap-south-1.amazonaws.com/dummy-avatar.jpg",
    productName: "Korean D Regular Cover Screw",
    productImage: "https://merchant-cdn.storedum.com/Screenshot_2026-01-27_110948.png",
    comment: "The Korean D Regular Cover Screw is a must-have for dental implantology. It's well-designed and provides excellent protection for the implant threading during the healing phase. Highly recommend!",
    rating: 5
  },
  {
    id: 3,
    reviewer: "Meera Singh",
    avatar: "https://ecommerce-static-assets.s3.ap-south-1.amazonaws.com/dummy-avatar.jpg",
    productName: "Korean O Close Tray Transfer Coping",
    productImage: "https://merchant-cdn.storedum.com/44.png",
    comment: "I was amazed by the impressive performance of the Korean O Close Tray Transfer Coping. It helped me achieve accurate implant positioning and simplified the entire process. Definitely a game-changer in dental prosthetics.",
    rating: 5
  },
  {
    id: 4,
    reviewer: "Aryan Verma",
    avatar: "https://ecommerce-static-assets.s3.ap-south-1.amazonaws.com/dummy-avatar.jpg",
    productName: "Implant Hex Driver",
    productImage: "https://merchant-cdn.storedum.com/HexDriver_(4).pngv1753787187width1946",
    comment: "I appreciate the maintenance tips provided with this hex driver. Regular inspection and proper storage ensure its longevity and functionality. Great product design!",
    rating: 5
  }
];

export default function ReviewsSection() {
  return (
    /* EXACT SIDE SPACING FROM SCREENSHOT: Padding rules matching website layout boundaries */
    <div className="w-full px-4 sm:px-8 md:px-12 py-10 bg-[var(--background-primary)]">
      <div className="homepage-container-large mx-auto max-w-7xl relative flex flex-col items-center">
        
        {/* FIXED FONT CLASS: Applies the global El Messiri style perfectly */}
        <h2 className="font-messiri font-bold text-center text-[var(--main)] text-[26px] sm:text-[34px] tracking-wide mb-10">
          Reviews
        </h2>

        {/* 4-Column desktop track / Touch swipe tracking for mobile layout viewports */}
        <div className="w-full flex sm:grid sm:grid-cols-4 gap-[30px] overflow-x-auto sm:overflow-x-visible snap-x snap-mandatory scrollbar-none pb-6">
          {REVIEWS_DATA.map((review) => (
            <div 
              key={review.id} 
              className="w-[85vw] sm:w-full shrink-0 snap-center relative flex flex-col items-center"
            >
              {/* Profile Avatar sitting strictly on z-20 above the card border lines */}
              <div className="pointer-events-none h-[100px] w-[100px] bg-white absolute rounded-full top-0 left-1/2 -translate-x-1/2 z-20 border border-neutral-100 shadow-sm overflow-hidden">
                <img 
                  alt={`${review.reviewer} profile`} 
                  src={review.avatar} 
                  className="h-full w-full object-cover"
                />
              </div>

              {/* Card Container Layout Box */}
              <div className="bg-transparent pt-[50px] w-full mt-[50px] border border-[var(--main)] rounded-none relative z-10">
                <div className="p-5 h-[255px] flex flex-col items-center justify-start">
                  
                  {/* Product Tagging Block */}
                  <div className="flex items-center mb-3 cursor-pointer w-full justify-center">
                    <div className="h-[35px] w-[35px] rounded-full shrink-0 bg-[var(--background-primary)] flex items-center justify-center border border-neutral-100">
                      <img 
                        alt={review.productName} 
                        src={review.productImage} 
                        className="h-[28px] w-[28px] rounded-full object-cover"
                      />
                    </div>
                    {/* FIXED FONT CLASS: Applies the global Montserrat font style */}
                    <p className="font-montserrat text-[var(--text-primary)] ml-2.5 font-semibold line-clamp-1 text-xs uppercase tracking-wider">
                      {review.productName}
                    </p>
                  </div>

                  {/* Review Text Body Paragraph */}
                  <p className="font-montserrat text-[13px] text-center text-[var(--text-secondary)] font-medium whitespace-pre-line line-clamp-5 leading-relaxed px-1">
                    "{review.comment}"
                  </p>

                  <div className="grow" />

                  {/* Reviewer Username */}
                  <label className="font-montserrat font-bold text-[var(--text-primary)] block text-[14px] tracking-wide mt-2">
                    {review.reviewer}
                  </label>

                  {/* Rating Stars Element Row */}
                  <div className="flex items-center justify-center mt-2 gap-0.5">
                    {[...Array(review.rating)].map((_, i) => (
                      <svg
                        key={i}
                        className="w-[16px] h-[16px]"
                        viewBox="0 0 24 24"
                        style={{ color: 'var(--rating-star-color, #FFB400)' }}
                      >
                        <path 
                          fill="currentColor" 
                          d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" 
                        />
                      </svg>
                    ))}
                  </div>

                </div>
              </div>
            </div>
          ))}
        </div>

      </div>
    </div>
  );
}
