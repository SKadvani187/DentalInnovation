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
  // 80-81 Bihar, 82-83 Jharkhand, 84-85 Bihar (8xx is shared; closest mapping, user can edit).
  80: "Bihar", 81: "Bihar", 82: "Jharkhand", 83: "Jharkhand", 84: "Bihar", 85: "Bihar",
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

// Derive the CITY from a pincode with no network call (mirrors stateFromPincode). Keyed by the
// 3-digit prefix (a postal "sorting district" usually = one city). Covers the regions we serve
// (Gujarat) + major metros; the India Post API still refines this when reachable.
const CITY_BY_PREFIX3 = {
  360: "Rajkot", 361: "Jamnagar", 362: "Junagadh", 363: "Surendranagar", 364: "Bhavnagar", 365: "Amreli",
  370: "Bhuj", 380: "Ahmedabad", 382: "Gandhinagar", 384: "Mehsana", 385: "Palanpur", 387: "Nadiad",
  388: "Anand", 389: "Godhra", 390: "Vadodara", 391: "Vadodara", 392: "Bharuch", 393: "Bharuch",
  394: "Surat", 395: "Surat", 396: "Valsad",
  110: "New Delhi", 400: "Mumbai", 411: "Pune", 560: "Bengaluru", 600: "Chennai",
  700: "Kolkata", 500: "Hyderabad", 302: "Jaipur",
};

/**
 * Derive the city from a pincode with no network call. Returns "" if unknown.
 * @param {string} pin
 * @returns {string}
 */
export function cityFromPincode(pin) {
  const code = String(pin || "").replace(/\D/g, "");
  if (code.length < 3) return "";
  return CITY_BY_PREFIX3[Number(code.slice(0, 3))] || "";
}

/**
 * Look up city / state / district + the official locality list for a pincode.
 * `areas` are the India Post post-office names for the pincode — used to populate the
 * Area dropdown so the user PICKS a real locality instead of free-typing one (this is what
 * prevents fake/non-existent localities; free-text geocode validation is unreliable in India).
 * @param {string} pin 6-digit Indian pincode
 * @returns {Promise<{city:string,state:string,district:string,areas:string[]}|null>}
 */
export async function lookupPincode(pin) {
  const code = String(pin || "").replace(/\D/g, "");
  if (code.length !== 6) return null;
  if (cache.has(code)) return cache.get(code);

  // Always have City + State from the local maps (no network); the India Post API only
  // refines them + adds the locality list when reachable.
  const fallback = { city: cityFromPincode(code), state: stateFromPincode(code), district: cityFromPincode(code), areas: [] };

  try {
    // 6s timeout so a hung request can't freeze the address form.
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 6000);
    const res = await fetch(`${ENDPOINT}/${code}`, { headers: { Accept: "application/json" }, signal: ctrl.signal });
    clearTimeout(timer);
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
    // Prefer the API's city/state; fall back to the local maps so neither is ever empty.
    const city = po.District || po.Block || po.Division || po.Name || fallback.city;
    const state = (offices.find((o) => o.State) || po).State || fallback.state;
    // Every post-office Name is a selectable locality for this pincode (de-duped + sorted).
    const areas = [...new Set(offices.map((o) => (o.Name || "").trim()).filter(Boolean))].sort();
    const out = { city, state, district: po.District || fallback.district, areas };
    cache.set(code, out);
    return out;
  } catch {
    cache.set(code, fallback);
    return fallback; // offline / blocked / timeout — local city+state, no locality list
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
 * Validate the locality/area the user entered. Real fake-locality prevention comes from the
 * Area DROPDOWN (the user picks an official India Post post-office name — see lookupPincode's
 * `areas`), so picked values are real by construction and pass instantly.
 *
 * We deliberately DO NOT forward-geocode free text anymore: in India that gave inconsistent
 * results — fake localities matched a nearby centroid and passed, while real small/rural
 * localities missing from OSM were wrongly rejected. So for the "Other (typed)" path we only
 * apply a cheap, offline gibberish guard (no network, no false rejects of real places).
 *
 * @param {{area:string, knownAreas?:string[]}} args
 * @returns {{ok:boolean, reason?:string}}
 */
export function validateAddressLocality({ area, knownAreas = [] }) {
  const locality = String(area || "").trim();
  if (!locality) {
    return { ok: false, reason: "Please enter or select your area / locality." };
  }
  // If it's one of the official localities for this pincode, it's real — accept.
  if (Array.isArray(knownAreas) && knownAreas.includes(locality)) return { ok: true };
  // Free-typed ("Other") locality: only block obvious gibberish, never a real place name.
  if (looksLikeGibberish(locality)) {
    return { ok: false, reason: "That doesn't look like a valid area / locality. Please check and re-enter." };
  }
  return { ok: true };
}
