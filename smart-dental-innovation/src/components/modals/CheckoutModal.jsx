import { useEffect, useState } from "react";
import Modal from "../ui/Modal";
import Button from "../ui/Button";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useAuth } from "../../context/AuthContext";
import api, { loadRazorpayScript } from "../../lib/api";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;
const initialAddress = { fullName: "", phone: "", line1: "", city: "", state: "", pincode: "" };

// Map a saved account address (from AddressPage) -> checkout form shape.
function mapSavedAddress(a) {
  if (!a) return null;
  const line1 = [a.line1, a.line2, a.landmark, a.building].filter(Boolean).join(", ");
  return { fullName: a.name || "", phone: a.mobile || "", line1, city: a.city || "", state: a.state || "", pincode: a.pincode || "" };
}

export default function CheckoutModal() {
  const { modal, closeModal, showToast, openModal } = useUI();
  const { items, pricing, appliedCoupon, clearCart, setDeliveryPincode } = useCart();
  const orderTotal = pricing.finalTotal;   // server-mirrored total (incl. coupon/bulk/shipping/tax)
  const { user, token } = useAuth();
  const [step, setStep] = useState(1);
  const [address, setAddress] = useState(initialAddress);
  const [payment, setPayment] = useState("online");
  const [orderId, setOrderId] = useState(null);
  const [placing, setPlacing] = useState(false);
  const [selectedAddrId, setSelectedAddrId] = useState(null);

  useEffect(() => {
    if (modal !== "checkout") return;
    setStep(1);
    setOrderId(null);
    // Pre-fill from the saved default address (falls back to name/phone only).
    const saved = user?.addresses || [];
    const def = saved.find((a) => a.isDefault) || saved[0];
    const mapped = mapSavedAddress(def);
    setSelectedAddrId(def?.id || null);
    setAddress((a) =>
      mapped
        ? { ...a, ...mapped }
        : { ...a, fullName: user?.name || a.fullName, phone: user?.mobile || user?.phone || a.phone }
    );
  }, [modal, user]);

  // Keep the cart's shipping quote in sync with the checkout destination pincode so the
  // total shown here matches what the server will charge for this address.
  useEffect(() => {
    const pin = (address.pincode || "").replace(/\D/g, "");
    if (pin.length === 6) setDeliveryPincode(pin);
  }, [address.pincode, setDeliveryPincode]);

  if (modal !== "checkout") return null;

  const onAddrChange = (k) => (e) => { setSelectedAddrId(null); setAddress((a) => ({ ...a, [k]: e.target.value })); };

  // Fill the form from a chosen saved address.
  const selectSaved = (a) => {
    const mapped = mapSavedAddress(a);
    setSelectedAddrId(a.id);
    if (mapped) setAddress((prev) => ({ ...prev, ...mapped }));
  };
  const addressValid = address.fullName && address.phone && address.line1 && address.city && address.state && address.pincode;

  const buildPayload = (paymentMethod) => ({
    // The server re-prices every line authoritatively (ignoring `price` here) and
    // recomputes discount/shipping/tax from the coupon code + settings. We only tell it
    // WHICH coupon to try; type/offerId/parentId let it validate offer + free-gift lines.
    items: items.map((i) => ({
      id: i.id, name: i.name, price: i.price, qty: i.qty, variant: i.variant,
      type: i.type || "product", offerId: i.offerId, parentId: i.parentId,
    })),
    address,
    paymentMethod,
    couponCode: appliedCoupon?.code || null,
  });

  const confirm = async () => {
    if (!token) { showToast?.("Please log in to complete your order.", "info"); openModal("auth"); return; }
    if (payment === "online") return payOnline();

    // Cash on Delivery — order is created unpaid, no gateway step.
    setPlacing(true);
    try {
      const order = await api.placeOrder(buildPayload("cod"));
      setOrderId(order.orderId);
      clearCart();
      setStep(3);
    } catch (err) {
      console.error("[checkout] order failed:", err.message);
      showToast?.("Could not place your order. Please try again.", "error");
    } finally {
      setPlacing(false);
    }
  };

  // Online flow: create a pending order, create a Razorpay order, open the hosted
  // checkout widget, then verify the signature server-side before confirming.
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
        name: "Smart Dental Innovations",
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
            // Payment went through at the gateway; the webhook will reconcile it.
            console.warn("[checkout] verify failed, webhook will reconcile:", err.message);
            showToast?.("Payment received — confirming your order shortly.", "info");
          } finally {
            setOrderId(order.orderId);
            clearCart();
            setStep(3);
            setPlacing(false);
          }
        },
        modal: {
          ondismiss: () => {
            setPlacing(false);
            showToast?.("Payment not completed — your order is saved as pending. Retry from My Orders.", "info");
          },
        },
      });
      rz.on("payment.failed", () => {
        showToast?.("Payment failed — your order is saved as pending, you can retry.", "error");
      });
      rz.open();
    } catch (err) {
      console.error("[checkout] online payment failed:", err.message);
      setPlacing(false);
      showToast?.(
        order
          ? "Couldn't start the payment. Your order is saved as pending — retry from My Orders."
          : "Could not place your order. Please try again.",
        "error"
      );
    }
  };

  return (
    <Modal open={true} onClose={closeModal} maxWidth="max-w-2xl">
      <div className="p-5 sm:p-6">
        {/* Stepper */}
        <div className="flex items-center gap-2 mb-6">
          {["Address", "Payment", "Confirm"].map((label, i) => {
            const s = i + 1;
            const active = step === s;
            const done = step > s;
            return (
              <div key={label} className="flex items-center gap-2 flex-1">
                <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ${active ? "bg-brand-navy text-white" : done ? "bg-brand-orange text-white" : "bg-gray-200 text-brand-muted"}`}>
                  {done ? "✓" : s}
                </div>
                <span className={`text-xs font-semibold ${active || done ? "text-brand-ink" : "text-brand-muted"}`}>{label}</span>
                {i < 2 && <div className={`flex-1 h-0.5 ${done ? "bg-brand-orange" : "bg-gray-200"}`} />}
              </div>
            );
          })}
        </div>

        {step === 1 && (
          <>
            <h2 className="text-lg font-bold mb-4">Shipping Address</h2>
            {user?.addresses?.length > 0 && (
              <div className="mb-4">
                <p className="text-sm font-semibold text-brand-ink mb-2">Deliver to a saved address</p>
                <div className="space-y-2">
                  {user.addresses.map((a) => (
                    <button
                      key={a.id}
                      type="button"
                      onClick={() => selectSaved(a)}
                      className={`w-full text-left p-3 border rounded-lg ${selectedAddrId === a.id ? "border-brand-navy bg-brand-navy/5" : "border-gray-300 hover:border-brand-navy/50"}`}
                    >
                      <div className="flex items-center gap-2 mb-1">
                        <span className="text-[10px] font-bold uppercase bg-blue-50 text-[#3684bf] px-2 py-0.5 rounded">{a.type || "Home"}</span>
                        {a.isDefault && <span className="text-[10px] font-bold uppercase bg-green-50 text-green-700 px-2 py-0.5 rounded">Default</span>}
                      </div>
                      <p className="text-sm font-semibold text-brand-ink">{a.name} - +91 {a.mobile}</p>
                      <p className="text-xs text-brand-muted">{[a.line1, a.line2, a.landmark, a.building, a.city, a.district, a.state, a.pincode].filter(Boolean).join(", ")}</p>
                    </button>
                  ))}
                </div>
                <p className="text-xs text-brand-muted mt-2">Or edit the fields below.</p>
              </div>
            )}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <input className="border border-gray-300 rounded-md px-3 py-2 text-sm sm:col-span-2" placeholder="Full Name" value={address.fullName} onChange={onAddrChange("fullName")} />
              <input className="border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Phone Number" value={address.phone} onChange={onAddrChange("phone")} />
              <input className="border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Pincode" value={address.pincode} onChange={onAddrChange("pincode")} />
              <input className="border border-gray-300 rounded-md px-3 py-2 text-sm sm:col-span-2" placeholder="Address Line" value={address.line1} onChange={onAddrChange("line1")} />
              <input className="border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="City" value={address.city} onChange={onAddrChange("city")} />
              <input className="border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="State" value={address.state} onChange={onAddrChange("state")} />
            </div>
            <div className="mt-6 flex justify-end gap-2">
              <Button variant="outline" onClick={closeModal}>Cancel</Button>
              <Button variant="primary" disabled={!addressValid} onClick={() => setStep(2)}>Continue to Payment</Button>
            </div>
          </>
        )}

        {step === 2 && (
          <>
            <h2 className="text-lg font-bold mb-4">Payment Method</h2>
            <div className="space-y-2">
              {[
                { id: "online", label: "Pay Online", desc: "UPI, Cards, Netbanking & Wallets — secure payment via Razorpay" },
                { id: "cod", label: "Cash on Delivery", desc: "Pay when the order arrives" },
              ].map((opt) => (
                <label key={opt.id} className={`flex items-start gap-3 p-3 border rounded-lg cursor-pointer ${payment === opt.id ? "border-brand-navy bg-brand-navy/5" : "border-gray-300 hover:border-brand-navy/50"}`}>
                  <input type="radio" name="pay" value={opt.id} checked={payment === opt.id} onChange={() => setPayment(opt.id)} className="mt-1" />
                  <div>
                    <p className="text-sm font-semibold text-brand-ink">{opt.label}</p>
                    <p className="text-xs text-brand-muted">{opt.desc}</p>
                  </div>
                </label>
              ))}
            </div>
            <div className="mt-5 p-4 bg-gray-50 rounded-lg flex items-center justify-between text-sm">
              <span className="text-brand-muted">Order Total ({items.length} items)</span>
              <span className="font-bold text-brand-ink text-lg">{fmt(orderTotal)}</span>
            </div>
            <div className="mt-6 flex justify-between gap-2">
              <Button variant="ghost" onClick={() => setStep(1)}>← Back</Button>
              <Button variant="primary" onClick={confirm} disabled={placing}>
                {placing ? "Processing…" : payment === "online" ? `Pay ${fmt(orderTotal)}` : "Place Order"}
              </Button>
            </div>
          </>
        )}

        {step === 3 && (
          <div className="text-center py-6">
            <div className="w-16 h-16 mx-auto rounded-full bg-green-100 text-green-600 flex items-center justify-center mb-4">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><path d="M5 12l5 5L20 7" /></svg>
            </div>
            <h2 className="text-xl font-bold text-brand-ink mb-1">Order Placed!</h2>
            <p className="text-sm text-brand-muted mb-1">Your order ID is</p>
            <p className="text-lg font-bold text-brand-navy mb-6">{orderId}</p>
            <p className="text-xs text-brand-muted mb-6 max-w-sm mx-auto">
              You'll receive a confirmation on the registered phone & email. Estimated delivery: 3–5 business days.
            </p>
            <Button variant="primary" size="lg" onClick={closeModal}>Continue Shopping</Button>
          </div>
        )}
      </div>
    </Modal>
  );
}
