const img = (seed) => `https://merchant-cdn.storedum.com/${seed}`;

export const events = [
  {
    id: "ev-001",
    name: "The Complete Exodontia Mastery Program",
    type: "Course",
    brand: "Smart Dental Innovations",
    breadcrumb: ["Home", "Products", "Implantology"],
    extraCategories: ["Implantology", "Orthodontics", "General Dentistry", "Endodontics"],
    rating: 0,
    reviews: 0,
    mrp: 19980,
    price: 3000,
    image: img("ai_img_6_(1).png"),
    description:
      "This masterclass is designed to simplify extractions and boost your clinical confidence — with practical, real-world techniques for routine, surgical, and implant-friendly cases.",
  },
  {
    id: "ev-002",
    name: "Advanced Implantology Hands-On Workshop",
    type: "Course",
    brand: "Smart Dental Innovations",
    breadcrumb: ["Home", "Products", "Implantology"],
    extraCategories: ["Implantology", "Oral Surgery", "Periodontology"],
    rating: 4.8,
    reviews: 24,
    mrp: 29999,
    price: 9999,
    image: img("ai_img_22_(1).png"),
    description:
      "Live cadaveric and model-based hands-on training in implant placement, sinus lift, and bone grafting. Faculty-led by top oral surgeons.",
  },
  {
    id: "ev-003",
    name: "RCT Endodontic Excellence Live Course",
    type: "Course",
    brand: "Smart Dental Innovations",
    breadcrumb: ["Home", "Products", "Endodontics"],
    extraCategories: ["Endodontics", "Restorative"],
    rating: 4.7,
    reviews: 41,
    mrp: 14999,
    price: 4999,
    image: img("plain_image_2_53_(1).png"),
    description:
      "Step-by-step rotary endodontic protocols, apex location, irrigation, and obturation. Real case demos with troubleshooting tips.",
  },
];
