// Site-wide config — single source of truth for company info, contact, social, marketing copy.
// Edit here to update across all pages.

// Home page section order + visibility. Admin-managed via Settings → Home Layout.
// type: fixed block id. For "productSection", `source` selects the product list.
export const homeSections = [
  { key: "hero",          type: "hero",            label: "Hero Slider",        enabled: true },
  { key: "categoryGrid",  type: "categoryGrid",    label: "Category Grid",      enabled: true },
  { key: "trustBadges",   type: "trustBadges",     label: "Trust Badges Strip", enabled: true },
  { key: "bestsellers",   type: "productSection",  label: "Bestsellers",        source: "featured",     eyebrow: "Top Picks", enabled: true },
  { key: "promoGrid",     type: "promoGrid",       label: "Promo Banner Grid",  enabled: true },
  { key: "newArrivals",   type: "productSection",  label: "New Arrivals",       source: "new",          eyebrow: "Fresh In", accent: "orange", enabled: true },
  { key: "rfCautery",     type: "rfCautery",       label: "RF Cautery Showcase", enabled: true },
  { key: "implantology",  type: "productSection",  label: "Implantology",       source: "implantology", enabled: true },
  { key: "premium",       type: "premium",         label: "Premium Categories", enabled: true },
  { key: "handpieces",    type: "productSection",  label: "Handpiece",          source: "handpiece",    enabled: true },
  { key: "homeBanner",    type: "homeBanner",      label: "Home Banner",        enabled: true },
  { key: "matrixSystem",  type: "productSection",  label: "Matrix System",      source: "matrix",       enabled: true },
  { key: "endodontics",   type: "productSection",  label: "Endodontics",        source: "endodontics",  enabled: true },
  { key: "reviews",       type: "reviews",         label: "Reviews",            enabled: true },
  { key: "prosthodontics",type: "prosthodontics",  label: "Prosthodontics Carousel", enabled: true },
];

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

// About page config — fully admin-managed via Settings → About Page.
export const aboutConfig = {
  hero: {
    badge: "About Smart Dental Innovations",
    title: "Innovating Dentistry, One Tool at a Time",
    description: "A division of Younique Dental Innovations, we are Surat's premier destination for advanced dental products — designed to empower clinicians, elevate care, and deliver clinical excellence in every procedure.",
    ctaText: "Explore Our Products",
    cardTitle: "Smart Dental Innovations",
    stats: [
      { value: "1000+", label: "Products" },
      { value: "203k+", label: "Followers" },
      { value: "4.5★", label: "Avg Rating" },
      { value: "100%", label: "Original" },
    ],
  },
  story: {
    label: "Our Story",
    heading: "Built for Dentists Who Think Ahead",
    parentLabel: "A Division Of",
    parentName: "Younique Dental Innovations",
    paragraphs: [
      "Smart Dental Innovations was born from a simple vision — to make premium, innovative dental tools accessible to every clinician across India. As a proud division of Younique Dental Innovations, we combine world-class engineering with a deep understanding of the dental profession.",
      "From our base in Surat, Gujarat, we serve dental professionals nationwide with over 1,000+ carefully curated products — from RF Cautery units and implant systems to handpieces, burs, and clinic setup essentials.",
    ],
    promises: [
      { title: "100% Original Products", text: "Every product is authentic, quality-checked, and backed by manufacturer warranties." },
      { title: "Expert-Curated Range", text: "Products selected by dental professionals for dental professionals — no compromises." },
      { title: "Dedicated Support Team", text: "Responsive support before, during, and after every order." },
    ],
  },
  stats: [
    { value: "1,000+", label: "Dental Products" },
    { value: "203k+", label: "Social Followers" },
    { value: "4.5★", label: "Average Rating" },
    { value: "100%", label: "Original & Verified" },
  ],
  milestones: {
    label: "Our Journey", heading: "Milestones that matter",
    subtitle: "From a small Surat office to serving clinics nationwide — here's how far we've come.",
    items: [
      { year: "2019", title: "Founded in Surat", text: "Started as a small dental supply venture with a focus on imported handpieces." },
      { year: "2021", title: "1,000+ products", text: "Catalogue expanded to cover Endodontics, Implantology, Restorative & more." },
      { year: "2023", title: "Pan-India shipping", text: "Reached 500+ pincodes with reliable 5–7 day delivery and free shipping ₹20k+." },
      { year: "2025", title: "Division of Younique", text: "Joined Younique Dental Innovations to scale manufacturer-direct sourcing." },
      { year: "2026", title: "1000+ clinics served", text: "Trusted partner to over a thousand dental clinics across 28 states." },
    ],
  },
  coreValues: {
    label: "What We Stand For", heading: "Our Core Values",
    subtitle: "Every decision we make is guided by principles that put dentists and patient care first.",
    items: [
      { n: "01", title: "Innovation First", text: "We continuously scout and introduce cutting-edge dental technologies so your clinic stays ahead of the curve — always.", icon: "🏛" },
      { n: "02", title: "Clinical Excellence", text: "Every product is rigorously evaluated for clinical performance. We only offer what we'd use in our own practice.", icon: "🎯" },
      { n: "03", title: "Dentist Partnership", text: "We're not just a store — we're a partner in your growth. Bulk pricing, expert guidance, and a team that truly understands dentistry.", icon: "🤝" },
      { n: "04", title: "Smart Value", text: "Premium quality at fair prices. We work directly with manufacturers and innovators to ensure you get the best deal, always.", icon: "⚡" },
      { n: "05", title: "Reliability & Warranty", text: "Backed by manufacturer warranties, every product we sell is built to last. Our after-sales support is second to none.", icon: "🛡" },
      { n: "06", title: "Continuous Growth", text: "We grow when our dentists grow. Your success is our metric — and we're committed to growing alongside you, every step of the way.", icon: "🚀" },
    ],
  },
  leadership: {
    label: "The People", heading: "Meet the Team",
    subtitle: "Clinicians, engineers, and operators working to make modern dentistry accessible.",
    team: [
      { name: "Dr. Rakesh Patel", role: "Founder & CEO", bio: "20+ years in dental supplies. Vision-driven leader.", img: "https://merchant-cdn.storedum.com/ai_img_44.png" },
      { name: "Dr. Priya Shah", role: "Chief Clinical Officer", bio: "BDS, MDS Endodontics. Curates clinical product range.", img: "https://merchant-cdn.storedum.com/ai_img_40_(5).png" },
      { name: "Hiren Mehta", role: "Head of Operations", bio: "10+ years logistics & supply chain expertise.", img: "https://merchant-cdn.storedum.com/ai_img_42_(1).png" },
      { name: "Ankit Joshi", role: "Customer Success Lead", bio: "Ensures every clinic gets white-glove support.", img: "https://merchant-cdn.storedum.com/ai_img_31_(2).png" },
    ],
  },
  whyTrust: {
    label: "Why Smart Dental", heading: "Why Dentists Trust Us",
    subtitle: "Thousands of dental professionals across India choose Smart Dental Innovations for one simple reason — we deliver exactly what we promise.",
    rows: [
      { icon: "check", title: "Curated, Not Just Listed", text: "Every product in our catalog has been assessed for clinical utility, quality, and value before being offered to you." },
      { icon: "shield", title: "Warranty-Backed Products", text: "From 6-month to 2-year warranties, shop with confidence knowing every major product is protected." },
      { icon: "clock", title: "Fast & Secure Delivery", text: "Reliable shipping with secure packaging — your instruments arrive safely, on time, ready to use." },
      { icon: "chat", title: "Expert Customer Support", text: "Talk to real dental professionals on our team, Monday to Saturday from 9 AM to 7 PM." },
      { icon: "dollar", title: "Bulk & Clinic Pricing", text: "Setting up a new clinic or stocking up? Contact us for special bulk pricing tailored to your needs." },
    ],
    satTitle: "Customer Satisfaction", satRating: "4.5 / 5",
    satBars: [
      { label: "Product Quality", value: 96 },
      { label: "Delivery Speed", value: 91 },
      { label: "Value for Money", value: 94 },
      { label: "Customer Support", value: 89 },
    ],
  },
  missionVision: {
    label: "Our Direction", heading: "Mission & Vision",
    subtitle: "We're on a mission to redefine how dental professionals access, experience, and benefit from modern dental technology.",
    mission: "To provide every dental professional in India with access to innovative, reliable, and affordable dental products — paired with expert guidance and support — so they can deliver exceptional patient care without compromise. We strive to be the most trusted dental supply partner in the country.",
    vision: "To become India's leading platform for smart dental innovation — where dentists discover tomorrow's tools today. We envision a future where every clinic, whether in a metro or a small town, has equal access to world-class dental technology that transforms patient outcomes.",
  },
  testimonials: {
    label: "Trusted by Dentists", heading: "What clinicians say",
    items: [
      { name: "Dr. Amit Sharma", clinic: "Sharma Dental Care, Mumbai", stars: 5, text: "Switched all my supplies to SDI last year. Their handpieces are buttery smooth and warranty claims took 3 days — fastest I've ever seen." },
      { name: "Dr. Kavita Reddy", clinic: "Smile Studio, Hyderabad", stars: 5, text: "The bulk pricing for our new clinic setup saved us nearly ₹2L. Sales team understood exactly what a fresh clinic needs." },
      { name: "Dr. Ravi Kumar", clinic: "Kumar Family Dentistry, Pune", stars: 4, text: "RF Cautery they recommended is a game-changer. Patients heal faster and procedures are cleaner. Wish I'd switched sooner." },
      { name: "Dr. Neha Iyer", clinic: "Iyer Endodontics, Chennai", stars: 5, text: "Endodontic files are top quality and delivery is always on time. Customer support actually answers — rare these days." },
    ],
  },
  certifications: {
    label: "Certified & Trusted", heading: "Quality you can verify",
    items: [
      { label: "ISO 13485:2016", desc: "Medical Device Quality", icon: "📋" },
      { label: "CE Marked", desc: "European Conformity", icon: "✅" },
      { label: "FDA Listed", desc: "US Compliance", icon: "🇺🇸" },
      { label: "MDR Compliant", desc: "EU Medical Device Reg.", icon: "🛡️" },
      { label: "GST Registered", desc: "Tax-compliant invoicing", icon: "📄" },
      { label: "Made in India", desc: "Atmanirbhar Bharat", icon: "🇮🇳" },
    ],
  },
  cta: {
    label: "Get Started", heading: "Ready to Elevate Your Dental Practice?",
    subtitle: "Join thousands of dental professionals who trust Smart Dental Innovations for quality products, fair prices, and genuine expertise.",
    shopText: "Shop Now", contactText: "Contact Us",
  },
};

// About page section layout — admin can show/hide & reorder each block.
export const aboutSections = [
  { key: "hero",          label: "Hero",            enabled: true },
  { key: "story",         label: "Our Story",       enabled: true },
  { key: "stats",         label: "Stats Strip",     enabled: true },
  { key: "milestones",    label: "Milestones",      enabled: true },
  { key: "coreValues",    label: "Core Values",     enabled: true },
  { key: "leadership",    label: "Leadership / Team", enabled: true },
  { key: "whyTrust",      label: "Why Trust Us",    enabled: true },
  { key: "missionVision", label: "Mission & Vision", enabled: true },
  { key: "testimonials",  label: "Testimonials",    enabled: true },
  { key: "certifications", label: "Certifications", enabled: true },
  { key: "whatWeOffer",   label: "What We Offer",   enabled: true },
  { key: "cta",           label: "Bottom CTA",      enabled: true },
  { key: "contactStrip",  label: "Contact Strip",   enabled: true },
  { key: "socialStrip",   label: "Social Strip",    enabled: true },
];

// Contact page section layout — admin can show/hide & reorder each block.
export const contactSections = [
  { key: "hero",           label: "Hero",            enabled: true },
  { key: "quickActions",   label: "Quick Actions",   enabled: true },
  { key: "form",           label: "Contact Form",    enabled: true },
  { key: "contactMethods", label: "Reach Us",        enabled: true },
  { key: "businessHours",  label: "Business Hours",  enabled: true },
  { key: "officeMap",      label: "Our Office (Map)", enabled: true },
  { key: "faq",            label: "FAQs",            enabled: true },
];

// Combos page chrome (hero + trust strip + labels). Admin-managed via Settings → Catalog.
export const combosPage = {
  heroBadge: "Bundle & Save",
  heroTitle: "Combo Packs",
  savePrefix: "Save up to",
  saveSuffix: "across",
  subtitle: "Hand-picked product bundles — clinic essentials grouped together at a better price than buying separately.",
  bundleNote: "Multi-product bundle",
  trust: [
    { icon: "shield", title: "100% Genuine", desc: "Manufacturer-sourced" },
    { icon: "save", title: "Bundle Savings", desc: "Better than buying separately" },
    { icon: "ship", title: "Pan-India Shipping", desc: "5–7 day delivery" },
    { icon: "help", title: "Need help?", desc: "We're here to help" },
  ],
};

// Contact page config (FAQs, departments, business hours). Admin-managed via Settings → Contact Page.
export const contactConfig = {
  departments: [
    { id: "sales", label: "Sales Inquiry", icon: "💼", desc: "Bulk orders, demos, quotes" },
    { id: "support", label: "Product Support", icon: "🛠️", desc: "Warranty, repairs, returns" },
    { id: "partnership", label: "Partnerships", icon: "🤝", desc: "Distributors, resellers" },
    { id: "general", label: "General Query", icon: "💬", desc: "Anything else" },
  ],
  faqs: [
    { q: "How fast do you respond?", a: "Sales & support inquiries: under 4 business hours (Mon–Sat, 10 AM–7 PM IST). General queries: within 24 hours." },
    { q: "Do you ship pan-India?", a: "Yes — 5–7 business days to most pincodes. Free shipping above ₹20,000. COD available with verified pincode." },
    { q: "Can I visit your office?", a: "Walk-ins welcome Mon–Sat, 10 AM–7 PM. We recommend booking via call to ensure product samples & demo handpieces are ready." },
    { q: "How do bulk orders work?", a: "Use the Bulk Quote form on any product page (orders above ₹10,000). Our team replies with a custom quote within 24 hours." },
  ],
  businessHours: [
    { day: "Monday – Friday", hours: "10:00 AM – 7:00 PM" },
    { day: "Saturday", hours: "10:00 AM – 7:00 PM" },
    { day: "Sunday", hours: "Closed" },
  ],
  responseNote: "our team replies within 4 business hours. No bots.",
  timezone: "India Standard Time (UTC+5:30)",
  openHours: { openHour: 10, closeHour: 19, openDays: [1,2,3,4,5,6], openLabel: "Open now", closedLabel: "Closed" },
  heroBadge: "We're online now",
  heroBadgeClosed: "We're currently offline",
  heroTitle: "Let's talk about your clinic",
  heroSubtitle: "Bulk orders, product demos, technical support, partnership — our team replies within 4 business hours. No bots.",
  formTitle: "Send us a message",
  formChip: "Replies in 4 hrs",
  officeSubtitle: "Walk-in welcome — see products in action, talk to our team, leave with a demo.",
  officeBullets: [
    "Near Yogi Chowk metro stop",
    "Free parking in basement",
    "Book a slot via call to skip wait",
  ],
  labels: {
    whatsapp: "Chat on WhatsApp", whatsappSub: "Instant reply",
    call: "Call us", email: "Email us", visit: "Visit our office",
    reachHeading: "Reach us directly", faqHeading: "Common questions",
    successTitle: "Message received!",
    formSubtitle: "Fill the form or email us directly.",
    msgHint: "Be specific — helps us reply faster",
    sendBtn: "Send Message", deptHelp: "What can we help with?",
    fieldName: "Full Name *", fieldPhone: "Phone Number *", fieldEmail: "Email *", fieldMsg: "Your message *",
    visitBadge: "Visit Us", officeHeading: "Our Office",
    reachSales: "Sales", reachSupport: "Support", reachEmailSales: "Email Sales", reachGeneral: "General Info",
    privacyNote: "By submitting, you agree to our Privacy Policy. We never share your data.",
    followHeading: "Follow us", hoursHeading: "Business hours",
  },
  statChips: [
    { icon: "⚡", label: "Response: under 4 hrs" },
    { icon: "🕐", label: "Mon–Sat • 10 AM – 7 PM IST" },
    { icon: "🦷", label: "Trusted by 1000+ clinics" },
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

// Header branding — admin-managed via Settings → General → Logos & WhatsApp.
// Two header logos (no text) + the storefront WhatsApp number. Empty logo URLs
// fall back to the bundled logo asset in the header component.
export const branding = {
  logo1: "",
  logo2: "",
  whatsappNumber: "919328762586",
};

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

// Shipping rule — must mirror dentinno/api/v1/_pricing.php (settingVal 'shippingConfig').
// Flat rate unless the order subtotal reaches the free threshold. Admin-configurable.
export const shippingConfig = {
  freeThreshold: 20000,
  flatRate: 600,
};

// Tax (GST) rule — disabled by default (prices treated as tax-inclusive). When the
// admin enables it and sets inclusive:false, rate% is added on the discounted amount.
// Must mirror dentinno/api/v1/_pricing.php (settingVal 'taxConfig').
export const taxConfig = {
  enabled: false,
  rate: 0,        // e.g. 18 for 18% GST
  inclusive: true,
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

// Great Value Products = products with discount >= this %. Admin-managed.
export const gvpThreshold = 10;

// Combos: stock at or below this shows the "Low Stock! Hurry" urgency ribbon. Admin-managed.
export const lowStockThreshold = 10;

// Great Value Products page chrome (hero copy). Admin-managed via Settings → Catalog.
export const gvpPage = {
  heroBadge: "Great Value Deals",
  heroTitle: "Best Value Products",
  savePrefix: "Save up to",
  saveSuffix: "across",
  subtitle: "Hand-picked products with the biggest discounts — clinic essentials at unbeatable prices.",
  statDeals: "Live deals",
  statDiscount: "Max discount",
  statSavings: "Total savings",
};

// Shop by Price page chrome (hero copy). Admin-managed via Settings → Catalog.
export const shopByPricePage = {
  heroBadge: "Shop by Budget",
  heroTitle: "Shop by Price",
  subtitle: "Pick a budget — we'll show every product that fits, from quick buys to clinic essentials.",
  customLabel: "Custom Range",
  customDesc: "Set your own budget",
};

// Main navbar menu — admin can rename / reorder / show-hide each item.
// `view` maps to a route; "price" is the special Shop-by-Price dropdown; auth-gated items use `auth:true`.
export const navMenu = [
  { id: "category", label: "Category",            view: "category", enabled: true },
  { id: "offers",   label: "Offer Zone",          view: "offers",   enabled: true },
  { id: "combos",   label: "Combos",              view: "combos",   enabled: true },
  { id: "gvp",      label: "Great Value Products", view: "gvp",     enabled: true },
  { id: "price",    label: "Shop by Price",       view: "price",    enabled: true },
  { id: "events",   label: "Events",              view: "events",   enabled: true },
  { id: "wishlist", label: "Wishlist",            view: "wishlist", enabled: true, auth: true },
  { id: "about",    label: "About Us",            view: "about",    enabled: true },
  { id: "contact",  label: "Contact Us",          view: "contact",  enabled: true },
];

// Offer Zone hero copy (numbers auto-computed from offers). Admin-managed.
export const offerZoneHero = {
  badge: "Mega Deals Live",
  title: "Offer Zone",
  savePrefix: "Save up to",
  saveSuffix: "this month",
  subtitle: "Hand-picked combos with free handpieces, free mirrors & free files. Doctor-loved, clinic-tested. New bundles drop every week.",
  expiryLabel: "Next deal expires in",
  restockNote: "⚡ Restocks limited. Once gone, gone.",
  limitedLabel: "Limited Time Offer",
  topDealLabel: "Top Deal",
  grabCta: "Grab This Deal",
  freeItemsLabel: "Free Items Included",
  urgentNote: "Hurry! Less than 12 hours left",
  valueProps: [
    { icon: "shield", title: "100% Genuine", desc: "Manufacturer-sourced, batch-tested" },
    { icon: "ship", title: "Pan-India Shipping", desc: "5–7 day delivery to most pincodes" },
    { icon: "doctor", title: "Doctor-Loved", desc: "Trusted by 1000+ clinics across India" },
    { icon: "returns", title: "Easy Returns", desc: "7-day no-questions-asked returns" },
  ],
};

// Policy pages (Return / Terms / Privacy). Admin-managed via Settings → General.
export const policies = {
  return: { title: "Return Policy", sections: [
    { h: "Eligibility", p: "Most unused products may be returned within 7 days of delivery if sealed and in original packaging. Hygiene-sensitive items (intraoral burs, files, scrubs) are non-returnable once opened." },
    { h: "Process", p: "Raise a return from Account → Orders → select item → Request Return. Our team will schedule pickup within 48 hours." },
    { h: "Refund Timeline", p: "Refunds initiate within 3 business days of pickup quality-check. UPI/Card refunds reach your account in 5–7 business days. COD orders refund to original UPI/Bank shared at pickup." },
    { h: "Damaged or Incorrect Items", p: "Report within 48 hours of delivery with unboxing video. Full refund or replacement at our cost." },
  ]},
  terms: { title: "Terms of Use", sections: [
    { h: "Acceptance", p: "By accessing this site you agree to these terms. Products are sold business-to-business to licensed dental professionals and registered clinics." },
    { h: "Product Information", p: "We attempt to display accurate product details, but specifications, packaging, and availability may change without notice. Always verify catalogue with our team before bulk orders." },
    { h: "Orders & Pricing", p: "Prices are in INR and exclusive of statutory taxes unless stated. We reserve the right to cancel orders with incorrect price/stock, with full refund." },
    { h: "Liability", p: "Smart Dental Innovations is not liable for clinical outcomes resulting from product use. Operators must follow manufacturer instructions and applicable regulations." },
    { h: "Governing Law", p: "Disputes are subject to courts of Surat, Gujarat." },
  ]},
  privacy: { title: "Privacy Policy", sections: [
    { h: "Data We Collect", p: "Name, phone, email, billing & shipping address, clinic GSTIN (if provided), order history, device/browser metadata, and pincode entered for delivery checks." },
    { h: "How We Use It", p: "Fulfilling orders, payment processing, delivery, customer support, fraud prevention, statutory invoicing, and product communication you've opted into." },
    { h: "Sharing", p: "Limited sharing with logistics, payment gateways, and tax authorities as required. We never sell personal data." },
    { h: "Your Rights", p: "Email us to access, correct, or delete your data. We retain order records as required by Indian tax law (typically 7 years)." },
    { h: "Cookies", p: "We use functional cookies for cart, login, and analytics. You may disable cookies, but parts of the site may not work." },
  ]},
};

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
  replacementText: "Easy 7 days replacement available",
  variantDeliveryNote: "📦 Get it by 3–5 days",
  variantCodNote: "💳 COD available",
};

// Product page "Payment Options" card. Admin-managed via Settings → Catalog → Payments.
// icon = rupee | bank | card | upi ; span = grid columns (1–12).
export const paymentOptions = [
  { id: "cod", label: "COD", icon: "rupee", span: 5, desc: "Experience Convenience and Trust with Our Cash on Delivery (COD) Payment Service" },
  { id: "nb", label: "Net Banking", icon: "bank", span: 7, desc: "Net banking, also known as online banking or internet banking, is a digital platform that allows customers to perform various financial transactions and manage their bank accounts through the internet." },
  { id: "upi", label: "UPI", icon: "upi", span: 5, desc: "UPI (Unified Payments Interface) is a real-time payment system that allows you to link multiple bank accounts to a single mobile application, enabling seamless and instant money transfers and payments." },
  { id: "partial", label: "Partial Payment", icon: "rupee", span: 7, desc: "You can partially pay for your order now and the remaining amount can be paid at the time of delivery." },
  { id: "card", label: "Credit / Debit cards", icon: "card", span: 12, desc: "Pay securely with your Credit or Debit card via our trusted payment gateway." },
];

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

