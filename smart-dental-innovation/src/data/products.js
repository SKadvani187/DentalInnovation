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
  mk("p-001", "Radio Frequency Advance Cautery, 2 Year Warranty", 21000, 19000, 4.7, 312, "unique", "ai_img_2_(3).png"),
  mk("p-002", "Electric Portable Micromotor LED", 18500, 15999, 4.6, 240, "handpiece", "micromotor"),
  mk("p-003", "Endomotor with Apex Locator", 24999, 21500, 4.8, 187, "endodontics", "endomotor"),
  mk("p-004", "Straight Long Handpiece NSK Style", 7800, 6499, 4.5, 142, "handpiece", "straighthp"),
  mk("p-005", "Composite Filling Kit Premium", 4500, 3299, 4.6, 421, "restorative", "composite"),
  mk("p-006", "Dental Mirror Set Anti-Fog 12pc", 1899, 1299, 4.4, 88, "mirrors", "mirror"),
  mk("p-007", "Implant Surgical Drill Kit", 32500, 28999, 4.7, 64, "implantology", "drill"),
  mk("p-008", "Carbide Bur Assorted Pack 100pc", 2200, 1499, 4.3, 256, "burs", "burs"),
  mk("p-009", "Smartmed Scrub Premium Cotton", 1499, 999, 4.5, 198, "scrub", "scrub"),
  mk("p-010", "Apex Locator Mini Digital", 8999, 6999, 4.6, 110, "endodontics", "apex"),
  mk("p-011", "Light Cure LED Wireless", 6500, 4999, 4.7, 273, "restorative", "lightcure"),
  mk("p-012", "Autoclave 18L Class B", 78000, 64999, 4.8, 41, "clinic-setup", "autoclave"),
];

export const newArrivals = [
  mk("n-001", "Bio-Active Glass Ionomer Cement", 3299, 2499, 4.5, 32, "restorative", "gic"),
  mk("n-002", "Titanium Implant Driver Set", 12999, 9999, 4.7, 18, "implantology", "implant"),
  mk("n-003", "Rotary Endo File NiTi 6pc", 1899, 1299, 4.6, 54, "endodontics", "endofile"),
  mk("n-004", "Diamond Bur Premium FG Pack", 1499, 999, 4.4, 71, "burs", "diamond"),
  mk("n-005", "Surgical LED Loupes 3.5x", 24999, 18999, 4.8, 22, "accessories", "loupes"),
  mk("n-006", "Ultrasonic Scaler Piezo Tip Set", 4999, 3499, 4.5, 95, "accessories", "scaler"),
  mk("n-007", "Disposable Dental Tray 50pc", 999, 699, 4.3, 142, "accessories", "tray"),
  mk("n-008", "Curing Light Premium Cordless", 7999, 5999, 4.7, 86, "restorative", "curing"),
  mk("n-009", "Surgical Suction Tip Pack", 599, 399, 4.4, 167, "accessories", "suction"),
  mk("n-010", "Orthodontic Bracket Set", 5999, 4499, 4.6, 31, "unique", "ortho"),
  mk("n-011", "Implant Healing Abutment 5pc", 4999, 3499, 4.7, 24, "implant-component", "abutment"),
  mk("n-012", "Anti-Microbial Surgical Mask 100pc", 899, 599, 4.5, 312, "accessories", "mask"),
];

export const implantology = [
  mk("i-001", "Dental Implant Internal Hex 4.0mm", 8999, 6999, 4.7, 87, "implantology", "imp1"),
  mk("i-002", "Implant Surgical Kit Premium Box", 39999, 32999, 4.8, 28, "implantology", "imp2"),
  mk("i-003", "Implant Cover Screw Pack 10pc", 2999, 1999, 4.5, 41, "implant-component", "imp3"),
  mk("i-004", "Implant Hex Driver Long", 1899, 1299, 4.6, 56, "implant-component", "imp4"),
];

export const handpieces = [
  mk("h-001", "High-Speed Air Turbine Handpiece", 5999, 4499, 4.6, 152, "handpiece", "hp1"),
  mk("h-002", "Low-Speed Contra Angle 1:1", 4999, 3699, 4.5, 98, "handpiece", "hp2"),
  mk("h-003", "Electric Micromotor Brushless", 22999, 18999, 4.8, 36, "handpiece", "hp3"),
  mk("h-004", "Surgical Handpiece 20:1", 18999, 14999, 4.7, 24, "handpiece", "hp4"),
  mk("h-005", "Implant Motor + Handpiece Set", 49999, 39999, 4.8, 19, "handpiece", "hp5"),
  mk("h-006", "Air Scaler Handpiece", 7999, 5999, 4.5, 67, "handpiece", "hp6"),
  mk("h-007", "Hygiene Polisher Handpiece", 4499, 3299, 4.4, 88, "handpiece", "hp7"),
  mk("h-008", "Endodontic Handpiece Reciprocating", 14999, 11999, 4.7, 41, "handpiece", "hp8"),
];

export const matrixSystem = [
  mk("m-001", "Sectional Matrix Band Pack 100pc", 1999, 1399, 4.5, 124, "restorative", "mx1"),
  mk("m-002", "Matrix Ring Premium NiTi", 4499, 3299, 4.7, 56, "restorative", "mx2"),
  mk("m-003", "Matrix Forceps Anatomical", 1899, 1299, 4.6, 38, "restorative", "mx3"),
  mk("m-004", "Wedge Plastic Assorted 200pc", 999, 699, 4.4, 178, "restorative", "mx4"),
  mk("m-005", "Tofflemire Matrix Retainer", 1499, 999, 4.5, 92, "restorative", "mx5"),
  mk("m-006", "Universal Matrix Band Roll", 599, 399, 4.3, 211, "restorative", "mx6"),
  mk("m-007", "Anatomical Sectional Matrix Kit", 3999, 2999, 4.6, 47, "restorative", "mx7"),
  mk("m-008", "Composite Placement Kit", 2999, 2199, 4.5, 81, "restorative", "mx8"),
];

export const endodontics = [
  mk("e-001", "Rotary File ProTaper Universal", 2999, 2199, 4.7, 142, "endodontics", "en1"),
  mk("e-002", "GP Points 6% Taper 60pc", 599, 399, 4.5, 318, "endodontics", "en2"),
  mk("e-003", "Endo Irrigation Syringe 30pc", 499, 349, 4.4, 156, "endodontics", "en3"),
  mk("e-004", "Apex Locator Mini Touch", 12999, 9999, 4.8, 41, "endodontics", "en4"),
  mk("e-005", "Hand File K Stainless 21mm", 399, 249, 4.3, 487, "endodontics", "en5"),
  mk("e-006", "Endo Sealer Bioceramic", 1499, 999, 4.7, 92, "endodontics", "en6"),
  mk("e-007", "Endo Activator Sonic", 4999, 3499, 4.6, 38, "endodontics", "en7"),
  mk("e-008", "Paper Points Pack Sterile", 299, 199, 4.4, 264, "endodontics", "en8"),
];

export const allProducts = [
  ...bestsellers,
  ...newArrivals,
  ...implantology,
  ...handpieces,
  ...matrixSystem,
  ...endodontics,
];

export const findProductById = (id) => allProducts.find((p) => p.id === id);
