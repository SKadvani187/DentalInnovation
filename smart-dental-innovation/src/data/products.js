const placeholder = (seed) =>
  `https://merchant-cdn.storedum.com/${seed}`;

const mk = (id, name, mrp, price, rating, reviews, category, seed) => ({
  id,
  name,
  image: placeholder(seed),
  mrp,
  price,
  discount: Math.round(((mrp - price) / mrp) * 100),
  rating,
  reviews,
  category,
  description:
    "High-quality dental product engineered for clinical precision, reliability, and consistently better patient outcomes. Made for modern dental practices.",
  variants: ["Standard", "Pro", "Bundle"],
});

export const bestsellers = [
  mk("p-001", "Radio Frequency Advance Cautery, 2 Year Warranty", 21000, 19000, 4.7, 312, "unique", "ai_img_(1).webp"),
  mk("p-002", "Electric Portable Micromotor LED", 18500, 15999, 4.6, 240, "handpiece", "ai_img_1_(2).png"),
  mk("p-003", "Endomotor with Apex Locator", 24999, 21500, 4.8, 187, "endodontics", "ai_img_2_(3).png"),
  mk("p-004", "Straight Long Handpiece NSK Style", 7800, 6499, 4.5, 142, "handpiece", "ai_img_5_(2).png"),
  mk("p-005", "Composite Filling Kit Premium", 4500, 3299, 4.6, 421, "restorative", "WhatsApp_Image_2026-03-07_at_12.34.31_PM.jpeg"),
  mk("p-006", "Dental Mirror Set Anti-Fog 12pc", 1899, 1299, 4.4, 88, "mirrors", "dq3oxgejdhsf5sv5ym37_(7).webp"),
  mk("p-007", "Implant Surgical Drill Kit", 32500, 28999, 4.7, 64, "implantology", "ai_img_6_(1).png"),
  mk("p-008", "Carbide Bur Assorted Pack 100pc", 2200, 1499, 4.3, 256, "burs", "ai_img_9_(1).png"),
  mk("p-009", "Smartmed Scrub Premium Cotton", 1499, 999, 4.5, 198, "scrub", "ai_img_40_(5).png"),
  mk("p-010", "Apex Locator Mini Digital", 8999, 6999, 4.6, 110, "endodontics", "dentoscope-10-mm-1000x1000_(4).webpv1759486811width1946"),
  mk("p-011", "Light Cure LED Wireless", 6500, 4999, 4.7, 273, "restorative", "ai_img_42_(1).png"),
  mk("p-012", "Autoclave 18L Class B", 78000, 64999, 4.8, 41, "clinic-setup", "ai_img_35_(2).png"),
];

export const newArrivals = [
  mk("n-001", "Bio-Active Glass Ionomer Cement", 3299, 2499, 4.5, 32, "restorative", "ai_img_39_(1).png"),
  mk("n-002", "Titanium Implant Driver Set", 12999, 9999, 4.7, 18, "implantology", "ai_img_22_(1).png"),
  mk("n-003", "Rotary Endo File NiTi 6pc", 1899, 1299, 4.6, 54, "endodontics", "ai_img_(2).png"),
  mk("n-004", "Diamond Bur Premium FG Pack", 1499, 999, 4.4, 71, "burs", "ai_img_10_(1).png"),
  mk("n-005", "Surgical LED Loupes 3.5x", 24999, 18999, 4.8, 22, "accessories", "ai_img_30_(1).png"),
  mk("n-006", "Ultrasonic Scaler Piezo Tip Set", 4999, 3499, 4.5, 95, "accessories", "ai_img_31_(2).png"),
  mk("n-007", "Disposable Dental Tray 50pc", 999, 699, 4.3, 142, "accessories", "ai_img_32_(2).png"),
  mk("n-008", "Curing Light Premium Cordless", 7999, 5999, 4.7, 86, "restorative", "ai_img_33_(1).png"),
  mk("n-009", "Surgical Suction Tip Pack", 599, 399, 4.4, 167, "accessories", "ai_img_23_(1).png"),
  mk("n-010", "Orthodontic Bracket Set", 5999, 4499, 4.6, 31, "unique", "ai_img_27_(1).png"),
  mk("n-011", "Implant Healing Abutment 5pc", 4999, 3499, 4.7, 24, "implant-component", "ai_img_44.png"),
  mk("n-012", "Anti-Microbial Surgical Mask 100pc", 899, 599, 4.5, 312, "accessories", "ai_img_26_(1).png"),
];

export const implantology = [
  mk("i-001", "Dental Implant Internal Hex 4.0mm", 8999, 6999, 4.7, 87, "implantology", "smarthexdriverkit-02_(4).jpgv1748423646width1946"),
  mk("i-002", "Implant Surgical Kit Premium Box", 39999, 32999, 4.8, 28, "implantology", "plain_image_2_6_(1).png"),
  mk("i-003", "Implant Cover Screw Pack 10pc", 2999, 1999, 4.5, 41, "implant-component", "ChatGPT_Image_Apr_20_2026_11_18_56_AM.png"),
  mk("i-004", "Implant Hex Driver Long", 1899, 1299, 4.6, 56, "implant-component", "ai_img_7_(4).png"),
];

export const handpieces = [
  mk("h-001", "High-Speed Air Turbine Handpiece", 5999, 4499, 4.6, 152, "handpiece", "47_(8).png"),
  mk("h-002", "Low-Speed Contra Angle 1:1", 4999, 3699, 4.5, 98, "handpiece", "plain_image_2_23_(1).png"),
  mk("h-003", "Electric Micromotor Brushless", 22999, 18999, 4.8, 36, "handpiece", "Screenshot_2026-03-19_112934_(1).png"),
  mk("h-004", "Surgical Handpiece 20:1", 18999, 14999, 4.7, 24, "handpiece", "e98d8f04-103a-45c7-8604-f95001eb6cb5_(1).png"),
  mk("h-005", "Implant Motor + Handpiece Set", 49999, 39999, 4.8, 19, "handpiece", "ChatGPT_Image_Feb_6_2026_04_59_03_PM.png"),
  mk("h-006", "Air Scaler Handpiece", 7999, 5999, 4.5, 67, "handpiece", "plain_image_2_40_(1).png"),
  mk("h-007", "Hygiene Polisher Handpiece", 4499, 3299, 4.4, 88, "handpiece", "ChatGPT_Image_Feb_6_2026_04_29_14_PM.png"),
  mk("h-008", "Endodontic Handpiece Reciprocating", 14999, 11999, 4.7, 41, "handpiece", "84_(1).png"),
];

export const matrixSystem = [
  mk("m-001", "Sectional Matrix Band Pack 100pc", 1999, 1399, 4.5, 124, "restorative", "plain_image_2.png"),
  mk("m-002", "Matrix Ring Premium NiTi", 4499, 3299, 4.7, 56, "restorative", "1330_1_(4).webpv1744436024width1946"),
  mk("m-003", "Matrix Forceps Anatomical", 1899, 1299, 4.6, 38, "restorative", "ai_img_16_(1).png"),
  mk("m-004", "Wedge Plastic Assorted 200pc", 999, 699, 4.4, 178, "restorative", "automatic-wooden-wedges-500x500_(4).webpv1739864668width1946"),
  mk("m-005", "Tofflemire Matrix Retainer", 1499, 999, 4.5, 92, "restorative", "ChatGPT_Image_Mar_12_2026_06_14_55_PM.png"),
  mk("m-006", "Universal Matrix Band Roll", 599, 399, 4.3, 211, "restorative", "download-500x500_(4).webpv1739853341width1946"),
  mk("m-007", "Anatomical Sectional Matrix Kit", 3999, 2999, 4.6, 47, "restorative", "download-25-500x500_(4).webpv1739856285width1946"),
  mk("m-008", "Composite Placement Kit", 2999, 2199, 4.5, 81, "restorative", "plain_image_2_32_(1).png"),
];

export const endodontics = [
  mk("e-001", "Rotary File ProTaper Universal", 2999, 2199, 4.7, 142, "endodontics", "plain_image_2_53_(1).png"),
  mk("e-002", "GP Points 6% Taper 60pc", 599, 399, 4.5, 318, "endodontics", "plain_images_68_(1).png"),
  mk("e-003", "Endo Irrigation Syringe 30pc", 499, 349, 4.4, 156, "endodontics", "154.png"),
  mk("e-004", "Apex Locator Mini Touch", 12999, 9999, 4.8, 41, "endodontics", "Copy_of_plain_image_2_30_(1).png"),
  mk("e-005", "Hand File K Stainless 21mm", 399, 249, 4.3, 487, "endodontics", "19.2_21mm_(1).png"),
  mk("e-006", "Endo Sealer Bioceramic", 1499, 999, 4.7, 92, "endodontics", "274_(2).png"),
  mk("e-007", "Endo Activator Sonic", 4999, 3499, 4.6, 38, "endodontics", "FB2-03_(4).jpgv1750241250width1946"),
  mk("e-008", "Paper Points Pack Sterile", 299, 199, 4.4, 264, "endodontics", "WhatsAppImage2025-11-20at12.59.57PM_(1).jpgv1763623816width1946"),
];

export const premiumCategories=[
  {
    title: "Electric Portable Micromotor",
    description: "The Electric Portable Micromotor is a compact, pen-style rotary device with 3-speed power control, designed for efficient sanding, polishing, drilling, cutting, carving, and grinding with low heat generation and smooth performance.",
    imgSrc: "https://d2ypw3u7ezpmac.cloudfront.net/1_2_-removebg-preview.png",
  },
  {
    title: "Endomotor",
    description: "The Endo Motor by Smart Dental Innovations is a compact, rechargeable motor engineered for efficient and safe root canal procedures. With auto-reverse & auto-forward functions, bright LED illumination, and compatibility with 16:1 contra-angle handpieces, it ensures smooth instrumentation, reduced file separation, and enhanced clinical control during RCT.",
    imgSrc: "https://d2ypw3u7ezpmac.cloudfront.net/1766209325816-removebg-preview.png",
  },
  {
    title: "Straight Long Handpiece",
    description: "A high-performance surgical straight long handpiece designed for implantology and advanced surgical procedures, delivering smooth rotation, firm bur holding, and exceptional control.",
    imgSrc: "https://d2ypw3u7ezpmac.cloudfront.net/plain_images_19_1.png",
  }
]

export const allProducts = [
  ...bestsellers,
  ...newArrivals,
  ...implantology,
  ...handpieces,
  ...matrixSystem,
  ...endodontics,
  ...premiumCategories
];

export const findProductById = (id) => allProducts.find((p) => p.id === id);
