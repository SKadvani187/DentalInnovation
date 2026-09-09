// Delivery-date formatting, shared so the product page and checkout never disagree about how a
// promised date reads. The APIs (api/v1/delivery.php, api/v1/shipping_quote.php) both return the
// ETA as a plain "Y-m-d" string.

/** "2026-09-18" -> "Fri, 18 Sep". Returns the input unchanged if it isn't a parseable date. */
export const fmtEta = (iso) => {
  if (!iso) return "";
  // Append the time so the string is parsed in local time, not UTC — without it a "Y-m-d" date
  // shifts a day backwards for anyone east of Greenwich, India included.
  const d = new Date(`${iso}T00:00:00`);
  return Number.isNaN(d.getTime())
    ? iso
    : d.toLocaleDateString("en-IN", { weekday: "short", day: "numeric", month: "short" });
};
