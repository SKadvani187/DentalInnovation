const img = (seed) => `https://merchant-cdn.storedum.com/${seed}`;

const mkCombo = (id, name, mrp, price, seed) => ({
  id,
  name,
  image: img(seed),
  mrp,
  price,
  discount: Math.round(((mrp - price) / mrp) * 100),
  category: "combo",
  inStock: true,
  description: "Carefully curated combo pack for dental clinics — better value than individual items.",
  variants: [],
});

export const combos = [
  mkCombo("c-001", "Trial Pack Combo", 2100, 1199, "plain_images_19_1.png"),
  mkCombo("c-002", "Implant S Pro + Smart Hex Driver Kit", 72000, 69990, "ai_img_(1).webp"),
  mkCombo("c-003", "Implant s lite + Implant handpiece", 40000, 39480, "ai_img_1_(2).png"),
  mkCombo("c-004", "Smart Pack + GP Point, 1 GP Point Free", 9000, 7990, "plain_images_68_(1).png"),
  mkCombo("c-005", "Endo Master Combo: Motor + Apex Locator", 35000, 28999, "ai_img_2_(3).png"),
  mkCombo("c-006", "Restorative Starter Combo Pack", 8500, 5499, "ai_img_42_(1).png"),
  mkCombo("c-007", "Implant Surgery Combo Box", 89999, 74999, "ai_img_6_(1).png"),
  mkCombo("c-008", "Composite Filling Combo, 5 Shades", 6500, 4299, "WhatsApp_Image_2026-03-07_at_12.34.31_PM.jpeg"),
  mkCombo("c-009", "Handpiece Duo: High + Low Speed", 11500, 8499, "47_(8).png"),
  mkCombo("c-010", "Bur Mega Combo, 200pc Assorted", 4500, 2799, "ai_img_9_(1).png"),
  mkCombo("c-011", "Scrub Trio Combo, Premium Cotton", 4499, 2899, "ai_img_40_(5).png"),
  mkCombo("c-012", "Clinic Setup Essentials Combo", 95000, 79999, "ai_img_35_(2).png"),
];
