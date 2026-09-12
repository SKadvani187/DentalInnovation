import { test, expect } from "@playwright/test";

// The critical fix of this session, tested end-to-end over HTTP.
//
// These run against a THROWAWAY database served on port 9099, seeded with a deliberate hole in the
// rate card: one weight method whose only rule covers 0-0.99 kg, and the old dangerous
// shippingConfig of {"freeThreshold":1000,"flatRate":99}. The live database is never touched.
//
// Before the fix, an order that fell in the hole was priced by the fallback, which honoured
// freeThreshold — so the LARGER the order, the more likely it shipped for nothing.

const GAP_API = "http://localhost:9099/api/v1";
const PRODUCT = "cautery-foot-paddle";   // 0.400 kg, Rs.1,000 each

const quote = async (request, qty) => {
  const r = await request.post(`${GAP_API}/shipping_quote.php`, {
    data: { items: [{ id: PRODUCT, qty }], pincode: "380001" },
  });
  expect(r.ok()).toBeTruthy();
  return r.json();
};

test.describe("D. Fallback safety (throwaway DB with a deliberate rate-card hole)", () => {
  test("D1 · the hole really is reachable — the method drops out", async ({ request }) => {
    const q = await quote(request, 3);           // 1.2 kg, outside the 0-0.99 rule
    expect(q.weight).toBeCloseTo(1.2, 2);
    const m = q.methods.find((x) => x.id === 900);
    expect(m.applicable).toBe(false);            // no rule matched, so it is not an option
  });

  test("D2 · an order inside the covered band still prices normally", async ({ request }) => {
    const q = await quote(request, 1);           // 0.4 kg, inside the rule
    expect(q.shipping).toBe(60);
  });

  test("D3 · REGRESSION: an order in the hole is charged, never free", async ({ request }) => {
    // qty 3 = 1.2 kg, subtotal Rs.3,000 — comfortably over the Rs.1,000 free threshold.
    // This returned Rs.0 before the fix.
    const q = await quote(request, 3);
    expect(q.shipping).toBe(99);
    expect(q.free).toBe(false);
  });

  test("D4 · REGRESSION: the bigger the order, the worse the old leak was", async ({ request }) => {
    for (const qty of [3, 13, 60]) {
      const q = await quote(request, qty);
      expect(q.shipping, `qty ${qty} — subtotal Rs.${q.subtotal}`).toBe(99);
      expect(q.free, `qty ${qty} must not be free`).toBe(false);
    }
  });

  test("D5 · the free threshold no longer applies to a configuration gap", async ({ request }) => {
    const small = await quote(request, 3);       // Rs.3,000  — over the threshold
    const large = await quote(request, 60);      // Rs.60,000 — far over it
    expect(small.shipping).toBe(large.shipping); // the subtotal must make no difference here
  });
});
