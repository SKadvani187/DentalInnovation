// Site-wide config — single source of truth for company info, contact, social, marketing copy.
// Edit here to update across all pages.

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
