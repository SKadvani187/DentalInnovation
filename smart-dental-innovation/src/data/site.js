// Site-wide config — single source of truth for company info, contact, social, marketing copy.
// Edit here to update across all pages.

// RF Cautery showcase section (home). Admin-managed via Settings → RF Cautery Section.
export const rfSection = {
  title: "RF Advance Cautery",
  productId: "p-001",
  image: "https://merchant-cdn.storedum.com/Untitled_design_6_(1).png",
  descShort: "High-performance surgical unit for precise, bloodless soft-tissue management with clean scalpel-like cutting and superior coagulation.",
  description: "The Radio Frequency Advance Electro Cautery by Younique Dental Innovations is a high-performance surgical unit designed to deliver precise, smooth, and bloodless soft-tissue management in dental procedures. Powered by advanced high-frequency radio waves, it enables clean scalpel-like cutting with excellent coagulation, ensuring faster healing and superior clinical outcomes.",
  features: [
    { image: "https://merchant-cdn.storedum.com/Untitled_design_9_(5).png", title: "Active Handle", desc: "A durable and ergonomically designed cautery active handle that ensures precise energy delivery and comfortable control during electrosurgical procedures." },
    { image: "https://merchant-cdn.storedum.com/Untitled_design_10_(15).png", title: "Hand Piece Pencil", desc: "A lightweight, ergonomically designed cautery hand switch pencil that provides precise, fingertip control for safe and efficient electrosurgical procedures." },
    { image: "https://merchant-cdn.storedum.com/Untitled_design_11_(11).png", title: "Bio Polar Tweezer", desc: "A high-precision bipolar cautery tweezer designed for controlled coagulation with minimal thermal spread and maximum surgical accuracy." },
  ],
};

// Trust badges strip (home, under category grid). Admin-managed via Settings.
// {dynamic:'productCount'} pulls the live product count; else label is shown as-is.
export const trustBadges = [
  { icon: "fa-solid fa-cube", label: "Products", dynamic: "productCount" },
  { icon: "fa-solid fa-hand-holding-medical", label: "Quick Service Support" },
  { icon: "fa-solid fa-circle-check", label: "100% Original" },
  { icon: "fa-solid fa-shield-halved", label: "Best Price" },
];

// Home secondary banners — admin-managed via Settings → Banners.
export const banners = {
  promo: {
    leftId: "i-001", topRightId: "i-002", bottomRightId: "i-003",
    leftImg: "https://merchant-cdn.storedum.com/new_website_banner_mobile_2.png",
    topRightImg: "https://merchant-cdn.storedum.com/new_website_banner_desktop_(2).webp",
    bottomRightImg: "https://merchant-cdn.storedum.com/new_website_banner_desktop.png",
    leftImgM: "https://merchant-cdn.storedum.com/new_website_banner_mobile_2_(1).png",
    topRightImgM: "https://merchant-cdn.storedum.com/new_banner_2.webp",
    bottomRightImgM: "https://merchant-cdn.storedum.com/new_website_banner_mobile_1_1.webp",
  },
  patti: {
    desktop: "https://merchant-cdn.storedum.com/website_patti_slider_desktop_(2).png",
    mobile: "https://merchant-cdn.storedum.com/Quick_Service_Support.png",
  },
};

// Home hero carousel slides (image + product link). Admin-managed via Settings → Banners.
export const heroSlides = [
  { src: "https://merchant-cdn.storedum.com/New_Website_slider_344_x_1080_px_5_1.webp", productId: "p-001" },
  { src: "https://merchant-cdn.storedum.com/New_Website_slider_344_x_1080_px_10.webp", productId: "p-002" },
  { src: "https://merchant-cdn.storedum.com/New_Website_slider_344_x_1080_px_9_(3).webp", productId: "p-003" },
  { src: "https://merchant-cdn.storedum.com/New_Website_slider_344_x_1080_px_9_1.webp", productId: "p-007" },
  { src: "https://merchant-cdn.storedum.com/Smart_Hex_driver.png", productId: "i-001" },
  { src: "https://merchant-cdn.storedum.com/New_Website_slider_344_x_1080_px_8.webp", productId: "p-010" },
  { src: "https://merchant-cdn.storedum.com/new_Website_slider_344_x_1080_px_5_(1).png", productId: "n-003" },
];

export const company = {
  name: "Smart Dental Innovations",
  shortName: "Dentinno",
  parent: "Younique Dental Innovations",
  tagline: "Innovating Dentistry, One Tool at a Time",
  description:
    "A division of Younique Dental Innovations, we are Surat's premier destination for advanced dental products — designed to empower clinicians, elevate care, and deliver clinical excellence in every procedure.",
  city: "Surat",
  state: "Gujarat",
  pincode: "395006",
  address: "Third Floor, Swastik Plaza, 308, Savlia Cir, Yogi Chowk Ground, Chikuwadi, Varachha, Surat, Gujarat 395006",
  addressShort: "Third Floor, Swastik Plaza, Varachha, Surat, Gujarat 395006",
  email: "info@smartdentalinnovations.com",
  emailSales: "smartdentalinnovations.web@gmail.com",
  phone: "+91 92653 18584",
  phoneSales: "+91 93287 62586",
  hours: "Mon to Sat (10:00 AM to 7:00 PM)",
};

export const stats = [
  { value: "1000", suffix: "+", label: "Products" },
  { value: "203k", suffix: "+", label: "Followers" },
  { value: "4.5", suffix: "★", label: "Avg Rating" },
  { value: "100", suffix: "%", label: "Original" },
];

export const socials = [
  { id: "facebook", label: "Facebook", url: "https://facebook.com" },
  { id: "instagram", label: "Instagram", url: "https://instagram.com" },
  { id: "youtube", label: "YouTube", url: "https://youtube.com" },
  { id: "google", label: "Google", url: "https://google.com" },
  { id: "linkedin", label: "LinkedIn", url: "https://linkedin.com" },
];

export const payments = [
  { id: "cod", label: "COD" },
  { id: "netbanking", label: "Net Banking" },
  { id: "upi", label: "UPI" },
  { id: "partial", label: "Partial Payment" },
  { id: "card", label: "Credit / Debit cards", span: 2 },
];

// Cart drawer cross-sell items (frequently bought together)
export const fbtItems = [
  { id: "fbt-1", name: "Radio Frequency Advance Cautery", warranty: "5 Year warranty", mrp: 24000, price: 23000, discount: 8, image: "https://merchant-cdn.storedum.com/ai_img_(1).webp" },
  { id: "fbt-2", name: "RF Smart Cautery", warranty: "2 Year warranty", mrp: 17900, price: 16500, discount: 8, image: "https://merchant-cdn.storedum.com/ai_img_1_(2).png" },
  { id: "fbt-3", name: "R.F Mini Cautery", warranty: "2 Year warranty", mrp: 14900, price: 13900, discount: 7, image: "https://merchant-cdn.storedum.com/ai_img_2_(3).png" },
];

// Free gifts threshold + items
export const freeGifts = {
  threshold: 5000,
  items: [
    { id: "g-1", name: "Antifog Mirror (Pack Of 25)", mrp: 1000, image: "https://merchant-cdn.storedum.com/dq3oxgejdhsf5sv5ym37_(7).webp" },
    { id: "g-2", name: "Super Torque Push Button Handpiece", mrp: 1400, image: "https://merchant-cdn.storedum.com/47_(8).png" },
  ],
};

// Bulk savings rule
export const bulkRule = {
  minQty: 2,
  rate: 0.1, // 10% per line when qty >= minQty
};

// Coupon offers — applied at cart. Discount: { type: "flat"|"percent", value, max? }.
export const coupons = [
  {
    code: "SDI100",
    title: "Flat ₹100 off",
    desc: "Min order ₹1,500. Auto-eligible.",
    minSubtotal: 1500,
    discount: { type: "flat", value: 100 },
  },
  {
    code: "WELCOME10",
    title: "10% off (up to ₹500)",
    desc: "First order or any cart above ₹2,000.",
    minSubtotal: 2000,
    discount: { type: "percent", value: 10, max: 500 },
  },
  {
    code: "DENTAL15",
    title: "Flat 15% off",
    desc: "On orders above ₹10,000.",
    minSubtotal: 10000,
    discount: { type: "percent", value: 15, max: 3000 },
  },
  {
    code: "FREESHIP",
    title: "Free shipping",
    desc: "Removes delivery charges. Min ₹500.",
    minSubtotal: 500,
    discount: { type: "flat", value: 0 },
    perk: "shipping",
  },
];

// Sort options used across category & combos
export const sortOptions = [
  { id: "all", label: "All Products" },
  { id: "price-asc", label: "Price: Low to High" },
  { id: "price-desc", label: "Price: High to Low" },
  { id: "discount", label: "Top Discount" },
  { id: "rating", label: "Top Rated" },
];

// Price range presets used by Shop by Price dropdown
export const pricePresets = [
  { label: "Below ₹499", max: 499 },
  { label: "Below ₹999", max: 999 },
  { label: "Below ₹1999", max: 1999 },
];

export const priceBounds = { min: 10, max: 500000 };

// Product detail page tiered offers
export const tierOffers = [
  { minQty: 2, rate: 0.05, label: "Buy 2 or above" },
  { minQty: 5, rate: 0.08, label: "Buy 5 or above" },
];

// Product detail page defaults
export const productDefaults = {
  reviews: 0,
  rating: 5.0,
  deliveryDays: "3–5 business days",
  breadcrumbExtraCount: 5,
};

// Section title → category filter mapping (used by home ProductSection View All)
export const sectionToCategory = {
  Bestsellers: null,
  "New Arrivals": "new",
  Implantology: "implantology",
  Handpiece: "handpiece",
  "Matrix System": "matrix",
  Endodontics: "endodontics",
};

// Sample reviews shown in product detail reviews dropdown
export const sampleReviews = [
  { id: "r1", name: "Dr. Patel", stars: 5, date: "2 weeks ago", text: "Excellent build quality and precision. My clinic team loves it." },
  { id: "r2", name: "Dr. Mehta", stars: 5, date: "1 month ago", text: "Best purchase for our endo procedures. Fast delivery too." },
  { id: "r3", name: "Dr. Shah", stars: 4, date: "2 months ago", text: "Works as advertised. Good value for money." },
];

// Product detail — benefits strip (Smart Dental Innovation Benefits)
export const productBenefits = [
  { id: "secure", label: "Secure Payment", icon: "shield" },
  { id: "cancel", label: "Hassle Free Cancellation*", icon: "x" },
  { id: "replace", label: "7 Days Replacement*", icon: "refresh" },
  { id: "genuine", label: "100% Genuine", icon: "check" },
];

// Default product detail content (Highlights / Accordions / FAQs) — used when product doesn't override
export const productContent = {
  highlights: [
    { title: "Key Features", text: "High-frequency alternating current for precise soft-tissue cutting and coagulation, smooth scalpel-like incisions, multiple intensity levels, and bipolar capability." },
    { title: "Clinical Applications", text: "Suitable for gingivectomy, frenectomy, biopsy, hemostasis, and other electrosurgical procedures." },
    { title: "Electrodes & Accessories", text: "Comes with a selection of interchangeable electrodes (needle, loop, ball) for flexibility in different surgical needs." },
  ],
  accordions: [
    { id: "desc", title: "Description", body: "Premium dental product engineered for clinical excellence and patient outcomes." },
    { id: "spec", title: "Key Specifications", body: "Power: 200W. Frequency: 3.5 MHz. Intensity Levels: 6. Display: LED. Foot control included." },
    { id: "use", title: "Directions to Use", body: "Connect handpiece, set intensity, ground plate to patient, activate via foot switch or hand switch." },
    { id: "pack", title: "Packaging Info", body: "Main unit, handpiece pencil, bipolar tweezer, electrodes set, foot switch, manual, warranty card." },
    { id: "warr", title: "Warranty", body: "Manufacturer warranty as per product. Standard 2-year warranty unless specified." },
  ],
  faqs: [
    { id: "f1", q: "Does the device come with different electrodes?", a: "Yes, it includes a variety of interchangeable electrodes like needle, loop, and ball types to suit different surgical needs." },
    { id: "f2", q: "What is radio frequency advance cautery used for?", a: "The radio frequency advance cautery is used for precise soft-tissue cutting and coagulation in dental procedures. It helps achieve minimal tissue damage and excellent hemostasis." },
    { id: "f3", q: "Is foot control included?", a: "Yes, includes both foot and hand switch options for flexibility." },
    { id: "f4", q: "Does it support bipolar mode?", a: "Yes, supports both monopolar and bipolar modes." },
    { id: "f5", q: "What is the warranty period?", a: "Standard 2-year manufacturer warranty. Extended warranty available on select models." },
  ],
};

