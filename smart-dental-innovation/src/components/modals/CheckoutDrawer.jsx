import { useEffect, useState, useCallback } from "react";
import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useAuth } from "../../context/AuthContext";
import { useSettings } from "../../context/SettingsContext";
import { useAppNavigate } from "../../hooks/useAppNavigate";
import api, { loadRazorpayScript } from "../../lib/api";
import { lookupPincode, detectCurrentPincode, validateAddressLocality } from "../../lib/pincode";
import { useDeliveryCheck, useStockCheck } from "../../hooks/useCheckout";
import logoAsset from "../../assets/logo.png";

const fmt = (n) => `₹${Number(n || 0).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmt0 = (n) => `₹${Math.round(Number(n || 0)).toLocaleString("en-IN")}`;

// Compose a saved account address into a single display line.
const addrLine = (a) =>
  [a?.line1 || a?.building, a?.line2 || a?.area, a?.landmark, a?.city, a?.district, a?.state, a?.pincode]
    .filter(Boolean)
    .join(", ");

// Map a saved account address -> the order payload's `address` shape (orders.php).
function toOrderAddress(a) {
  if (!a) return null;
  return {
    name: a.name || "",
    phone: a.mobile || a.phone || "",
    address: addrLine(a),
    city: a.city || "",
    state: a.state || "",
    pincode: a.pincode || "",
  };
}

const emptyForm = { type: "Home", pincode: "", city: "", state: "", areas: [], building: "", area: "", name: "", mobile: "" };

export default function CheckoutDrawer() {
  const { modal, closeModal, showToast, openModal } = useUI();
  const { items, pricing, appliedCoupon, applyCoupon, removeCoupon, clearCart, setDeliveryPincode } = useCart();
  const { user, token, addAddress, updateAddress, setDefaultAddress } = useAuth();
  const { branding = {}, coupons: COUPONS = [], company = {} } = useSettings();
  const navigate = useAppNavigate();
  const logoSrc = branding.logo1 || branding.logo2 || logoAsset;

  const open = modal === "checkout";
  const [view, setView] = useState("delivery"); // delivery | breakup | payment | addrList | addrPincode | addrForm | done
  const [showBreakup, setShowBreakup] = useState(false);
  const [askCancel, setAskCancel] = useState(false);

  // Selected delivery address (from the saved book).
  const addresses = user?.addresses || [];
  const [selectedAddrId, setSelectedAddrId] = useState(null);
  const selectedAddr = addresses.find((a) => a.id === selectedAddrId) || null;

  const [payment, setPayment] = useState("online");
  const [method, setMethod] = useState("upi"); // online sub-method (display only; Razorpay shows all)
  const [placing, setPlacing] = useState(false);
  const [orderId, setOrderId] = useState(null);

  // Add/edit address form state. `editTarget` non-null = editing an existing address.
  const [form, setForm] = useState(emptyForm);
  const [editTarget, setEditTarget] = useState(null);

  const { mrpTotal, subtotal, couponDiscount, deliveryCharges, tax, finalTotal, totalSaved } = pricing;
  const productDiscount = Math.max(0, mrpTotal - subtotal);

  // On open: reset to delivery, pick the default (or first) saved address.
  useEffect(() => {
    if (!open) return;
    setView("delivery");
    setOrderId(null);
    setShowBreakup(false);
    setAskCancel(false);
    const def = addresses.find((a) => a.isDefault) || addresses[0] || null;
    setSelectedAddrId(def?.id || null);
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  // Serviceability + COD for the chosen destination, and the live stock guard — extracted
  // into hooks (see hooks/useCheckout.js). delivery = { serviceable, cod, days, eta } | null.
  const delivery = useDeliveryCheck(open, selectedAddr, setDeliveryPincode);
  const stockIssues = useStockCheck(open, items);  // [{ name, available }]

  const hasStockIssue = stockIssues.length > 0;

  // COD allowed only when the destination is serviceable AND the pincode permits COD.
  const codAvailable = !!(delivery?.serviceable && delivery?.cod);
  // Effective method: never let an unavailable COD selection survive to PAY NOW —
  // derived at render (no effect/cascading render) so it always reflects current COD state.
  const effectivePayment = payment === "cod" && !codAvailable ? "online" : payment;

  // These sub-screens render with a simple header (back + title + close), no price summary.
  const isAddressView = view === "addrList" || view === "addrPincode" || view === "addrForm" || view === "coupons";
  const addrTitle =
    view === "addrList" ? "Select Delivery Address" :
    view === "addrForm" ? "Address Details" :
    view === "coupons" ? "Apply Coupon" :
    "Add Delivery Address";

  const requestClose = useCallback(() => {
    // Nudge before abandoning a non-empty cart (matches reference "Cancel checkout?").
    if (items.length > 0 && view !== "done") { setAskCancel(true); return; }
    closeModal();
  }, [items.length, view, closeModal]);

  if (!open) return null;

  const buildPayload = (paymentMethod) => ({
    // Server re-prices every line authoritatively and recomputes discount/shipping/tax.
    // We only pass the coupon code + line identity. For per-product free gifts the server
    // (orders.php) requires `autoGift` + `parentSlug` to validate the gift against its
    // granting product — omitting them routes the line into the offer-gift branch and 409s.
    items: items.map((i) => ({
      id: i.id, name: i.name, price: i.price, qty: i.qty, variant: i.variant,
      type: i.type || "product", offerId: i.offerId,
      autoGift: i.autoGift, parentSlug: i.parentSlug,
    })),
    address: toOrderAddress(selectedAddr),
    paymentMethod,
    couponCode: appliedCoupon?.code || null,
  });

  const onPay = async () => {
    if (!token) { showToast?.("Please log in to complete your order.", "info"); openModal("auth"); return; }
    if (hasStockIssue) { showToast?.(`${stockIssues[0].name} is out of stock. Please remove it to continue.`, "error"); return; }
    if (!selectedAddr) { setView(addresses.length === 0 ? "addrPincode" : "addrList"); return; }
    if (effectivePayment === "online") return payOnline();

    // Cash on Delivery — order is created unpaid, no gateway step.
    setPlacing(true);
    try {
      const order = await api.placeOrder(buildPayload("cod"));
      setOrderId(order.orderId);
      clearCart();
      setView("done");
    } catch (err) {
      console.error("[checkout] order failed:", err.message);
      showToast?.(err.message || "Could not place your order. Please try again.", "error");
    } finally {
      setPlacing(false);
    }
  };

  // Online: create a pending order, create a Razorpay order, open the hosted widget,
  // verify the signature server-side before confirming. (Lifted from CheckoutModal.)
  const payOnline = async () => {
    setPlacing(true);
    let order;
    try {
      order = await api.placeOrder(buildPayload("online"));
      const rzp = await api.createRazorpayOrder(order.orderId);
      await loadRazorpayScript();

      const rz = new window.Razorpay({
        key: rzp.keyId,
        order_id: rzp.rzpOrderId,
        amount: rzp.amount,
        currency: rzp.currency,
        name: company.name || "",
        description: `Order ${order.orderId}`,
        prefill: rzp.prefill,
        theme: { color: "#0b2545" },
        handler: async (resp) => {
          try {
            await api.verifyRazorpayPayment({
              orderId: order.orderId,
              razorpay_payment_id: resp.razorpay_payment_id,
              razorpay_order_id: resp.razorpay_order_id,
              razorpay_signature: resp.razorpay_signature,
            });
          } catch (err) {
            console.warn("[checkout] verify failed, webhook will reconcile:", err.message);
            showToast?.("Payment received — confirming your order shortly.", "info");
          } finally {
            setOrderId(order.orderId);
            clearCart();
            setView("done");
            setPlacing(false);
          }
        },
        modal: {
          ondismiss: () => {
            // User exited Razorpay without paying. The order already exists as pending —
            // leave checkout and land on its Order Details page (pending banner + Retry
            // Payment). Keep the cart intact: if this pending order is never paid (expires),
            // the customer still has their items and isn't forced to re-add everything.
            // Tell the server so the admin sees the failed-payment immediately (not after the
            // 30-min cleanup). Fire-and-forget — never block the UX on it.
            api.reportPaymentFailed(order.orderId).catch(() => {});
            setPlacing(false);
            closeModal();
            showToast?.("Payment not completed — your order is saved as pending.", "info");
            navigate("orderDetails", { id: order.orderId });
          },
        },
      });
      rz.on("payment.failed", () => {
        // Payment failed — order stays pending, keep the cart so the customer can retry
        // (via the Order Details page) without losing their items. Report to the server so
        // the admin can tell this apart from a fresh order immediately.
        api.reportPaymentFailed(order.orderId).catch(() => {});
        setPlacing(false);
        closeModal();
        showToast?.("Payment failed — your order is saved as pending, you can retry.", "error");
        navigate("orderDetails", { id: order.orderId });
      });
      rz.open();
    } catch (err) {
      console.error("[checkout] online payment failed:", err.message);
      setPlacing(false);
      showToast?.(
        order
          ? "Couldn't start the payment. Your order is saved as pending — retry from My Orders."
          : (err.message || "Could not place your order. Please try again."),
        "error"
      );
    }
  };

  return createPortal(
    <>
      {/* Backdrop */}
      <div className="fixed inset-0 z-[1100] bg-black/50" onClick={requestClose} />

      {/* Bottom-sheet on mobile, centered card on desktop */}
      <div className="fixed inset-x-0 bottom-0 z-[1101] sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4 pointer-events-none">
        <div
          className="pointer-events-auto w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl flex flex-col max-h-[90vh] sm:max-h-[88vh]"
          role="dialog"
          aria-modal="true"
        >
          {view === "done" ? (
            <DonePanel orderId={orderId} onClose={closeModal} />
          ) : isAddressView ? (
            /* Address screens render as their own modal — simple header (back + title + close),
               no price summary / PAY NOW (matches reference's standalone Add-Address dialog). */
            <>
              <SimpleHeader
                title={addrTitle}
                onBack={() => setView(view === "addrForm" ? (editTarget ? "addrList" : "addrPincode") : view === "addrPincode" ? (addresses.length === 0 ? "delivery" : "addrList") : "delivery")}
                onClose={requestClose}
              />
              <div className="flex-1 overflow-y-auto">
                {view === "addrList" && (
                  <AddressListView
                    addresses={addresses}
                    selectedId={selectedAddrId}
                    onSelect={(id) => { setSelectedAddrId(id); setDefaultAddress(id); setView("delivery"); }}
                    onAdd={() => setView("addrPincode")}
                    onEdit={(a) => { setEditTarget(a); setForm({ ...emptyForm, ...mapAddrToForm(a) }); setView("addrForm"); }}
                  />
                )}
                {view === "addrPincode" && (
                  <PincodeGateView
                    onServiceable={(pin, city, state, areas) => {
                      setEditTarget(null);
                      setForm({ ...emptyForm, pincode: pin, city, state, areas: areas || [], name: user?.name || "", mobile: user?.mobile || "" });
                      setView("addrForm");
                    }}
                  />
                )}
                {view === "addrForm" && (
                  <AddressFormView
                    form={form}
                    setForm={setForm}
                    isEdit={!!editTarget}
                    onSave={async () => {
                      // Re-verify serviceability before committing (the pincode could have
                      // been edited after the gate). Returns {ok,error}; the form shows the
                      // "Scanning address…" loader while this runs.
                      try {
                        const d = await api.checkDelivery(form.pincode);
                        if (!d?.serviceable) return { ok: false, error: "We don't deliver to this pincode. Please use a different address." };
                      } catch {
                        // Serviceability service down — don't block the save, just proceed.
                      }
                      // Locality check: a value picked from the pincode's official area list is
                      // real by construction; a free-typed ("Other") value only gets a cheap
                      // gibberish guard. (No more unreliable free-text geocoding.)
                      const loc = validateAddressLocality({ area: form.area, knownAreas: form.areas });
                      if (!loc.ok) return { ok: false, error: loc.reason };
                      const payload = {
                        type: form.type,
                        name: form.name, mobile: form.mobile,
                        building: form.building, area: form.area,
                        line1: form.building, line2: form.area,
                        city: form.city, state: form.state, pincode: form.pincode,
                      };
                      if (editTarget) {
                        updateAddress(editTarget.id, payload);
                        setSelectedAddrId(editTarget.id);
                      } else {
                        const res = addAddress({ ...payload, isDefault: addresses.length === 0 });
                        if (res?.id) setSelectedAddrId(res.id);
                      }
                      setView("delivery");
                      return { ok: true };
                    }}
                  />
                )}
                {view === "coupons" && (
                  <CouponsView
                    coupons={COUPONS}
                    subtotal={subtotal}
                    appliedCoupon={appliedCoupon}
                    applyCoupon={applyCoupon}
                    removeCoupon={removeCoupon}
                    onDone={() => setView("delivery")}
                  />
                )}
              </div>
            </>
          ) : (
            <>
              <SheetHeader
                logoSrc={logoSrc}
                totalSaved={totalSaved}
                itemCount={items.length}
                finalTotal={finalTotal}
                showBreakup={showBreakup}
                onToggleBreakup={() => setShowBreakup((v) => !v)}
                onBack={null}
                onClose={requestClose}
              />

              {/* Collapsible price breakup (chevron in header) */}
              {showBreakup && (
                <PriceBreakup
                  items={items}
                  mrpTotal={mrpTotal}
                  productDiscount={productDiscount}
                  couponDiscount={couponDiscount}
                  couponCode={appliedCoupon?.code}
                  subtotal={subtotal}
                  deliveryCharges={deliveryCharges}
                  tax={tax}
                  finalTotal={finalTotal}
                />
              )}

              <div className="flex-1 overflow-y-auto">
                {view === "delivery" && (
                  <DeliveryView
                    addr={selectedAddr}
                    delivery={delivery}
                    deliveryCharges={deliveryCharges}
                    finalTotal={finalTotal}
                    codAvailable={codAvailable}
                    payment={effectivePayment}
                    setPayment={setPayment}
                    method={method}
                    setMethod={setMethod}
                    onChangeAddress={() => setView(addresses.length === 0 ? "addrPincode" : "addrList")}
                    onOpenCoupons={() => setView("coupons")}
                  />
                )}
              </div>

              {/* Sticky PAY NOW bar */}
              {view === "delivery" && (
                <div className="border-t border-gray-200 p-3">
                  {hasStockIssue && (
                    <p className="text-xs text-red-600 font-medium mb-2 text-center">
                      {stockIssues[0].name} {stockIssues[0].available === 0 ? "is out of stock" : `has only ${stockIssues[0].available} left`}. Remove it to continue.
                    </p>
                  )}
                  <button
                    type="button"
                    disabled={placing || items.length === 0 || hasStockIssue}
                    onClick={onPay}
                    className="w-full flex items-center justify-between bg-orange-500 hover:bg-orange-600 disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold rounded-xl px-5 py-3.5 transition"
                  >
                    <span>{placing ? "Processing…" : hasStockIssue ? "OUT OF STOCK" : "PAY NOW"}</span>
                    <span>{fmt0(finalTotal)}</span>
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {askCancel && (
        <CancelNudge
          totalSaved={totalSaved}
          onContinue={() => setAskCancel(false)}
          onCancel={() => { setAskCancel(false); closeModal(); }}
        />
      )}
    </>,
    document.body
  );
}

// Map a saved address back into the editable form shape.
function mapAddrToForm(a) {
  return {
    type: a.type || "Home",
    pincode: a.pincode || "",
    city: a.city || "",
    state: a.state || "",
    building: a.building || a.line1 || "",
    area: a.area || a.line2 || "",
    name: a.name || "",
    mobile: a.mobile || a.phone || "",
  };
}

/* ---------------------------- Header ---------------------------- */
function SheetHeader({ logoSrc, totalSaved, itemCount, finalTotal, showBreakup, onToggleBreakup, onBack, onClose }) {
  return (
    <div className="flex items-center gap-2 px-4 py-3 border-b border-gray-100">
      {onBack ? (
        <button onClick={onBack} aria-label="Back" className="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-ink shrink-0">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
      ) : (
        <img src={logoSrc} alt="Logo" className="h-9 w-auto max-w-[120px] object-contain shrink-0" />
      )}
      <div className="flex-1 min-w-0">
        {totalSaved > 0 && (
          <span className="inline-block text-[11px] font-semibold text-green-700 bg-green-100 rounded-full px-2 py-0.5">
            {fmt0(totalSaved)} saved so far
          </span>
        )}
        <span className="text-[11px] text-brand-muted ml-2">{itemCount} item{itemCount !== 1 ? "s" : ""}</span>
      </div>
      <button onClick={onToggleBreakup} className="flex items-center gap-1 shrink-0" aria-label="Toggle price details">
        <span className="text-base font-bold text-brand-ink">{fmt(finalTotal)}</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" className={`text-brand-muted transition ${showBreakup ? "rotate-180" : ""}`}>
          <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
        </svg>
      </button>
      <button onClick={onClose} aria-label="Close" className="w-8 h-8 rounded-full hover:bg-red-50 flex items-center justify-center text-red-500 shrink-0">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6l12 12M6 18L18 6" /></svg>
      </button>
    </div>
  );
}

/* ---------------- Simple header (address screens) --------------- */
function SimpleHeader({ title, onBack, onClose }) {
  return (
    <div className="flex items-center gap-2 px-4 py-3 border-b border-gray-100">
      <button onClick={onBack} aria-label="Back" className="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-ink shrink-0">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <h2 className="flex-1 text-base font-bold text-brand-ink">{title}</h2>
      <button onClick={onClose} aria-label="Close" className="w-8 h-8 rounded-full hover:bg-red-50 flex items-center justify-center text-red-500 shrink-0">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 6l12 12M6 18L18 6" /></svg>
      </button>
    </div>
  );
}

/* ------------------------- Price breakup ------------------------ */
function PriceBreakup({ items, mrpTotal, productDiscount, couponDiscount, couponCode, subtotal, deliveryCharges, tax, finalTotal }) {
  const first = items[0];
  return (
    <div className="px-4 py-3 bg-gray-50 border-b border-gray-100">
      {first && (
        <div className="flex items-center gap-3 mb-3">
          <div className="w-12 h-12 bg-white rounded-lg border border-gray-100 flex items-center justify-center overflow-hidden shrink-0">
            <img src={first.image} alt={first.name} className="max-w-full max-h-full object-contain" />
          </div>
          <div className="min-w-0">
            <p className="text-sm font-semibold text-brand-ink line-clamp-1">{first.name}{items.length > 1 ? ` +${items.length - 1} more` : ""}</p>
            <p className="text-xs text-brand-muted">Qty: {first.qty}</p>
          </div>
        </div>
      )}
      <div className="space-y-1.5 text-sm">
        <Row label="MRP Total" value={fmt(mrpTotal)} />
        {productDiscount > 0 && <Row label="Discount on MRP" value={`-${fmt(productDiscount)}`} green />}
        {couponDiscount > 0 && <Row label={`Coupon${couponCode ? ` (${couponCode})` : ""}`} value={`-${fmt(couponDiscount)}`} green />}
        <Row label="Subtotal" value={fmt(subtotal)} bold />
        <Row label="Shipping Charges" value={deliveryCharges > 0 ? fmt(deliveryCharges) : "FREE"} green={deliveryCharges === 0} />
        {tax > 0 && <Row label="Tax (GST)" value={fmt(tax)} />}
        <div className="pt-2 mt-1 border-t border-gray-200">
          <Row label="Total" value={fmt(finalTotal)} bold big />
        </div>
      </div>
    </div>
  );
}

function Row({ label, value, green, bold, big }) {
  return (
    <div className="flex items-center justify-between">
      <span className={`${bold ? "font-bold text-brand-ink" : "text-brand-ink"} ${big ? "text-base" : ""}`}>{label}</span>
      <span className={`${green ? "text-green-600" : "text-brand-ink"} ${bold ? "font-bold" : "font-semibold"} ${big ? "text-base" : ""}`}>{value}</span>
    </div>
  );
}

/* --------------------------- Delivery --------------------------- */
function DeliveryView({ addr, delivery, deliveryCharges, finalTotal, codAvailable, payment, setPayment, method, setMethod, onChangeAddress, onOpenCoupons }) {
  return (
    <div className="px-4 py-4 space-y-4">
      <p className="text-xs font-bold uppercase tracking-wider text-brand-muted">Delivery Details</p>

      {/* Deliver-to card */}
      <div className="border border-gray-200 rounded-xl overflow-hidden">
        {addr ? (
          <div className="p-3 flex items-start justify-between gap-2">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <svg className="w-4 h-4 text-[#3684bf] shrink-0" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7m0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5" /></svg>
                <span className="text-sm font-bold text-brand-ink truncate">Deliver to {addr.name}</span>
                <span className="text-[10px] font-bold uppercase bg-gray-100 text-brand-muted px-1.5 py-0.5 rounded">{addr.type || "Home"}</span>
              </div>
              <p className="text-xs text-brand-muted mt-1 line-clamp-2">{addrLine(addr)}</p>
              {(addr.mobile || addr.phone) && <p className="text-xs text-brand-ink mt-1">📞 +91 {addr.mobile || addr.phone}</p>}
            </div>
            <button onClick={onChangeAddress} className="text-xs font-semibold border border-gray-300 rounded-lg px-3 py-1.5 hover:border-[#3684bf] hover:text-[#3684bf] shrink-0">
              Change
            </button>
          </div>
        ) : (
          /* Empty-address state — matches reference (house icon + Add Delivery Address). */
          <div className="p-6 flex flex-col items-center text-center">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" className="mb-2">
              <path d="M3 11.5 12 4l9 7.5" stroke="#f97316" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
              <path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9" stroke="#0b2545" strokeWidth="2" strokeLinejoin="round" />
              <rect x="10" y="14" width="4" height="6" fill="#3684bf" />
              <rect x="14.5" y="12" width="2.5" height="2.5" fill="#22c55e" />
            </svg>
            <p className="text-base font-bold text-brand-ink">No delivery address found</p>
            <p className="text-sm text-brand-muted mt-1 mb-4">You'll need to add a delivery address to continue</p>
            <button onClick={onChangeAddress} className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold rounded-xl px-6 py-3 transition">
              Add Delivery Address
            </button>
          </div>
        )}

        {/* Shipping method row */}
        <div className="border-t border-gray-100 p-3 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <svg className="w-5 h-5 text-brand-muted" viewBox="0 0 24 24" fill="currentColor"><path d="M20 8h-3V4H3a1 1 0 0 0-1 1v11h2a3 3 0 0 0 6 0h4a3 3 0 0 0 6 0h2v-5l-2-3M7 17.5A1.5 1.5 0 1 1 8.5 16 1.5 1.5 0 0 1 7 17.5m10 0A1.5 1.5 0 1 1 18.5 16 1.5 1.5 0 0 1 17 17.5M17 12V9.5h2.5L21 12Z" /></svg>
              <div>
                <p className="text-sm font-semibold text-brand-ink">Standard Delivery</p>
                <p className="text-[11px] text-brand-muted">
                  {delivery?.serviceable === false
                    ? "Not deliverable to this pincode"
                    : delivery?.eta
                      ? `Scheduled · arrives by ${delivery.eta}`
                      : "Scheduled for delivery"}
                </p>
              </div>
            </div>
            <span className={`text-sm font-bold ${deliveryCharges > 0 ? "text-green-700" : "text-green-600"}`}>
              {deliveryCharges > 0 ? fmt(deliveryCharges) : "FREE"}
            </span>
          </div>
      </div>

      {/* Offers & Rewards */}
      <div>
        <p className="text-xs font-bold uppercase tracking-wider text-brand-muted mb-2">Offers & Rewards</p>
        <button onClick={onOpenCoupons} className="w-full flex items-center justify-between border border-gray-200 rounded-xl px-4 py-3 hover:border-green-500 hover:bg-green-50 transition">
          <span className="flex items-center gap-2 text-green-700 font-semibold">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
            View Coupons
          </span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5"><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </div>

      {/* Payment options — full expanded block (COD + Pay Online sub-methods + Storedum seal) */}
      <div>
        <p className="text-xs font-bold uppercase tracking-wider text-brand-muted mb-2">Payment Options</p>
        <PaymentView
          finalTotal={finalTotal}
          codAvailable={codAvailable}
          payment={payment}
          setPayment={setPayment}
          method={method}
          setMethod={setMethod}
          bare
        />
      </div>
    </div>
  );
}

function CodOption({ codAvailable, payment, setPayment, amount }) {
  return (
    <label className={`flex items-center justify-between border rounded-2xl px-4 py-4 ${codAvailable ? "cursor-pointer border-gray-200 hover:border-[#3684bf]/50" : "bg-gray-50 cursor-not-allowed border-gray-200"}`}>
      <span className="flex items-center gap-3">
        <input type="radio" name="pay" disabled={!codAvailable} checked={payment === "cod"} onChange={() => codAvailable && setPayment("cod")} className="w-4 h-4 accent-[#3684bf]" />
        <span>
          <span className={`block text-base font-bold ${codAvailable ? "text-brand-ink" : "text-gray-400"}`}>Cash on Delivery</span>
          {!codAvailable && <span className="block text-xs text-red-500 font-medium">Not available for this order.</span>}
        </span>
      </span>
      {amount != null && <span className={`text-base font-bold ${codAvailable ? "text-brand-ink" : "text-gray-400"}`}>{fmt0(amount)}</span>}
    </label>
  );
}

// Payment-network logos (Paytm / PhonePe / GPay) shown next to "Pay Online".
function PayLogos() {
  return (
    <span className="flex items-center gap-1">
      <span className="text-[10px] font-bold text-[#00b9f1] bg-[#002970] rounded px-1 py-0.5">Paytm</span>
      <span className="w-4 h-4 rounded-full bg-[#5f259f] text-white text-[9px] font-bold flex items-center justify-center">P</span>
      <span className="w-4 h-4 rounded-full bg-white border border-gray-200 text-[9px] font-bold flex items-center justify-center">
        <span className="text-[#4285F4]">G</span>
      </span>
    </span>
  );
}

const cardIconPath = "M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m0 4H4V6h16zm0 10H4v-6h16z";
const bankIconPath = "M11.5 1 2 6v2h19V6M5 10v7H2v2h19v-2h-3v-7h-3v7h-3v-7H9v7H6v-7";

/* --------------------------- Payment ---------------------------- */
function PaymentView({ finalTotal, codAvailable, payment, setPayment, method, setMethod, bare }) {
  const online = payment === "online";
  const onlineMethods = [
    { id: "upi", label: "Pay by any UPI App / QR Code", icon: null },
    { id: "card", label: "Debit/Credit Cards", icon: cardIconPath },
    { id: "netbanking", label: "Net Banking", icon: bankIconPath },
  ];
  return (
    <div className={bare ? "space-y-3" : "px-4 py-4 space-y-3"}>
      <CodOption codAvailable={codAvailable} payment={payment} setPayment={setPayment} amount={finalTotal} />

      <div className={`border-2 rounded-2xl overflow-hidden ${online ? "border-[#3684bf]" : "border-gray-200"}`}>
        <button onClick={() => setPayment("online")} className="w-full flex items-center justify-between px-4 py-4">
          <span className="flex items-center gap-3">
            <input type="radio" readOnly checked={online} className="w-4 h-4 accent-[#3684bf]" />
            <span className="text-base font-bold text-brand-ink">Pay Online</span>
            <PayLogos />
          </span>
          <span className="text-base font-bold text-[#3684bf]">{fmt0(finalTotal)}</span>
        </button>

        {online && (
          <div className="border-t border-gray-100">
            {onlineMethods.map((m) => {
              const sel = method === m.id;
              return (
                <button
                  key={m.id}
                  onClick={() => setMethod(m.id)}
                  className={`w-full flex items-center justify-between px-4 py-3 border-b border-gray-50 last:border-0 ${sel ? "bg-[#3684bf]/5" : ""}`}
                >
                  <span className="flex items-center gap-3">
                    {m.id === "upi" ? (
                      <PayLogos />
                    ) : (
                      <svg className="w-5 h-5 text-brand-muted" viewBox="0 0 24 24" fill="currentColor"><path d={m.icon} /></svg>
                    )}
                    <span className={`text-sm ${sel ? "text-[#3684bf] font-semibold" : "text-brand-ink"}`}>{m.label}</span>
                  </span>
                  <span className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${sel ? "border-[#3684bf]" : "border-gray-300"}`}>
                    {sel && <span className="w-2 h-2 rounded-full bg-[#3684bf]" />}
                  </span>
                </button>
              );
            })}
          </div>
        )}
      </div>

      {/* Powered by Storedum seal */}
      <div className="flex flex-col items-center pt-3">
        <div className="flex items-center gap-1 text-brand-muted">
          <span className="text-[10px] font-semibold tracking-wide">Powered by</span>
        </div>
        <div className="flex items-center gap-1 mt-0.5">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="#374151"><path d="M12 2L2 22h20L12 2z" /></svg>
          <span className="text-sm font-bold text-gray-800 tracking-tight">Storedum</span>
        </div>
        <div className="flex items-center gap-4 mt-3 text-gray-500">
          <span className="text-[11px] font-medium flex items-center gap-1">✓ Verified</span>
          <span className="text-[11px] font-medium flex items-center gap-1">🔒 Secure</span>
          <span className="text-[11px] font-medium flex items-center gap-1">🛡 Protected</span>
        </div>
      </div>
    </div>
  );
}

/* ------------------------ Address list -------------------------- */
function AddressListView({ addresses, selectedId, onSelect, onAdd, onEdit }) {
  return (
    <div className="px-4 py-4">
      <div className="flex items-center justify-end mb-3">
        <button onClick={onAdd} className="flex items-center gap-1 text-sm font-semibold text-[#3684bf] border border-[#3684bf] rounded-lg px-3 py-1.5 hover:bg-[#3684bf]/5">
          <span className="text-lg leading-none">+</span> Add New Address
        </button>
      </div>
      {addresses.length === 0 ? (
        <p className="text-sm text-brand-muted py-8 text-center">No saved addresses. Add one to continue.</p>
      ) : (
        <div className="space-y-2">
          {addresses.map((a) => (
            <div key={a.id} className={`relative border rounded-xl p-3 cursor-pointer ${selectedId === a.id ? "border-[#3684bf] bg-[#3684bf]/5" : "border-gray-200 hover:border-[#3684bf]/50"}`} onClick={() => onSelect(a.id)}>
              <div className="flex items-center gap-2 mb-1">
                <input type="radio" readOnly checked={selectedId === a.id} className="accent-[#3684bf]" />
                <span className="text-sm font-bold text-brand-ink">{a.name}</span>
                <span className="text-[10px] font-bold uppercase bg-gray-100 text-brand-muted px-1.5 py-0.5 rounded">{a.type || "Home"}</span>
                {a.isDefault && <span className="text-[10px] font-bold uppercase bg-blue-50 text-[#3684bf] px-1.5 py-0.5 rounded">Default</span>}
                <button onClick={(e) => { e.stopPropagation(); onEdit(a); }} aria-label="Edit" className="ml-auto w-7 h-7 rounded-full hover:bg-gray-100 flex items-center justify-center text-brand-muted">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z" /></svg>
                </button>
              </div>
              <p className="text-xs text-brand-muted pl-6">{addrLine(a)}</p>
              {(a.mobile || a.phone) && <p className="text-xs text-brand-ink pl-6 mt-0.5">+91 {a.mobile || a.phone}</p>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* --------------------- Pincode-first gate ----------------------- */
function PincodeGateView({ onServiceable }) {
  const [pin, setPin] = useState("");
  const [checking, setChecking] = useState(false);
  const [locating, setLocating] = useState(false);
  const [error, setError] = useState("");

  // Check serviceability for a pincode, then autofill City/State. `geo` may be pre-filled
  // (from geolocation) to skip the extra India Post lookup.
  const verify = async (code, geo = null) => {
    setChecking(true);
    setError("");
    try {
      const d = await api.checkDelivery(code);
      if (!d?.serviceable) {
        setError("We don't deliver to this pincode yet. Try another.");
        return;
      }
      // Always resolve the locality list (for the Area dropdown), even when geolocation
      // pre-filled city/state.
      const g = geo?.areas ? geo : (await lookupPincode(code));
      onServiceable(code, g?.city || "", g?.state || "", g?.areas || []);
    } catch {
      setError("Couldn't check this pincode. Please try again.");
    } finally {
      setChecking(false);
    }
  };

  const proceed = () => { if (pin.length === 6) verify(pin); };

  const useLocation = async () => {
    setLocating(true);
    setError("");
    try {
      const { pincode, city, state } = await detectCurrentPincode();
      setPin(pincode);
      await verify(pincode, { city, state });
    } catch (e) {
      setError(e.message || "Couldn't detect your location.");
    } finally {
      setLocating(false);
    }
  };

  const busy = checking || locating;

  return (
    <div className="px-4 py-4">
      <p className="text-sm text-brand-muted mb-4">Please enter your pincode to check delivery availability and proceed with adding your address.</p>

      <label className="text-xs font-bold uppercase tracking-wider text-[#3684bf]">Pincode</label>
      <input
        type="text"
        inputMode="numeric"
        maxLength={6}
        placeholder="000000"
        value={pin}
        onChange={(e) => { setPin(e.target.value.replace(/\D/g, "").slice(0, 6)); setError(""); }}
        className="mt-1 w-full border-2 border-[#3684bf] rounded-xl px-4 py-3 text-lg tracking-widest focus:outline-none"
      />

      <div className="flex items-center gap-3 my-3">
        <span className="flex-1 h-px bg-gray-200" />
        <span className="text-xs font-semibold text-brand-muted">OR</span>
        <span className="flex-1 h-px bg-gray-200" />
      </div>

      <button
        disabled={busy}
        onClick={useLocation}
        className="w-full flex items-center justify-center gap-2 border-2 border-[#3684bf] text-[#3684bf] font-bold rounded-xl py-3 hover:bg-[#3684bf]/5 disabled:opacity-60 transition"
      >
        <svg className="w-4 h-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7m0 9.5a2.5 2.5 0 0 1 0-5 2.5 2.5 0 0 1 0 5" /></svg>
        {locating ? "Detecting…" : "Use Current Location"}
      </button>

      {error && <p className="mt-3 text-xs text-red-600">{error}</p>}

      <button
        disabled={pin.length !== 6 || busy}
        onClick={proceed}
        className="mt-4 w-full bg-[#3684bf] disabled:bg-gray-200 disabled:text-gray-400 text-white font-bold rounded-xl py-3 transition"
      >
        {checking ? "Checking…" : "Continue"}
      </button>
    </div>
  );
}

/* ------------------------ Address form -------------------------- */
function AddressFormView({ form, setForm, isEdit, onSave }) {
  const { showToast } = useUI();
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
  const valid = form.pincode && form.city && form.state && form.building && form.area && form.name && form.mobile;
  const [scanning, setScanning] = useState(false);
  const [error, setError] = useState("");

  const handleSave = async () => {
    setScanning(true);
    setError("");
    // Brief verification step (re-checks serviceability + locality), shown as "Scanning address…".
    const [res] = await Promise.all([
      Promise.resolve(onSave()),
      new Promise((r) => setTimeout(r, 1400)), // keep the loader visible long enough to read
    ]);
    if (res && res.ok === false) {
      const m = res.error || "Couldn't verify this address.";
      setError(m);
      showToast?.(m, "error");
      setScanning(false);
      return;
    }
    // Success: parent already navigated away; leave scanning true so it doesn't flash back.
  };

  if (scanning) return <ScanningAddress />;

  return (
    <div className="px-4 py-4 space-y-4">
      <div>
        <p className="text-xs font-semibold text-brand-muted mb-2">Save Address As</p>
        <div className="flex gap-2">
          {["Home", "Work"].map((t) => (
            <button key={t} onClick={() => setForm((f) => ({ ...f, type: t }))} className={`flex-1 border rounded-xl py-2 text-sm font-semibold ${form.type === t ? "border-[#3684bf] text-[#3684bf] bg-[#3684bf]/5" : "border-gray-200 text-brand-muted"}`}>
              {t === "Home" ? "🏠 " : "💼 "}{t}
            </button>
          ))}
        </div>
      </div>

      <div>
        <p className="text-sm font-semibold text-brand-ink mb-3">Address Details</p>
        <div className="grid grid-cols-2 gap-3">
          <Field className="col-span-2" label="Pincode *" value={form.pincode} onChange={set("pincode")} readOnly />
          <Field label="City *" value={form.city} onChange={set("city")} />
          <Field label="State *" value={form.state} onChange={set("state")} />
          <Field className="col-span-2" label="Flat, House no., Building, Company *" value={form.building} onChange={set("building")} />
          <AreaField className="col-span-2" value={form.area} areas={form.areas} onChange={(v) => setForm((f) => ({ ...f, area: v }))} />
        </div>
      </div>

      <div className="space-y-3">
        <p className="text-sm font-semibold text-brand-ink">Customer Information</p>
        <Field label="Full Name *" value={form.name} onChange={set("name")} />
        <Field label="Mobile Number *" value={form.mobile} inputMode="numeric" maxLength={10} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value.replace(/\D/g, "").slice(0, 10) }))} />
      </div>

      {error && <p className="text-xs text-red-600 text-center">{error}</p>}

      <button disabled={!valid} onClick={handleSave} className="w-full bg-[#3684bf] disabled:bg-gray-200 disabled:text-gray-400 text-white font-bold rounded-xl py-3 transition">
        {isEdit ? "Update Address" : "Save Address"}
      </button>
    </div>
  );
}

// "Scanning address…" loader shown while a saved address is verified (matches reference).
function ScanningAddress() {
  return (
    <div className="px-6 py-16 flex flex-col items-center text-center">
      <div className="text-5xl mb-5 animate-pulse">🗺️🔍</div>
      <h3 className="text-xl font-bold text-brand-ink mb-2">Scanning address…</h3>
      <p className="text-sm text-brand-muted max-w-xs">
        We are verifying your address to ensure smooth and guaranteed delivery.
      </p>
      <div className="mt-6 flex gap-1.5">
        <span className="w-2 h-2 rounded-full bg-[#3684bf] animate-bounce" style={{ animationDelay: "0ms" }} />
        <span className="w-2 h-2 rounded-full bg-[#3684bf] animate-bounce" style={{ animationDelay: "150ms" }} />
        <span className="w-2 h-2 rounded-full bg-[#3684bf] animate-bounce" style={{ animationDelay: "300ms" }} />
      </div>
    </div>
  );
}

// Floating-label input (label sits on the border, like the reference Material-style form).
// `placeholder=" "` drives the :placeholder-shown state so the label drops into the field
// when empty + unfocused and floats up to the border once filled or focused.
// Area / locality selector. When the pincode's official localities were returned (`areas`),
// the user PICKS one from a dropdown (real by construction — this is what blocks fake/typo
// localities). An "Other (type manually)…" option reveals a free-text input for localities
// India Post doesn't list. With no list (offline / unknown pincode) it's a plain text field.
function AreaField({ value, areas = [], onChange, className = "" }) {
  const hasList = Array.isArray(areas) && areas.length > 0;
  const isOther = !hasList || (value && !areas.includes(value));
  const [manual, setManual] = useState(isOther);

  if (!hasList) {
    return <Field className={className} label="Area, Colony, Street, Sector, Village *" value={value} onChange={(e) => onChange(e.target.value)} />;
  }
  return (
    <div className={`${className} space-y-2`}>
      <div className="relative">
        <select
          value={manual ? "__other__" : (value || "")}
          onChange={(e) => {
            const v = e.target.value;
            if (v === "__other__") { setManual(true); onChange(""); }
            else { setManual(false); onChange(v); }
          }}
          className="peer w-full border border-gray-300 rounded-lg px-3 pt-3 pb-2 text-sm text-brand-ink bg-white focus:outline-none focus:border-[#3684bf] focus:ring-1 focus:ring-[#3684bf] appearance-none"
        >
          <option value="" disabled>Select your area</option>
          {areas.map((a) => <option key={a} value={a}>{a}</option>)}
          <option value="__other__">Other (type manually)…</option>
        </select>
        <label className="pointer-events-none absolute left-2.5 -top-2 px-1 bg-white text-xs text-[#3684bf]">Area, Colony, Street, Sector, Village *</label>
        <span className="pointer-events-none absolute right-3 top-3 text-brand-muted">▾</span>
      </div>
      {manual && (
        <Field label="Type your area / locality *" value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </div>
  );
}

function Field({ label, value, onChange, readOnly, inputMode, maxLength, className = "" }) {
  return (
    <div className={`relative ${className}`}>
      <input
        value={value}
        onChange={onChange}
        readOnly={readOnly}
        inputMode={inputMode}
        maxLength={maxLength}
        placeholder=" "
        className={`peer w-full border rounded-lg px-3 pt-3 pb-2 text-sm focus:outline-none focus:border-[#3684bf] focus:ring-1 focus:ring-[#3684bf] ${readOnly ? "bg-gray-50 border-gray-200 text-brand-muted" : "border-gray-300 text-brand-ink"}`}
      />
      <label
        className="pointer-events-none absolute left-2.5 top-2.5 px-1 bg-white text-sm text-brand-muted transition-all
          peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-sm
          peer-focus:-top-2 peer-focus:text-xs peer-focus:text-[#3684bf]
          peer-[:not(:placeholder-shown)]:-top-2 peer-[:not(:placeholder-shown)]:text-xs"
      >
        {label}
      </label>
    </div>
  );
}

/* --------------------------- Coupons ---------------------------- */
function CouponsView({ coupons, subtotal, appliedCoupon, applyCoupon, removeCoupon, onDone }) {
  const [code, setCode] = useState("");
  const [msg, setMsg] = useState("");

  const applyStatic = (c) => {
    if (subtotal < c.minSubtotal) { setMsg(`Add ${fmt0(c.minSubtotal - subtotal)} more to use ${c.code}.`); return; }
    applyCoupon(c);
    setMsg(`${c.code} applied.`);
    onDone();
  };

  const applyTyped = async () => {
    const cc = code.trim().toUpperCase();
    if (!cc) return;
    try {
      const res = await api.validateCoupon(cc, Math.round(subtotal));
      if (res.valid) {
        applyCoupon({
          code: res.code, minSubtotal: 0,
          discount: { type: res.type === "percent" ? "percent" : "flat", value: res.value },
          serverDiscount: res.discount,
        });
        setMsg(res.message || `${cc} applied.`);
        onDone();
        return;
      }
      setMsg(res.message || `Invalid coupon "${cc}".`);
    } catch {
      const match = coupons.find((c) => c.code === cc);
      if (match) return applyStatic(match);
      setMsg(`Invalid coupon "${cc}".`);
    }
  };

  return (
    <div className="px-4 py-4">
      {/* Code entry */}
      <div className="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" strokeWidth="2" className="shrink-0"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
        <input
          value={code}
          onChange={(e) => setCode(e.target.value.toUpperCase())}
          onKeyDown={(e) => e.key === "Enter" && applyTyped()}
          placeholder="ENTER COUPON CODE"
          className="flex-1 min-w-0 text-sm focus:outline-none uppercase tracking-wide"
        />
        {code && <button onClick={applyTyped} className="text-[#3684bf] font-semibold text-sm">Apply</button>}
      </div>
      {msg && <p className={`mt-2 text-xs ${msg.includes("applied") ? "text-green-600" : "text-red-600"}`}>{msg}</p>}

      {appliedCoupon && (
        <div className="mt-3 flex items-center justify-between border border-green-500 bg-green-50 rounded-lg px-4 py-2.5">
          <span className="text-sm text-green-700 font-semibold">{appliedCoupon.code} applied</span>
          <button onClick={() => { removeCoupon(); setMsg(""); }} className="text-xs text-red-600 font-semibold">Remove</button>
        </div>
      )}

      {/* Available coupons */}
      <p className="text-xs font-bold uppercase tracking-wider text-brand-muted mt-5 mb-2">Available Coupons</p>
      {coupons.length === 0 ? (
        <p className="text-sm text-brand-muted py-6 text-center">No coupons available.</p>
      ) : (
        <ul className="space-y-2">
          {coupons.map((c) => {
            const ok = subtotal >= c.minSubtotal;
            return (
              <li key={c.code} className={`border rounded-lg p-3 flex items-start gap-3 ${ok ? "border-gray-200" : "border-gray-200 opacity-70"}`}>
                <div className="w-9 h-9 rounded-md bg-[#3684bf]/10 text-[#3684bf] flex items-center justify-center shrink-0">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></svg>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-bold text-brand-ink text-sm">{c.code}</p>
                  {c.desc && <p className="text-xs text-brand-muted mt-0.5">{c.desc}</p>}
                  {!ok && <p className="text-[11px] text-red-600 mt-0.5">Add {fmt0(c.minSubtotal - subtotal)} more to use this.</p>}
                </div>
                <button onClick={() => applyStatic(c)} disabled={!ok} className={`shrink-0 text-xs font-bold uppercase px-3 py-1.5 rounded ${ok ? "text-[#3684bf] hover:bg-blue-50" : "text-gray-400 cursor-not-allowed"}`}>
                  Apply
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

/* ------------------------ Cancel nudge -------------------------- */
function CancelNudge({ totalSaved, onContinue, onCancel }) {
  return (
    <div className="fixed inset-0 z-[1200] bg-black/50 flex items-end sm:items-center justify-center p-4" onClick={onContinue}>
      <div className="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-center" onClick={(e) => e.stopPropagation()}>
        <div className="w-12 h-12 mx-auto rounded-full bg-orange-100 text-orange-500 flex items-center justify-center mb-3">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 9v4M12 17h.01" /><circle cx="12" cy="12" r="10" /></svg>
        </div>
        <h3 className="text-lg font-bold text-brand-ink mb-3">Cancel checkout?</h3>
        {totalSaved > 0 && (
          <div className="bg-green-50 border border-green-100 rounded-lg py-2 px-3 text-sm font-semibold text-green-700 mb-2">
            💡 You're saving {fmt0(totalSaved)}
          </div>
        )}
        <p className="text-xs text-brand-muted mb-5">You'll lose this deal if you leave now</p>
        <div className="flex gap-3">
          <button onClick={onContinue} className="flex-1 border border-gray-300 rounded-xl py-2.5 font-semibold text-brand-ink hover:bg-gray-50">Continue</button>
          <button onClick={onCancel} className="flex-1 bg-[#3684bf] hover:bg-[#1f5f96] text-white rounded-xl py-2.5 font-semibold">Cancel</button>
        </div>
      </div>
    </div>
  );
}

/* --------------------------- Done ------------------------------- */
function DonePanel({ orderId, onClose }) {
  return (
    <div className="text-center px-6 py-10">
      <div className="w-16 h-16 mx-auto rounded-full bg-green-100 text-green-600 flex items-center justify-center mb-4">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><path d="M5 12l5 5L20 7" /></svg>
      </div>
      <h2 className="text-xl font-bold text-brand-ink mb-1">Order Placed!</h2>
      <p className="text-sm text-brand-muted mb-1">Your order ID is</p>
      <p className="text-lg font-bold text-brand-navy mb-6">{orderId}</p>
      <p className="text-xs text-brand-muted mb-6 max-w-sm mx-auto">
        You'll receive a confirmation on the registered phone & email.
      </p>
      <button onClick={onClose} className="bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold px-6 py-3 rounded-xl">Continue Shopping</button>
    </div>
  );
}
