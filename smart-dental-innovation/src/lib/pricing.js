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

// Matching quantity-discount tier for one line (or null if none applies).
// Tiers are the primary engine: pick the highest tier whose minQty <= qty.
// When NO tiers are configured, fall back to the legacy single-step bulk rule
// (kept dormant in the DB), synthesized into a tier-shaped object.
// Mirrors tierRateForQty() in dentinno/api/v1/_pricing.php.
export function tierFor(qty, tiers, bulkRule) {
  const list = Array.isArray(tiers) ? tiers : [];
  if (list.length) {
    return (
      list
        .filter((t) => qty >= (Number(t.minQty) || 0))
        .sort((a, b) => (Number(b.minQty) || 0) - (Number(a.minQty) || 0))[0] || null
    );
  }
  // Dormant fallback: legacy single bulk rule (no label).
  if (bulkRule && qty >= (Number(bulkRule.minQty) || 0) && (Number(bulkRule.rate) || 0) > 0) {
    return { minQty: Number(bulkRule.minQty) || 0, rate: Number(bulkRule.rate) || 0, label: "" };
  }
  return null;
}

// Convenience: just the rate for a line (0 when no tier applies).
export function tierRateFor(qty, tiers, bulkRule) {
  const t = tierFor(qty, tiers, bulkRule);
  return t ? (Number(t.rate) || 0) : 0;
}

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
// opts: { tierOffers:[{minQty,rate}], bulkRule:{minQty,rate}, shipping:{freeThreshold,flatRate}, tax:{enabled,rate,inclusive}, coupon }
export function computeCartPricing(items, opts = {}) {
  // Per-field fallbacks: an empty/partial config object (e.g. DB row missing) must
  // not produce NaN totals, so we default each field rather than the whole object.
  const DEF_BULK = { minQty: 2, rate: 0.1 };
  const DEF_SHIP = { freeThreshold: 20000, flatRate: 600 };
  const DEF_TAX  = { enabled: false, rate: 0, inclusive: true };
  const ob = opts.bulkRule || {}, os = opts.shipping || {}, ot = opts.tax || {};
  const bulkRule = {
    minQty: Number.isFinite(+ob.minQty) ? +ob.minQty : DEF_BULK.minQty,
    rate:   Number.isFinite(+ob.rate)   ? +ob.rate   : DEF_BULK.rate,
  };
  const shipping = {
    freeThreshold: Number.isFinite(+os.freeThreshold) ? +os.freeThreshold : DEF_SHIP.freeThreshold,
    flatRate:      Number.isFinite(+os.flatRate)      ? +os.flatRate      : DEF_SHIP.flatRate,
  };
  const tax = {
    enabled:   typeof ot.enabled === "boolean" ? ot.enabled : DEF_TAX.enabled,
    rate:      Number.isFinite(+ot.rate) ? +ot.rate : DEF_TAX.rate,
    inclusive: typeof ot.inclusive === "boolean" ? ot.inclusive : DEF_TAX.inclusive,
  };
  const tiers = opts.tierOffers;
  const coupon = opts.coupon || null;

  // Free-gift lines (price 0) are excluded from MRP/"you saved" so their value
  // doesn't masquerade as a discount.
  const nonGift = items.filter((i) => i.type !== "gift");

  const mrpTotal = r2(nonGift.reduce((s, i) => s + (i.mrp || i.price) * i.qty, 0));
  const subtotal = r2(items.reduce((s, i) => s + i.price * i.qty, 0));

  // Quantity discount: tier table first, single bulk rule as dormant fallback.
  // Field name kept as `bulkSavings` so existing consumers don't break.
  const bulkSavings = r2(
    nonGift.reduce((s, i) => {
      const rate = tierRateFor(i.qty, tiers, bulkRule);
      return rate > 0 ? s + i.price * rate * i.qty : s;
    }, 0)
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
