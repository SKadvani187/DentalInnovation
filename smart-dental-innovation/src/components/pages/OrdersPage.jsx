import { useEffect, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import api from "../../lib/api";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

// Every filter maps to a real order.status value (backend enum extended to match), so
// each option filters live and admin can set the matching status.
const ORDER_TYPES = [
  { id: "all", label: "All Orders" },
  { id: "dispatched", label: "Dispatched" },
  { id: "out", label: "Out for Delivery" },
  { id: "cancelled", label: "Cancelled" },
  { id: "delivered", label: "Delivered" },
  { id: "pending", label: "Pending" },
  { id: "confirmed", label: "Confirmed" },
  { id: "returned", label: "Returned" },
  { id: "returning", label: "Returning" },
  { id: "rejected", label: "Rejected" },
];

const DATE_FILTERS = [
  { id: "30d", label: "Last 30 days" },
  { id: "3m", label: "Last 3 months" },
  { id: "6m", label: "Last 6 months" },
  { id: "1y", label: "Last year" },
  { id: "lfy", label: "Last financial year" },
  { id: "tfy", label: "This financial year" },
];

const ORDER_TYPE_VISIBLE = 5;
const DATE_VISIBLE = 3;

export default function OrdersPage() {
  const { user, token } = useAuth();
  const { openModal } = useUI();
  const navigate = useAppNavigate();
  const [tab, setTab] = useState("orders");
  const [orderType, setOrderType] = useState("all");
  const [dateFilter, setDateFilter] = useState("30d");
  const [orderExpanded, setOrderExpanded] = useState(false);
  const [dateExpanded, setDateExpanded] = useState(false);
  const [orders, setOrders] = useState([]);
  const [refunds, setRefunds] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!token) return;
    let alive = true;
    setLoading(true);
    Promise.all([
      api.myOrders().catch(() => []),
      api.myRefunds().catch(() => []),
    ]).then(([ol, rl]) => {
      if (!alive) return;
      setOrders(ol || []);
      setRefunds(rl || []);
    }).finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [token]);

  if (!user) {
    return (
      <div className="max-w-[1400px] mx-auto px-4 py-12 text-center">
        <p className="text-brand-muted mb-4">Please sign in to view orders.</p>
        <button
          onClick={() => openModal("auth")}
          className="bg-[#3684bf] text-white font-bold px-6 py-2.5 rounded-md hover:bg-[#1f5f96]"
        >
          Sign In
        </button>
      </div>
    );
  }

  const visibleOrderTypes = orderExpanded ? ORDER_TYPES : ORDER_TYPES.slice(0, ORDER_TYPE_VISIBLE);
  const visibleDates = dateExpanded ? DATE_FILTERS : DATE_FILTERS.slice(0, DATE_VISIBLE);
  // Filter-id (sidebar) -> real order.status values (enum now carries all of these).
  const FILTER_TO_STATUS = {
    dispatched: ["shipped"],
    out: ["out_for_delivery"],
    cancelled: ["cancelled"],
    delivered: ["delivered"],
    pending: ["pending", "processing"],
    confirmed: ["confirmed"],
    returned: ["returned", "refunded"],
    returning: ["returning"],
    rejected: ["rejected"],
  };
  // Date window: how many days back each option covers (financial-year ones approximated
  // to rolling windows — good enough for a storefront order history).
  const DATE_DAYS = { "30d": 30, "3m": 90, "6m": 180, "1y": 365, lfy: 730, tfy: 365 };
  const cutoff = Date.now() - (DATE_DAYS[dateFilter] || 30) * 86400000;

  const filteredOrders = orders.filter((o) => {
    const statusOk = orderType === "all" || (FILTER_TO_STATUS[orderType] || [orderType]).includes(o.status);
    const dateOk = !o.createdAt || new Date(o.createdAt).getTime() >= cutoff;
    return statusOk && dateOk;
  });

  return (
    <div className="max-w-[1200px] mx-auto px-4 py-6">
      <nav className="text-sm text-brand-muted mb-4 flex items-center gap-2">
        <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
        <span>/</span>
        <span className="text-brand-ink font-semibold">My Orders</span>
      </nav>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <aside className="lg:col-span-1">
          <div className="border border-gray-200 rounded-xl p-5">
            <h2 className="text-xl font-bold text-brand-ink mb-5">Filters</h2>

            <h3 className="font-bold text-brand-ink mb-3">Order Type</h3>
            <ul className="space-y-2 mb-3">
              {visibleOrderTypes.map((o) => (
                <li key={o.id}>
                  <Radio
                    label={o.label}
                    checked={orderType === o.id}
                    onChange={() => setOrderType(o.id)}
                  />
                </li>
              ))}
            </ul>
            {ORDER_TYPES.length > ORDER_TYPE_VISIBLE && (
              <button
                onClick={() => setOrderExpanded((v) => !v)}
                className="w-full py-2 border-t border-b border-gray-200 text-[#3684bf] font-semibold text-sm flex items-center justify-center gap-1 hover:bg-gray-50"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" className={`transition ${orderExpanded ? "rotate-180" : ""}`}>
                  <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
                </svg>
                {orderExpanded ? "SHOW LESS" : "SHOW MORE"}
              </button>
            )}

            <h3 className="font-bold text-brand-ink mt-6 mb-3">Date & Time</h3>
            <ul className="space-y-2 mb-3">
              {visibleDates.map((d) => (
                <li key={d.id}>
                  <Radio
                    label={d.label}
                    checked={dateFilter === d.id}
                    onChange={() => setDateFilter(d.id)}
                  />
                </li>
              ))}
            </ul>
            <button
              onClick={() => setDateExpanded((v) => !v)}
              className="w-full py-2 border-t border-b border-gray-200 text-[#3684bf] font-semibold text-sm flex items-center justify-center gap-1 hover:bg-gray-50"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" className={`transition ${dateExpanded ? "rotate-180" : ""}`}>
                <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
              </svg>
              {dateExpanded ? "SHOW LESS" : "SHOW MORE"}
            </button>
          </div>
        </aside>

        <section className="lg:col-span-3">
          <div className="border-b border-gray-200 flex items-center gap-8 mb-8">
            <Tab active={tab === "orders"} onClick={() => setTab("orders")}>MY ORDERS</Tab>
            <Tab active={tab === "refunds"} onClick={() => setTab("refunds")}>MY REFUNDS</Tab>
          </div>

          {loading ? (
            <div className="py-12 text-center text-brand-muted">Loading…</div>
          ) : tab === "orders" ? (
            filteredOrders.length > 0 ? (
              <div className="space-y-4">
                {filteredOrders.map((o) => (
                  <OrderCard key={o.orderId} order={o} onOpen={() => navigate("orderDetails", { id: o.orderId })} />
                ))}
              </div>
            ) : (
              <EmptyState tab="orders" onStart={() => navigate("category")} />
            )
          ) : refunds.length > 0 ? (
            <div className="space-y-4">
              {refunds.map((r) => (
                <RefundCard key={r.id} refund={r} onOpen={() => r.orderId && navigate("orderDetails", { id: r.orderId })} />
              ))}
            </div>
          ) : (
            <EmptyState tab="refunds" onStart={() => navigate("contact")} />
          )}
        </section>
      </div>
    </div>
  );
}

function Radio({ label, checked, onChange }) {
  return (
    <label className="flex items-center gap-3 cursor-pointer">
      <span className={`w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 ${checked ? "border-[#3684bf]" : "border-gray-400"}`}>
        {checked && <span className="w-2.5 h-2.5 rounded-full bg-[#3684bf]" />}
      </span>
      <span className={`text-sm ${checked ? "text-brand-ink font-semibold" : "text-brand-muted"}`}>{label}</span>
      <input type="radio" checked={checked} onChange={onChange} className="hidden" />
    </label>
  );
}

function Tab({ active, onClick, children }) {
  return (
    <button
      onClick={onClick}
      className={`pb-3 text-sm font-bold tracking-wider transition relative ${
        active ? "text-[#3684bf]" : "text-brand-muted hover:text-brand-ink"
      }`}
    >
      {children}
      {active && <span className="absolute left-0 right-0 -bottom-px h-0.5 bg-[#3684bf]" />}
    </button>
  );
}

// Color + label per order status (user-friendly pill). Online orders left unpaid surface
// as "Payment Pending" so the customer knows to retry.
const STATUS_META = {
  pending:          { label: "Pending",          cls: "bg-amber-50 text-amber-700 border-amber-200",     dot: "bg-amber-500" },
  processing:       { label: "Processing",        cls: "bg-purple-50 text-purple-700 border-purple-200",  dot: "bg-purple-500" },
  confirmed:        { label: "Confirmed",         cls: "bg-blue-50 text-blue-700 border-blue-200",        dot: "bg-blue-500" },
  shipped:          { label: "Dispatched",        cls: "bg-indigo-50 text-indigo-700 border-indigo-200",  dot: "bg-indigo-500" },
  out_for_delivery: { label: "Out for Delivery",  cls: "bg-cyan-50 text-cyan-700 border-cyan-200",        dot: "bg-cyan-500" },
  delivered:        { label: "Delivered",         cls: "bg-green-50 text-green-700 border-green-200",     dot: "bg-green-500" },
  returning:        { label: "Returning",         cls: "bg-orange-50 text-orange-700 border-orange-200",  dot: "bg-orange-500" },
  returned:         { label: "Returned",          cls: "bg-pink-50 text-pink-700 border-pink-200",        dot: "bg-pink-500" },
  cancelled:        { label: "Cancelled",         cls: "bg-red-50 text-red-700 border-red-200",           dot: "bg-red-500" },
  rejected:         { label: "Rejected",          cls: "bg-rose-50 text-rose-700 border-rose-200",        dot: "bg-rose-500" },
  refunded:         { label: "Refunded",          cls: "bg-gray-100 text-gray-700 border-gray-300",       dot: "bg-gray-500" },
};
function statusMeta(order) {
  if (order.paymentMethod !== "cod" && order.paymentStatus !== "paid") {
    return { label: "Payment Pending", cls: "bg-orange-50 text-orange-700 border-orange-200", dot: "bg-orange-500" };
  }
  return STATUS_META[order.status] || { label: order.status || "—", cls: "bg-gray-100 text-gray-700 border-gray-300", dot: "bg-gray-400" };
}

function StatusPill({ order }) {
  const m = statusMeta(order);
  return (
    <span className={`inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full border ${m.cls}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${m.dot}`} />
      {m.label}
    </span>
  );
}

function OrderCard({ order, onOpen }) {
  const first = order.items?.[0];
  const [extra, setExtra] = useState(null); // { image, mrp } for the first item

  useEffect(() => {
    if (!first) return;
    let alive = true;
    api.product(first.id)
      .then((p) => { if (alive) setExtra({ image: p.image, mrp: p.mrp }); })
      .catch(() => {});
    return () => { alive = false; };
  }, [first?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <button onClick={onOpen} className="w-full text-left border border-gray-200 rounded-xl p-4 flex items-center gap-4 hover:border-[#3684bf]/50 hover:shadow-sm transition">
      <div className="w-20 h-20 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center overflow-hidden shrink-0">
        {extra?.image ? <img src={extra.image} alt={first?.name} className="max-w-full max-h-full object-contain" /> : <span className="text-xs text-gray-300">No image</span>}
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-bold text-brand-ink line-clamp-1">
          {first?.name}{first?.variant ? ` (${first.variant})` : ""}
          {order.items.length > 1 && <span className="text-brand-muted font-normal"> +{order.items.length - 1} more</span>}
        </p>
        <div className="flex items-baseline gap-2 mt-1">
          <span className="text-[#3684bf] font-bold">{fmt(first?.price)}</span>
          {extra?.mrp && extra.mrp > (first?.price || 0) && <span className="text-xs text-brand-muted line-through">{fmt(extra.mrp)}</span>}
        </div>
        <div className="mt-2"><StatusPill order={order} /></div>
      </div>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3684bf" strokeWidth="2.2" className="shrink-0"><path d="M9 6l6 6-6 6" /></svg>
    </button>
  );
}

function RefundCard({ refund, onOpen }) {
  const badge = {
    pending: "bg-amber-100 text-amber-700",
    approved: "bg-blue-100 text-blue-700",
    completed: "bg-green-100 text-green-700",
    rejected: "bg-red-100 text-red-700",
  }[refund.status] || "bg-gray-100 text-gray-700";
  const date = refund.requestedAt ? new Date(refund.requestedAt).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" }) : "";
  return (
    <button onClick={onOpen} className="w-full text-left border border-gray-200 rounded-xl p-4 flex items-center justify-between gap-4 hover:border-[#3684bf]/50 hover:shadow-sm transition">
      <div className="min-w-0">
        <p className="font-bold text-brand-ink">Order {refund.orderId}</p>
        <p className="text-xs text-brand-muted mt-0.5">{date}</p>
        {refund.reason && <p className="text-sm text-brand-muted mt-1 line-clamp-1">Reason: {refund.reason}</p>}
        {refund.adminNote && <p className="text-xs text-brand-muted mt-0.5">Note: {refund.adminNote}</p>}
      </div>
      <div className="text-right shrink-0">
        <span className={`text-xs font-bold px-3 py-1 rounded-full uppercase ${badge}`}>{refund.status}</span>
        {refund.amount > 0 && <p className="text-sm font-bold text-brand-ink mt-1">{fmt(refund.amount)}</p>}
      </div>
    </button>
  );
}

function EmptyState({ tab, onStart }) {
  const isOrders = tab === "orders";
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <div className="w-32 h-32 rounded-full bg-gray-700 flex items-center justify-center mb-6">
        <svg width="60" height="60" viewBox="0 0 24 24" fill="white">
          <path d="M19 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-3 11H9v-1h7v1zm0-3H9V9h7v1zm0-3H9V6h7v1z" />
        </svg>
      </div>
      <h3 className="text-xl font-bold text-brand-ink mb-1">
        {isOrders ? "No Orders to show" : "There are no refunds to show"}
      </h3>
      <p className="text-sm text-brand-muted mb-6 max-w-md">
        {isOrders
          ? "You haven't placed any orders yet."
          : "You haven't placed any refund request yet. Get help with returns and refunds."}
      </p>
      <button
        onClick={onStart}
        className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold px-10 py-3 rounded-full"
      >
        {isOrders ? "Start Shopping" : "Need Help"}
      </button>
    </div>
  );
}
