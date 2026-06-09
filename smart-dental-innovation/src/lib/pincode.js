// India Post public pincode lookup — used to autofill City/State/District in the
// add-address form. Public, no API key. delivery.php remains the source of truth for
// serviceability/COD/ETA; this only fills in the geography fields as a convenience.
//
// Never blocks the flow: any failure (network down, unknown pincode, bad shape)
// resolves to null and the form fields stay editable.

const ENDPOINT = "https://api.postalpincode.in/pincode";

// In-memory cache so re-entering the same pincode doesn't re-hit the API.
const cache = new Map();

// Deterministic state lookup by the first TWO digits of the PIN (India's postal
// "circle"/region scheme). No network — this is the reliable fallback when the India
// Post API is unreachable (it 403s from some networks). State is always derivable here;
// only the city/district needs the API.
const STATE_BY_PREFIX2 = {
  11: "Delhi",
  12: "Haryana", 13: "Haryana",
  14: "Punjab", 15: "Punjab", 16: "Punjab",
  17: "Himachal Pradesh",
  18: "Jammu & Kashmir", 19: "Jammu & Kashmir",
  20: "Uttar Pradesh", 21: "Uttar Pradesh", 22: "Uttar Pradesh", 23: "Uttar Pradesh",
  24: "Uttar Pradesh", 25: "Uttar Pradesh", 26: "Uttar Pradesh", 27: "Uttar Pradesh", 28: "Uttar Pradesh",
  // 24x also covers Uttarakhand (24x) — kept as UP/UK; user can edit.
  30: "Rajasthan", 31: "Rajasthan", 32: "Rajasthan", 33: "Rajasthan", 34: "Rajasthan",
  36: "Gujarat", 37: "Gujarat", 38: "Gujarat", 39: "Gujarat",
  40: "Maharashtra", 41: "Maharashtra", 42: "Maharashtra", 43: "Maharashtra", 44: "Maharashtra",
  45: "Madhya Pradesh", 46: "Madhya Pradesh", 47: "Madhya Pradesh", 48: "Madhya Pradesh",
  49: "Chhattisgarh",
  50: "Telangana", 51: "Andhra Pradesh", 52: "Andhra Pradesh", 53: "Andhra Pradesh",
  56: "Karnataka", 57: "Karnataka", 58: "Karnataka", 59: "Karnataka",
  60: "Tamil Nadu", 61: "Tamil Nadu", 62: "Tamil Nadu", 63: "Tamil Nadu", 64: "Tamil Nadu",
  67: "Kerala", 68: "Kerala", 69: "Kerala",
  70: "West Bengal", 71: "West Bengal", 72: "West Bengal", 73: "West Bengal", 74: "West Bengal",
  75: "Odisha", 76: "Odisha", 77: "Odisha",
  78: "Assam", 79: "North East",
  80: "Bihar", 81: "Bihar", 82: "Bihar", 83: "Bihar", 84: "Bihar", 85: "Bihar",
  82: "Jharkhand", 83: "Jharkhand", // overlap; Bihar/Jharkhand share 8xx — editable
  90: "Army Post (APO)", 91: "Army Post (APO)",
};

const STATE_BY_PREFIX1 = {
  1: "North India", 2: "Uttar Pradesh", 3: "Rajasthan", 4: "Maharashtra",
  5: "South India", 6: "Tamil Nadu", 7: "West Bengal", 8: "Bihar",
};

/**
 * Derive the state from a pincode with no network call. Returns "" if unknown.
 * @param {string} pin
 * @returns {string}
 */
export function stateFromPincode(pin) {
  const code = String(pin || "").replace(/\D/g, "");
  if (code.length < 1) return "";
  const p2 = Number(code.slice(0, 2));
  if (STATE_BY_PREFIX2[p2]) return STATE_BY_PREFIX2[p2];
  return STATE_BY_PREFIX1[Number(code[0])] || "";
}

/**
 * @param {string} pin 6-digit Indian pincode
 * @returns {Promise<{city:string,state:string,district:string}|null>}
 */
export async function lookupPincode(pin) {
  const code = String(pin || "").replace(/\D/g, "");
  if (code.length !== 6) return null;
  if (cache.has(code)) return cache.get(code);

  // Always have a state from the local map; the API only enriches city/district.
  const fallback = { city: "", state: stateFromPincode(code), district: "" };

  try {
    const res = await fetch(`${ENDPOINT}/${code}`, { headers: { Accept: "application/json" } });
    if (!res.ok) { cache.set(code, fallback); return fallback; }
    const json = await res.json();
    // Shape: [ { Status: "Success", PostOffice: [ { District, State, Block, Name, ... } ] } ]
    const entry = Array.isArray(json) ? json[0] : null;
    const offices = entry && entry.Status === "Success" && Array.isArray(entry.PostOffice) ? entry.PostOffice : [];
    if (offices.length === 0) { cache.set(code, fallback); return fallback; }
    // India Post gives District + State (no "City" field). Some PostOffice rows have a
    // blank District, so scan for the first non-empty one before falling back to
    // Block/Division/Name — this keeps City from coming back empty for valid pincodes.
    const po =
      offices.find((o) => o.District) ||
      offices.find((o) => o.Block || o.Division) ||
      offices[0];
    const city = po.District || po.Block || po.Division || po.Name || "";
    // Prefer the API's state; fall back to the local map so State is never empty.
    const state = (offices.find((o) => o.State) || po).State || fallback.state;
    const out = { city, state, district: po.District || "" };
    cache.set(code, out);
    return out;
  } catch {
    cache.set(code, fallback);
    return fallback; // offline / blocked — at least the state is filled from the local map
  }
}

/**
 * Resolve the user's current pincode via browser geolocation + reverse geocoding.
 * Uses OpenStreetMap Nominatim (public, no key) to turn lat/lng into a postcode.
 * Rejects if permission is denied, geolocation is unavailable, or no postcode found.
 * @returns {Promise<{pincode:string, city:string, state:string}>}
 */
export function detectCurrentPincode() {
  return new Promise((resolve, reject) => {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      reject(new Error("Location not supported on this device."));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      async ({ coords }) => {
        try {
          const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${coords.latitude}&lon=${coords.longitude}`;
          const res = await fetch(url, { headers: { Accept: "application/json" } });
          if (!res.ok) throw new Error("reverse geocode failed");
          const json = await res.json();
          const a = json.address || {};
          const pincode = String(a.postcode || "").replace(/\D/g, "").slice(0, 6);
          if (pincode.length !== 6) throw new Error("Couldn't determine your pincode.");
          resolve({ pincode, city: a.city || a.town || a.village || a.county || "", state: a.state || "" });
        } catch (e) {
          reject(new Error(e.message || "Couldn't determine your location."));
        }
      },
      () => reject(new Error("Location permission denied.")),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  });
}

// A locality value that's clearly not a real place name: no vowels, all one repeated
// run, or random consonant soup. Cheap offline guard so obvious gibberish ("ADDJLKJFJ")
// is rejected even when the geocoder is unreachable.
function looksLikeGibberish(s) {
  const t = String(s || "").trim();
  if (t.length < 3) return true;
  const letters = t.replace(/[^a-z]/gi, "");
  if (letters.length < 3) return false; // mostly numbers/symbols — let geocoder decide
  const vowels = (letters.match(/[aeiou]/gi) || []).length;
  // Real Indian locality names almost always have ≥1 vowel per ~4 letters.
  if (vowels / letters.length < 0.15) return true;
  // Long unbroken consonant run = not a word.
  if (/[bcdfghjklmnpqrstvwxyz]{6,}/i.test(letters)) return true;
  return false;
}

/**
 * Validate that the typed address resolves to a real locality/street — mirrors the
 * reference site's "Address lacks locality, street, or neighborhood details" check.
 * Forward-geocodes "area, city, pincode" via Nominatim and requires the match to carry
 * a road / suburb / neighbourhood / residential component (not just a city centroid).
 *
 * Fails OPEN on network/geocoder errors (returns {ok:true}) so a blocked API never
 * traps a legitimate address — but still rejects locally-detectable gibberish.
 *
 * @returns {Promise<{ok:boolean, reason?:string}>}
 */
export async function validateAddressLocality({ area, city, pincode }) {
  const locality = String(area || "").trim();
  if (!locality) return { ok: false, reason: "Address lacks locality, street, or neighborhood details, making it undeliverable." };
  if (looksLikeGibberish(locality)) {
    return { ok: false, reason: "Address lacks locality, street, or neighborhood details, making it undeliverable." };
  }

  try {
    const q = encodeURIComponent([locality, city, pincode, "India"].filter(Boolean).join(", "));
    const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=1&countrycodes=in&q=${q}`;
    const res = await fetch(url, { headers: { Accept: "application/json" } });
    if (!res.ok) return { ok: true }; // geocoder unavailable — don't block
    const arr = await res.json();
    if (!Array.isArray(arr) || arr.length === 0) {
      return { ok: false, reason: "Address lacks locality, street, or neighborhood details, making it undeliverable." };
    }
    const a = arr[0].address || {};
    const hasLocality = !!(a.road || a.suburb || a.neighbourhood || a.residential || a.hamlet || a.quarter || a.city_district);
    return hasLocality ? { ok: true } : { ok: false, reason: "Address lacks locality, street, or neighborhood details, making it undeliverable." };
  } catch {
    return { ok: true }; // network error — fail open
  }
}
