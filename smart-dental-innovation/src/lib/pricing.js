// Shared money math for the cart/checkout.
// IMPORTANT: this MUST mirror the server-authoritative logic in
// dentinno/api/v1/_pricing.php (computeOrderTotals) so the number shown in the cart,
// the number shown at checkout, and the number the server persists all agree.

// Round to 2 decimals (paise) — avoids float accumulation drift.
export const r2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

// Safe discount percentage (guards divide-by-zero / bad MRP). Returns an integer %.
export const discountPct = (mrp, price) => {
  const m = Number(mrp) || 0;
  const p = Number(price) || 0;
  if (m <= 0 || p >= m) return 0;
  return Math.round(((m - p) / m) * 100);
};

// Coupon discount for a given subtotal. CAP first, THEN round (matches the server).
// Accepts the cart's applied-coupon shape: { minSubtotal?, discount:{type,value,max?}, serverDiscount? }.
export function couponDiscountFor(coupon, subtotal) {
  if (!coupon) return 0;
  if (subtotal < (coupon.minSubtotal || 0)) return 0;
  // An exact server-validated amount (from api.validateCoupon) wins.
  if (typeof coupon.serverDiscount === "number") return r2(coupon.serverDiscount);
  const d = coupon.discount;
  if (!d) return 0;
  let raw;
  if (d.type === "flat") raw = d.value;
  else if (d.type === "percent") {
    raw = subtotal * (d.value / 100);
    if (d.max) raw = Math.min(raw, d.max);
  } else return 0;
  return r2(Math.min(raw, subtotal));
}

// Compute the full cart price breakdown.
// opts: { bulkRule:{minQty,rate}, shipping:{freeThreshold,flatRate}, tax:{enabled,rate,inclusive}, coupon }
export function computeCartPricing(items, opts = {}) {
  const bulkRule = opts.bulkRule || { minQty: 2, rate: 0.1 };
  const shipping = opts.shipping || { freeThreshold: 20000, flatRate: 600 };
  const tax = opts.tax || { enabled: false, rate: 0, inclusive: true };
  const coupon = opts.coupon || null;

  // Free-gift lines (price 0) are excluded from MRP/"you saved" so their value
  // doesn't masquerade as a discount.
  const nonGift = items.filter((i) => i.type !== "gift");

  const mrpTotal = r2(nonGift.reduce((s, i) => s + (i.mrp || i.price) * i.qty, 0));
  const subtotal = r2(items.reduce((s, i) => s + i.price * i.qty, 0));

  const bulkSavings = r2(
    nonGift.reduce((s, i) => (i.qty >= bulkRule.minQty ? s + i.price * bulkRule.rate * i.qty : s), 0)
  );
  const couponDiscount = couponDiscountFor(coupon, subtotal);
  const discount = r2(bulkSavings + couponDiscount);
  const afterDiscount = Math.max(0, r2(subtotal - discount));

  const deliveryCharges =
    items.length === 0 || subtotal >= shipping.freeThreshold ? 0 : shipping.flatRate;

  const taxAmount = tax.enabled && !tax.inclusive ? r2(afterDiscount * (tax.rate / 100)) : 0;

  const finalTotal = Math.max(0, r2(afterDiscount + deliveryCharges + taxAmount));
  const totalSaved = Math.max(0, r2(mrpTotal - afterDiscount));

  return {
    mrpTotal,
    subtotal,
    bulkSavings,
    couponDiscount,
    discount,
    deliveryCharges,
    tax: taxAmount,
    finalTotal,
    totalSaved,
  };
}
