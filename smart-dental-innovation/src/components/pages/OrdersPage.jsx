import { useEffect, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import api from "../../lib/api";

const fmt = (n) => `₹${Number(n).toLocaleString("en-IN")}`;

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
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!token) return;
    let alive = true;
    setLoading(true);
    api.myOrders()
      .then((list) => { if (alive) setOrders(list || []); })
      .catch((err) => console.warn("[orders] fetch failed:", err.message))
      .finally(() => alive && setLoading(false));
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
  const filteredOrders = orderType === "all" ? orders : orders.filter((o) => o.status === orderType);

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
            <button
              onClick={() => setOrderExpanded((v) => !v)}
              className="w-full py-2 border-t border-b border-gray-200 text-[#3684bf] font-semibold text-sm flex items-center justify-center gap-1 hover:bg-gray-50"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" className={`transition ${orderExpanded ? "rotate-180" : ""}`}>
                <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
              </svg>
              {orderExpanded ? "SHOW LESS" : "SHOW MORE"}
            </button>

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

          {tab === "orders" && filteredOrders.length > 0 ? (
            <div className="space-y-4">
              {filteredOrders.map((o) => (
                <OrderCard key={o.orderId} order={o} />
              ))}
            </div>
          ) : loading ? (
            <div className="py-12 text-center text-brand-muted">Loading orders…</div>
          ) : (
            <EmptyState
              tab={tab}
              onStart={() => navigate(tab === "orders" ? "category" : "contact")}
            />
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

function OrderCard({ order }) {
  const date = order.createdAt ? new Date(order.createdAt).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" }) : "";
  const statusColor = {
    delivered: "bg-green-100 text-green-700",
    shipped: "bg-blue-100 text-blue-700",
    pending: "bg-amber-100 text-amber-700",
    processing: "bg-purple-100 text-purple-700",
    confirmed: "bg-indigo-100 text-indigo-700",
    cancelled: "bg-red-100 text-red-700",
  }[order.status] || "bg-gray-100 text-gray-700";

  return (
    <div className="border border-gray-200 rounded-xl p-4">
      <div className="flex items-center justify-between mb-3">
        <div>
          <p className="font-bold text-brand-ink">{order.orderId}</p>
          <p className="text-xs text-brand-muted">{date}</p>
        </div>
        <span className={`text-xs font-bold px-3 py-1 rounded-full uppercase ${statusColor}`}>{order.status}</span>
      </div>
      <div className="space-y-1.5 mb-3">
        {order.items.map((it, i) => (
          <div key={i} className="flex items-center justify-between text-sm">
            <span className="text-brand-ink">{it.name}{it.variant ? ` (${it.variant})` : ""} × {it.qty}</span>
            <span className="text-brand-muted">{fmt(it.total)}</span>
          </div>
        ))}
      </div>
      <div className="flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
        <span className="text-brand-muted">{order.paymentMethod?.toUpperCase()} · {order.paymentStatus}</span>
        <span className="font-bold text-brand-ink">{fmt(order.total)}</span>
      </div>
    </div>
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
