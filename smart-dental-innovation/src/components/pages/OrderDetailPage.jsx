import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import api, { loadRazorpayScript } from "../../lib/api";

const fmt = (n) => `₹${Number(n || 0).toLocaleString("en-IN")}`;

// Single order view. Mirrors the reference "Order Details" page: a pending-payment
// banner + Retry Payment for unpaid online orders, the delivery address, a track-order
// timeline, and the line items. Reached after a dismissed Razorpay checkout (and from
// the orders list).
export default function OrderDetailPage() {
  const { id } = useParams();
  const { token } = useAuth();
  const { showToast } = useUI();
  const navigate = useAppNavigate();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [retrying, setRetrying] = useState(false);
  // Order items only store name/price; enrich with image + category + mrp from products
  // (best-effort) so the item card matches the reference (thumb + category breadcrumb).
  const [enriched, setEnriched] = useState({}); // slug -> { image, mrp, categories }
  // Refund: existing request for this order (null = none), plus the request modal.
  const [refund, setRefund] = useState(null);
  const [refundOpen, setRefundOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const loadRefund = () => {
    api.getRefundForOrder(id).then(setRefund).catch(() => {});
  };
  useEffect(() => { if (token) loadRefund(); }, [id, token]); // eslint-disable-line react-hooks/exhaustive-deps

  const submitRefund = async () => {
    if (!reason.trim()) return;
    setSubmitting(true);
    try {
      await api.requestRefund({ orderId: id, reason: reason.trim() });
      showToast?.("Refund request submitted. We'll review it shortly.", "success");
      setRefundOpen(false);
      setReason("");
      loadRefund();
    } catch (err) {
      showToast?.(err.message || "Couldn't submit your request.", "error");
    } finally {
      setSubmitting(false);
    }
  };

  useEffect(() => {
    if (!order?.items?.length) return;
    let alive = true;
    Promise.all(order.items.map((it) =>
      api.product(it.id).then((p) => ({ id: it.id, p })).catch(() => null)
    )).then((rs) => {
      if (!alive) return;
      const map = {};
      for (const r of rs) {
        if (!r || !r.p) continue;
        map[r.id] = {
          image: r.p.image,
          mrp: r.p.mrp,
          categories: r.p.categories || (r.p.category ? [r.p.category] : []),
        };
      }
      setEnriched(map);
    });
    return () => { alive = false; };
  }, [order]);

  const load = () => {
    if (!token) { setLoading(false); return; }
    setLoading(true);
    api.getOrder(id)
      .then((o) => setOrder(o))
      .catch((err) => console.warn("[order] fetch failed:", err.message))
      .finally(() => setLoading(false));
  };
  useEffect(load, [id, token]); // eslint-disable-line react-hooks/exhaustive-deps

  const isPendingPayment = order && order.paymentMethod !== "cod" && order.paymentStatus !== "paid";

  // Re-open the Razorpay widget for this existing order.
  const retryPayment = async () => {
    setRetrying(true);
    try {
      const rzp = await api.createRazorpayOrder(order.orderId);
      await loadRazorpayScript();
      const rz = new window.Razorpay({
        key: rzp.keyId, order_id: rzp.rzpOrderId, amount: rzp.amount, currency: rzp.currency,
        name: "Smart Dental Innovations", description: `Order ${order.orderId}`,
        prefill: rzp.prefill, theme: { color: "#0b2545" },
        handler: async (resp) => {
          try {
            await api.verifyRazorpayPayment({
              orderId: order.orderId,
              razorpay_payment_id: resp.razorpay_payment_id,
              razorpay_order_id: resp.razorpay_order_id,
              razorpay_signature: resp.razorpay_signature,
            });
          } catch {
            showToast?.("Payment received — confirming shortly.", "info");
          } finally {
            load(); // refresh status
            setRetrying(false);
          }
        },
        modal: { ondismiss: () => setRetrying(false) },
      });
      rz.on("payment.failed", () => { showToast?.("Payment failed — you can retry.", "error"); setRetrying(false); });
      rz.open();
    } catch (err) {
      console.error("[order] retry failed:", err.message);
      showToast?.("Couldn't start the payment. Please try again.", "error");
      setRetrying(false);
    }
  };

  if (!token) {
    return (
      <div className="max-w-[900px] mx-auto px-4 py-12 text-center text-brand-muted">
        Please sign in to view this order.
      </div>
    );
  }
  if (loading) return <div className="max-w-[900px] mx-auto px-4 py-12 text-center text-brand-muted">Loading order…</div>;
  if (!order) return <div className="max-w-[900px] mx-auto px-4 py-12 text-center text-brand-muted">Order not found.</div>;

  const addr = order.address || {};
  const addrLine = [addr.address, addr.city, addr.state].filter(Boolean).join(", ") + (addr.pincode ? ` (${addr.pincode})` : "");
  const placedAt = order.createdAt
    ? new Date(order.createdAt).toLocaleString("en-IN", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" })
    : "";
  const placedDate = order.createdAt
    ? new Date(order.createdAt).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })
    : "";

  // Refund eligible: a real (paid or COD-delivered) order that isn't already cancelled/
  // refunded and has no active refund request. We let any non-pending-payment order request.
  const refundEligible =
    !isPendingPayment &&
    !["cancelled", "refunded"].includes(order.status) &&
    (!refund || refund.status === "rejected");

  const refundBadge = {
    pending: "bg-amber-100 text-amber-700",
    approved: "bg-blue-100 text-blue-700",
    completed: "bg-green-100 text-green-700",
    rejected: "bg-red-100 text-red-700",
  };

  return (
    <div className="max-w-[900px] mx-auto px-4 py-6">
      <button onClick={() => navigate("orders")} className="flex items-center gap-2 text-lg font-bold text-brand-ink mb-5">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        Order Details
      </button>

      {/* Pending payment banner */}
      {isPendingPayment && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
          <div className="flex items-start gap-2 mb-3">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" strokeWidth="2" className="shrink-0 mt-0.5"><path d="M12 9v4M12 17h.01" /><circle cx="12" cy="12" r="10" /></svg>
            <p className="text-sm text-amber-800 font-medium">Your order payment is pending. Please pay the amount to get your order delivered.</p>
          </div>
          <button onClick={retryPayment} disabled={retrying} className="bg-yellow-400 hover:bg-yellow-500 disabled:opacity-60 text-brand-ink font-bold px-5 py-2.5 rounded-lg uppercase text-sm">
            {retrying ? "Opening…" : "Retry Payment"}
          </button>
        </div>
      )}

      {/* Refund: show current request status (incl. rejected, so the customer sees why). */}
      {refund && (
        <div className={`border rounded-xl p-4 mb-6 flex items-center justify-between ${refund.status === "rejected" ? "border-red-200 bg-red-50" : "border-gray-200"}`}>
          <div>
            <p className="text-sm font-semibold text-brand-ink">
              {refund.status === "completed" ? "Refund completed"
                : refund.status === "rejected" ? "Refund request rejected"
                : "Refund request " + refund.status}
            </p>
            {refund.reason && <p className="text-xs text-brand-muted mt-0.5">Your reason: {refund.reason}</p>}
            {refund.adminNote && <p className="text-xs text-brand-muted mt-0.5">{refund.status === "rejected" ? "Why: " : "Note: "}{refund.adminNote}</p>}
            {refund.status === "rejected" && <p className="text-xs text-brand-muted mt-0.5">Your order is unaffected — you can raise a new request below.</p>}
          </div>
          <span className={`text-xs font-bold px-3 py-1 rounded-full uppercase shrink-0 ${refundBadge[refund.status] || "bg-gray-100 text-gray-700"}`}>
            {refund.status}{refund.amount ? ` · ${fmt(refund.amount)}` : ""}
          </span>
        </div>
      )}
      {refundEligible && (
        <div className="flex justify-end mb-6">
          <button onClick={() => setRefundOpen(true)} className="border border-[#3684bf] text-[#3684bf] font-semibold text-sm px-4 py-2 rounded-lg hover:bg-[#3684bf]/5">
            {refund?.status === "rejected" ? "Request Refund Again" : "Request Refund / Return"}
          </button>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Delivery address */}
        <div className="border border-gray-200 rounded-xl p-5">
          <h3 className="font-bold text-brand-ink mb-2">Delivery Address</h3>
          <p className="text-sm text-brand-muted">{addrLine}</p>
        </div>

        {/* Track order */}
        <div className="border border-gray-200 rounded-xl p-5">
          <h3 className="font-bold text-brand-ink mb-3">Track Order</h3>
          <div className="flex gap-3">
            <span className="w-3 h-3 rounded-full bg-green-500 mt-1 shrink-0" />
            <div>
              <p className="text-sm font-semibold text-brand-ink">Order Placed <span className="font-normal text-brand-muted ml-1">{placedAt}</span></p>
              <p className="text-sm text-brand-muted">Your order has been placed</p>
            </div>
          </div>
          {/* Order Items live under Track Order in the right column (matches reference). */}
          <h3 className="font-bold text-brand-ink mt-6 mb-3">Order Items ({order.items?.length || 0})</h3>
          <div className="space-y-4">
            {(order.items || []).map((it, i) => {
              const e = enriched[it.id] || {};
              const cats = (e.categories || []).filter(Boolean);
              return (
                <div key={i} className="flex gap-3">
                  <div className="w-16 h-16 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                    {e.image ? <img src={e.image} alt={it.name} className="max-w-full max-h-full object-contain" /> : <span className="text-xs text-gray-300">No image</span>}
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm text-brand-ink">{it.name}{it.variant ? ` (${it.variant})` : ""}</p>
                    {cats.length > 0 && <p className="text-xs text-[#3684bf] font-medium mt-0.5">{cats.join(", ")}</p>}
                    <div className="flex items-baseline gap-2 mt-1">
                      <span className="text-sm font-bold text-brand-ink">{fmt(it.price)}</span>
                      {e.mrp && e.mrp > it.price && <span className="text-xs text-brand-muted line-through">{fmt(e.mrp)}</span>}
                      {it.qty > 1 && <span className="text-xs text-brand-muted">× {it.qty}</span>}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Order Details table (left column on wide screens) */}
      <div className="border border-gray-200 rounded-xl p-5 mt-6 lg:max-w-[560px]">
        <h3 className="font-bold text-brand-ink mb-4">Order Details</h3>
        <Detail label="Name" value={addr.name} />
        <Detail label="Order ID" value={order.orderId} />
        <Detail label="Shippment Number" value={order.trackingId || ""} />
        <Detail label="Order Date" value={placedDate} />
        <Detail label="Payment Method" value={order.paymentMethod === "cod" ? "Cash on Delivery" : "Payment Gateway"} />
        <Detail label="Order Status" value={cap(order.status)} />
        <Detail label="Total Amount" value={fmt(order.total)} />
        <Detail label="Shipping Charges" value={order.shipping > 0 ? fmt(order.shipping) : "FREE"} />
        {order.discount > 0 && <Detail label="Total Discount" value={fmt(order.discount)} />}
      </div>

      {/* Request refund modal */}
      {refundOpen && (
        <div className="fixed inset-0 z-[1200] bg-black/50 flex items-center justify-center p-4" onClick={() => setRefundOpen(false)}>
          <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-brand-ink mb-1">Request Refund / Return</h3>
            <p className="text-sm text-brand-muted mb-4">Tell us why you'd like a refund for order {order.orderId}. Our team will review it.</p>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={4}
              placeholder="Reason for refund (e.g. wrong item, damaged, no longer needed)…"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#3684bf]"
            />
            <div className="flex justify-end gap-2 mt-4">
              <button onClick={() => setRefundOpen(false)} className="px-4 py-2 rounded-lg border border-gray-300 font-semibold text-brand-ink hover:bg-gray-50">Cancel</button>
              <button onClick={submitRefund} disabled={!reason.trim() || submitting} className="px-4 py-2 rounded-lg bg-[#3684bf] disabled:bg-gray-200 disabled:text-gray-400 text-white font-bold">
                {submitting ? "Submitting…" : "Submit Request"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

const cap = (s) => (s ? String(s).charAt(0).toUpperCase() + String(s).slice(1) : "");

function Detail({ label, value }) {
  return (
    <div className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
      <span className="text-sm text-brand-muted">{label}</span>
      <span className="text-sm font-bold text-brand-ink text-right">{value}</span>
    </div>
  );
}
