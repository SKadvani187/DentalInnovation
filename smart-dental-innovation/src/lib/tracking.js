// Build a "track your shipment" URL from a courier name + AWB/tracking number.
//
// We don't integrate with courier APIs (yet) — this just deep-links the customer to the
// carrier's own public tracking page for the AWB the admin entered. Each carrier exposes a
// URL that pre-fills the tracking number; where one isn't known we fall back to Shiprocket's
// universal tracking page, which resolves most Indian AWBs regardless of carrier.

// Match on a normalized (lowercase, spaces/dots/hyphens stripped) courier name so
// "Blue Dart", "bluedart" and "BLUE-DART" all map to the same builder.
const CARRIERS = [
  { match: ["bluedart", "bdart"], url: (awb) => `https://www.bluedart.com/tracking?trackingNo=${awb}` },
  { match: ["delhivery"], url: (awb) => `https://www.delhivery.com/track/package/${awb}` },
  { match: ["dtdc"], url: (awb) => `https://www.dtdc.in/tracking.asp?strCnno=${awb}` },
  { match: ["xpressbees"], url: (awb) => `https://www.xpressbees.com/track?awb=${awb}` },
  { match: ["ekart"], url: (awb) => `https://ekartlogistics.com/shipmenttrack/${awb}` },
  { match: ["ecomexpress", "ecom"], url: (awb) => `https://ecomexpress.in/tracking/?awb_field=${awb}` },
  { match: ["fedex"], url: (awb) => `https://www.fedex.com/fedextrack/?trknbr=${awb}` },
  { match: ["dhl"], url: (awb) => `https://www.dhl.com/in-en/home/tracking.html?tracking-id=${awb}` },
  { match: ["indiapost", "speedpost", "postoffice"], url: (awb) => `https://www.indiapost.gov.in/_layouts/15/DOP.Portal.Tracking/TrackConsignment.aspx?ID=${awb}` },
  { match: ["shiprocket"], url: (awb) => `https://shiprocket.co/tracking/${awb}` },
];

const normalize = (s) => String(s || "").toLowerCase().replace(/[\s._-]+/g, "");

/**
 * @param {string} courier carrier name as entered by admin (e.g. "Blue Dart")
 * @param {string} awb tracking / AWB number
 * @returns {string|null} a public tracking URL, or null when there's no AWB to track
 */
export function courierTrackingUrl(courier, awb) {
  const id = String(awb || "").trim();
  if (!id) return null;
  const key = normalize(courier);
  const carrier = CARRIERS.find((c) => c.match.some((m) => key.includes(m)));
  // Unknown/blank courier → Shiprocket's universal tracker resolves most Indian AWBs.
  return carrier ? carrier.url(encodeURIComponent(id)) : `https://shiprocket.co/tracking/${encodeURIComponent(id)}`;
}
