import { useEffect, useState } from "react";
import Modal from "../ui/Modal";
import Button from "../ui/Button";
import { useUI } from "../../context/UIContext";
import { useCart } from "../../context/CartContext";
import { useAuth } from "../../context/AuthContext";

const fmt = (n) => `₹${n.toLocaleString("en-IN")}`;
const initialAddress = { fullName: "", phone: "", line1: "", city: "", state: "", pincode: "" };

export default function CheckoutModal() {
  const { modal, closeModal } = useUI();
  const { items, subtotal, clearCart } = useCart();
  const { user } = useAuth();
  const [step, setStep] = useState(1);
  const [address, setAddress] = useState(initialAddress);
  const [payment, setPayment] = useState("upi");
  const [orderId, setOrderId] = useState(null);

  useEffect(() => {
    if (modal !== "checkout") return;
    setStep(1);
    setOrderId(null);
    setAddress((a) => ({ ...a, fullName: user?.name || a.fullName }));
  }, [modal, user]);

  if (modal !== "checkout") return null;

  const onAddrChange = (k) => (e) => setAddress((a) => ({ ...a, [k]: e.target.value }));
  const addressValid = address.fullName && address.phone && address.line1 && address.city && address.state && address.pincode;

  const confirm = () => {
    setOrderId(`SDI-${Math.floor(100000 + Math.random() * 900000)}`);
    clearCart();
    setStep(3);
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
                { id: "upi", label: "UPI / GPay / PhonePe", desc: "Pay instantly from your bank app" },
                { id: "card", label: "Credit / Debit Card", desc: "Visa, Mastercard, RuPay accepted" },
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
              <span className="font-bold text-brand-ink text-lg">{fmt(subtotal)}</span>
            </div>
            <div className="mt-6 flex justify-between gap-2">
              <Button variant="ghost" onClick={() => setStep(1)}>← Back</Button>
              <Button variant="primary" onClick={confirm}>Place Order</Button>
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
