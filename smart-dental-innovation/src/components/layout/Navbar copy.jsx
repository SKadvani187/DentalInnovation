import React from 'react';

export default function NavigationHeader() {
  const subNavButtonStyle = "text-[15px] font-semibold flex items-center gap-[5px] whitespace-nowrap border-0 border-solid border-[var(--border-color-light)] px-[10px] py-[2px] rounded-[8px] bg-none cursor-pointer";

  // Helper array to programmatically output the animated search letters accurately
  const searchPlaceholder = "Search over 1,000 Dental Products".split("");

  return (
    <div className="sticky top-0 flex flex-col z-[1000] w-full">

      {/* ROW 1: Main Top Bar */}
      <div className="relative w-full h-[65px] border-0 border-b border-solid border-[rgba(var(--border-color-1-rgb),0.5)] flex items-center justify-between z-10 transition-all duration-300 bg-[rgba(var(--background-primary-rgb),0.7)] backdrop-blur-[30px] px-[35px] gap-[10px]">

        {/* Logo & Brand Name Container */}
        <div className="flex items-center gap-2 h-[40px] cursor-pointer select-none">

          {/* Logo Graphic */}
          <img
            src="./src/assets/logo.png"
            alt="Logo Icon"
            className="h-[38px] w-auto object-contain opacity-100 transition-opacity duration-500 ease-in-out"
          />

          {/* Brand Typography */}
          <span className="text-xl font-extrabold tracking-tight text-gray-900 whitespace-nowrap">
            Dent
            <span className="text-[#1976d2]">Inno</span>
          </span>

        </div>

        {/* Absolute Centered Search Bar */}
        <div className="absolute left-1/2 -translate-x-1/2 w-[40%] h-[52px] border border-solid border-[var(--text-primary-2)] rounded-[100px] min-w-[300px] flex items-center px-[20px] gap-[10px] bg-[var(--background-primary)] cursor-pointer transition-all duration-300">
          <svg viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" className="h-[20px]">
            <path
              d="M14.5 14.5L10.5 10.5M6.5 12.5C3.18629 12.5 0.5 9.81371 0.5 6.5C0.5 3.18629 3.18629 0.5 6.5 0.5C9.81371 0.5 12.5 3.18629 12.5 6.5C12.5 9.81371 9.81371 12.5 6.5 12.5Z"
              stroke="black"
            ></path>
          </svg>

          {/* Animated Text Container */}
          <div className="inline-flex flex-wrap perspective-[1000px] min-h-[1.2em]">
            <div className="inline-flex flex-wrap">
              {searchPlaceholder.map((char, index) => (
                <span
                  key={index}
                  className={`inline-block origin-center-bottom opacity-100 transform-none ${char === " " ? "whitespace-pre" : "whitespace-normal"
                    }`}
                >
                  {char === " " ? "\u00A0" : char}
                </span>
              ))}
            </div>
          </div>
        </div>

        {/* Right Actions Block */}
        <div className="flex items-center gap-[10px]">
          {/* Account Button */}
          <button className="font-bold rounded-[8px] flex items-center cursor-pointer px-3 py-1 text-[var(--text-primary)]" aria-label="Account">
            <span className="mr-2">
              <div className="bg-[var(--main)] h-[25px] w-[25px] flex items-center justify-center rounded-[100px]">
                <svg className="text-white h-[18px]" focusable="false" aria-hidden="true" viewBox="0 0 24 24" data-testid="PersonRoundedIcon">
                  <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4"></path>
                </svg>
              </div>
            </span>
            <span className="normal-case font-medium text-[var(--text-primary)]">You</span>
          </button>

          {/* Wishlist Button */}
          <button className="text-[var(--text-primary)] p-2 cursor-pointer" aria-label="Wishlist">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
              <path fillRule="evenodd" d="M15.99 3.75c-1.311-.018-2.54.427-3.366 1.667a.75.75 0 01-1.248 0C10.554 4.184 9.303 3.75 8 3.75 5.373 3.75 2.75 5.955 2.75 9c0 3.178 2.055 5.99 4.375 8.065a20.921 20.921 0 003.27 2.397c.474.278.881.486 1.19.622a3.82 3.82 0 00.415.157l.05-.015c.088-.027.21-.074.365-.142.309-.136.716-.344 1.19-.622a20.92 20.92 0 003.27-2.397C19.195 14.99 21.25 12.18 21.25 9c0-3.037-2.616-5.211-5.26-5.25zm-3.992.06c1.13-1.165 2.6-1.58 4.013-1.56C19.366 2.3 22.75 5.039 22.75 9c0 3.822-2.445 7.01-4.875 9.184a22.424 22.424 0 01-3.51 2.572c-.512.3-.972.537-1.346.702-.187.082-.36.15-.515.2-.136.042-.32.092-.504.092-.183 0-.368-.05-.504-.093a5.262 5.262 0 01-.515-.199 13.403 13.403 0 01-1.345-.702 22.422 22.422 0 01-3.511-2.572C3.695 16.01 1.25 12.822 1.25 9c0-3.953 3.377-6.75 6.75-6.75 1.375 0 2.86.397 3.998 1.56z"></path>
            </svg>
          </button>

          {/* Cart Button */}
          <button className="font-bold rounded-[8px] flex items-center bg-[var(--main)] text-white px-4 py-2 text-sm uppercase tracking-wider cursor-pointer">
            <span className="mr-2">
              <svg className="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 24 24" data-testid="ShoppingBagIcon">
                <path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2m-8 4c0 .55-.45 1-1 1s-1-.45-1-1V8h2zm2-6c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2m4 6c0 .55-.45 1-1 1s-1-.45-1-1V8h2z"></path>
              </svg>
            </span>
            cart
          </button>
        </div>
      </div>

      {/* ROW 2: Sub-Navigation Cat Links */}
      <div className="h-[40px] bg-[rgba(var(--background-primary-rgb),0.7)] backdrop-blur-[30px] border-0 border-b border-solid border-[rgba(var(--border-color-1-rgb),0.2)] flex items-center justify-center gap-[45px] w-full overflow-visible px-[10px] no-scrollbar">
        <button className={subNavButtonStyle}>Category</button>
        <button className={subNavButtonStyle}>Combos</button>
        <button className={subNavButtonStyle}>Great Value Products</button>

        <div className="relative group">
          <button className={subNavButtonStyle}>
            <img
              src="https://merchant-cdn.storedum.com/istockphoto-1309295716-612x612.jpg"
              alt=""
              className="h-[16px] w-[16px] object-contain"
            />
            Shop by Price
            <svg className="h-[14px] transition-transform duration-200 group-hover:-rotate-180" viewBox="0 0 24 24" data-testid="KeyboardArrowDownIcon">
              <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"></path>
            </svg>
          </button>

          {/* Hover Dropdown */}
          <div className="absolute left-1/2 -translate-x-1/2 top-full pt-3 hidden group-hover:block z-[1100]">
            <div className="w-[230px] bg-white rounded-[14px] shadow-[0_12px_34px_rgba(0,0,0,0.16)] p-2">
              {[
                {
                  label: "Below ₹499",
                  icon: "M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z",
                },
                {
                  label: "Below ₹999",
                  icon: "M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z",
                },
                {
                  label: "Below ₹1999",
                  icon: "M20 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM10 4h4v2h-4V4z",
                },
              ].map((item) => (
                <button
                  key={item.label}
                  className="w-full flex items-center gap-[12px] px-[14px] py-[12px] rounded-[10px] text-[15px] font-semibold text-[var(--text-primary)] cursor-pointer hover:bg-[rgba(var(--border-color-1-rgb),0.6)] transition-colors"
                >
                  <svg className="h-[18px] w-[18px] shrink-0 text-[var(--text-primary)]" fill="currentColor" viewBox="0 0 24 24">
                    <path d={item.icon} />
                  </svg>
                  {item.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        <button className={subNavButtonStyle}>Events</button>
        <button className={subNavButtonStyle}>Wishlist</button>
        <button className={subNavButtonStyle}>About Us</button>
        <button className={subNavButtonStyle}>Contact Us</button>
      </div>

    </div>
  );
}