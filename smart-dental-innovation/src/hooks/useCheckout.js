import { useEffect, useState } from "react";
import api from "../lib/api";

// Live serviceability + COD for a selected delivery address. Extracted from CheckoutDrawer
// to keep the component lean. Re-runs when the sheet opens or the chosen address changes;
// pushes the pincode into the cart (for the shipping quote) as a side-effect.
// Returns the delivery object ({ serviceable, cod, days, eta }) or null.
export function useDeliveryCheck(open, selectedAddr, setDeliveryPincode) {
  const [delivery, setDelivery] = useState(null);
  useEffect(() => {
    if (!open || !selectedAddr) { setDelivery(null); return; }
    const pin = (selectedAddr.pincode || "").replace(/\D/g, "");
    if (pin.length !== 6) { setDelivery(null); return; }
    setDeliveryPincode(pin);
    let alive = true;
    api.checkDelivery(pin)
      .then((d) => { if (alive) setDelivery(d); })
      .catch(() => { if (alive) setDelivery(null); });
    return () => { alive = false; };
  }, [open, selectedAddr, setDeliveryPincode]);
  return delivery;
}

// Live stock guard for the cart's product lines. Offer/gift lines are server-priced combos —
// skipped here (the server still guards them on order placement). Returns an array of
// { name, available } for any line whose requested qty exceeds current stock.
export function useStockCheck(open, items) {
  const [stockIssues, setStockIssues] = useState([]);
  useEffect(() => {
    if (!open) { setStockIssues([]); return; }
    const lines = items.filter((i) => (i.type || "product") === "product");
    if (lines.length === 0) { setStockIssues([]); return; }
    let alive = true;
    Promise.all(lines.map((i) =>
      api.product(i.id).then((p) => ({ i, p })).catch(() => null)
    )).then((results) => {
      if (!alive) return;
      const issues = [];
      for (const r of results) {
        if (!r || !r.p) continue;
        const avail = typeof r.p.stock === "number" ? r.p.stock : (r.p.inStock ? Infinity : 0);
        if (avail < r.i.qty) issues.push({ name: r.i.name, available: avail });
      }
      setStockIssues(issues);
    });
    return () => { alive = false; };
  }, [open, items]);
  return stockIssues;
}
