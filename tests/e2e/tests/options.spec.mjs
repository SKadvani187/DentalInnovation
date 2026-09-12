import { test, expect } from "@playwright/test";

// The customer-facing delivery-options rule.
//
// A shopper picks delivery on one trade-off: how fast, for how much. Two options arriving on the
// same day are not a choice — they only invite paying more for the same service. So the quote
// returns `options`: one entry per distinct arrival date, the cheapest of each, soonest first.
// `methods` still carries every method for the admin calculator and for checkout validation.

const LIVE = "http://localhost:9090/api/v1";
// A throwaway database where Express is genuinely faster (Gujarat base 3 days, Express 1 day).
// Seed with tests/e2e/seed_twodate.php, then:
//   DB_NAME=dentinno_twodate php -S 127.0.0.1:9098 -t dentinno
const TWO_DATE = "http://localhost:9098/api/v1";
const PRODUCT = "cautery-foot-paddle";

const quote = async (request, base, qty = 1, pincode = "380001") => {
  const r = await request.post(`${base}/shipping_quote.php`, {
    data: { items: [{ id: PRODUCT, qty }], pincode },
  });
  expect(r.ok()).toBeTruthy();
  return r.json();
};

test.describe("F. Customer-facing delivery options", () => {
  test("F1 · the quote returns an options list alongside the full method list", async ({ request }) => {
    const q = await quote(request, LIVE);
    expect(Array.isArray(q.options)).toBe(true);
    expect(Array.isArray(q.methods)).toBe(true);
  });

  test("F2 · every option is a real, applicable method — nothing invented", async ({ request }) => {
    const q = await quote(request, LIVE);
    for (const o of q.options) {
      const m = q.methods.find((x) => x.id === o.id);
      expect(m, `option ${o.name} must exist in methods`).toBeTruthy();
      expect(m.applicable).toBe(true);
      expect(Number(o.cost)).toBe(Number(m.cost));
    }
  });

  test("F3 · no two options share an arrival date", async ({ request }) => {
    const q = await quote(request, LIVE);
    const dates = q.options.map((o) => o.eta);
    expect(new Set(dates).size).toBe(dates.length);
  });

  test("F4 · each option is the cheapest method for its arrival date", async ({ request }) => {
    const q = await quote(request, LIVE);
    for (const o of q.options) {
      const sameDay = q.methods.filter((m) => m.applicable && m.eta === o.eta);
      const cheapest = Math.min(...sameDay.map((m) => Number(m.cost)));
      expect(Number(o.cost), `on ${o.eta}`).toBe(cheapest);
    }
  });

  test("F5 · options are ordered soonest arrival first", async ({ request }) => {
    const q = await quote(request, LIVE);
    const dates = q.options.map((o) => o.eta);
    expect(dates).toEqual([...dates].sort());
  });

  test("F6 · same-day duplicates are collapsed away", async ({ request }) => {
    const q = await quote(request, LIVE);
    const applicable = q.methods.filter((m) => m.applicable);
    const distinctDates = new Set(applicable.map((m) => m.eta)).size;
    expect(q.options.length).toBe(distinctDates);
    expect(q.options.length).toBeLessThanOrEqual(applicable.length);
  });

  test("F7 · the preselected method is one the customer is actually offered", async ({ request }) => {
    const q = await quote(request, LIVE);
    if (q.defaultMethodId === null) test.skip(true, "No applicable method to preselect.");
    expect(q.options.some((o) => o.id === q.defaultMethodId)).toBe(true);
  });

  test("F8 · a genuinely faster option survives, and leads the list", async ({ request }) => {
    let q;
    try {
      q = await quote(request, TWO_DATE);
    } catch {
      test.skip(true, "Two-date server on :9098 not running — see the header comment.");
    }
    // Three methods, two distinct dates: the same-day duplicate goes, the speed upgrade stays.
    expect(q.methods.filter((m) => m.applicable).length).toBe(3);
    expect(q.options.length).toBe(2);

    const [first, second] = q.options;
    expect(first.eta < second.eta, "fastest option must come first").toBe(true);
    expect(Number(first.cost)).toBeGreaterThan(Number(second.cost)); // faster costs more — a real trade-off
    expect(first.name).toMatch(/express/i);
  });
});
