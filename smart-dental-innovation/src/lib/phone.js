// Shared phone helpers for storefront forms — Indian mobile only (same rule as login/OTP).
// Indian mobiles are 10 digits starting 6–9. Input may include +91 / 0 / spaces / dashes;
// we strip those and validate the final 10-digit number.

// Strip everything down to the 10-digit Indian mobile: removes +91 country code,
// a leading 0, spaces, dashes, parens. "+91 98765-43210" / "098765 43210" -> "9876543210".
export function cleanPhone(input) {
  let d = String(input || "").replace(/\D/g, "");
  if (d.length > 10 && d.startsWith("91")) d = d.slice(2);  // drop +91
  if (d.length === 11 && d.startsWith("0")) d = d.slice(1); // drop leading 0
  return d.slice(-10);                                        // keep last 10
}

// Valid Indian mobile: exactly 10 digits, starts 6–9.
export function isValidPhone(input) {
  return /^[6-9]\d{9}$/.test(cleanPhone(input));
}
