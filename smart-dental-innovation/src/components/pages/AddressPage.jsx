import { useState } from "react";
import { createPortal } from "react-dom";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";

export default function AddressPage() {
  const { user, addAddress, removeAddress } = useAuth();
  const { navigate, openModal } = useUI();
  const [drawerOpen, setDrawerOpen] = useState(false);

  if (!user) {
    return (
      <div className="max-w-[1200px] mx-auto px-4 py-12 text-center">
        <p className="text-brand-muted mb-4">Please sign in.</p>
        <button onClick={() => openModal("auth")} className="bg-[#3684bf] text-white font-bold px-6 py-2.5 rounded-md">
          Sign In
        </button>
      </div>
    );
  }

  const addresses = user.addresses || [];

  return (
    <div className="max-w-[1200px] mx-auto px-4 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <aside className="lg:col-span-1">
          <AccountSidebar active="address" />
        </aside>

        <section className="lg:col-span-2">
          <div className="flex items-center justify-between mb-5">
            <div className="flex items-center gap-3">
              <button onClick={() => navigate("account")} aria-label="Back" className="text-red-500">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                  <path d="M19 12H5M12 19l-7-7 7-7" />
                </svg>
              </button>
              <h2 className="text-xl font-bold text-brand-ink">Saved Address</h2>
            </div>
            <button
              onClick={() => setDrawerOpen(true)}
              className="flex items-center gap-2 border border-[#3684bf] text-[#3684bf] font-bold px-5 py-2 rounded-md hover:bg-blue-50"
            >
              ADD <span className="text-lg leading-none">+</span>
            </button>
          </div>

          {addresses.length === 0 ? (
            <div className="bg-gray-100 rounded-2xl py-16 px-4 flex flex-col items-center text-center">
              <div className="text-6xl mb-3">🏠</div>
              <h3 className="text-xl font-bold text-brand-ink mb-1">No addresses added</h3>
              <p className="text-sm text-brand-muted">Add a new delivery address</p>
            </div>
          ) : (
            <ul className="space-y-3">
              {addresses.map((a) => (
                <li key={a.id} className="border border-gray-200 rounded-xl p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="text-xs font-bold uppercase bg-blue-50 text-[#3684bf] px-2 py-0.5 rounded">{a.type || "Home"}</span>
                        {a.isDefault && (
                          <span className="text-xs font-bold uppercase bg-green-50 text-green-700 px-2 py-0.5 rounded">Default</span>
                        )}
                      </div>
                      <p className="font-semibold text-brand-ink">{a.name}</p>
                      <p className="text-sm text-brand-muted">+91 {a.mobile}</p>
                      <p className="text-sm text-brand-ink mt-1">
                        {[a.line1, a.line2, a.landmark, a.building].filter(Boolean).join(", ")}
                      </p>
                      <p className="text-sm text-brand-ink">
                        {[a.city, a.district, a.state, a.pincode].filter(Boolean).join(", ")}
                      </p>
                    </div>
                    <button
                      onClick={() => removeAddress(a.id)}
                      aria-label="Remove"
                      className="text-red-500 hover:bg-red-50 rounded-full p-2 shrink-0"
                    >
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M6 6l12 12M18 6L6 18" />
                      </svg>
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      {drawerOpen && (
        <AddAddressDrawer
          defaultName={user.name}
          defaultMobile={user.mobile}
          onClose={() => setDrawerOpen(false)}
          onSave={(addr) => { addAddress(addr); setDrawerOpen(false); }}
        />
      )}
    </div>
  );
}

function AccountSidebar({ active }) {
  const { user, logout } = useAuth();
  const { navigate } = useUI();
  const [signoutOpen, setSignoutOpen] = useState(false);

  const items = [
    { id: "orders", label: "My Orders", icon: "M19 7h-3V5.5C16 3.57 14.43 2 12.5 2h-1C9.57 2 8 3.57 8 5.5V7H5c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM10 5.5c0-.83.67-1.5 1.5-1.5h1c.83 0 1.5.67 1.5 1.5V7h-4V5.5z", go: () => navigate("orders") },
    { id: "wishlist", label: "Wishlist", icon: "M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z", go: () => navigate("wishlist") },
    { id: "address", label: "Address", icon: "M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z", go: () => navigate("address") },
    { id: "signout", label: "Sign Out", icon: "M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3h-8c-1.1 0-2 .9-2 2v4h2V5h8v14h-8v-4h-2v4c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z", go: () => setSignoutOpen(true) },
  ];

  return (
    <>
      <div className="border border-gray-200 rounded-xl p-5 mb-4">
        <div className="flex items-center gap-3">
          <div className="w-14 h-14 rounded-full bg-gray-200 flex items-center justify-center">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" className="text-gray-500">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
            </svg>
          </div>
          <div>
            <h3 className="font-bold text-brand-ink uppercase tracking-wide">{user.name}</h3>
            <p className="text-xs text-brand-muted">+91-{user.mobile}</p>
          </div>
        </div>
      </div>
      <div className="border border-gray-200 rounded-xl overflow-hidden divide-y divide-gray-100">
        {items.map((it) => (
          <button
            key={it.id}
            onClick={it.go}
            className={`w-full flex items-center gap-3 px-5 py-4 hover:bg-gray-50 text-left ${active === it.id ? "bg-gray-50" : ""}`}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#3684bf"><path d={it.icon} /></svg>
            <span className="font-semibold text-brand-ink">{it.label}</span>
          </button>
        ))}
      </div>
      <p className="text-sm text-brand-muted mt-4">
        For any queries,<br />
        Call us at <a href="tel:+919328762586" className="text-[#3684bf] font-semibold">+919328762586</a>
      </p>
      {signoutOpen && (
        <SignOutConfirm
          onCancel={() => setSignoutOpen(false)}
          onConfirm={() => { logout(); setSignoutOpen(false); navigate("home"); }}
        />
      )}
    </>
  );
}

function SignOutConfirm({ onCancel, onConfirm }) {
  return createPortal(
    <div
      className="fixed inset-0 z-[1200] bg-black/50 flex items-center justify-center p-4"
      onClick={onCancel}
      role="dialog"
      aria-modal="true"
    >
      <div className="w-full max-w-sm bg-white rounded-lg shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
        <h3 className="text-xl font-bold text-brand-ink mb-2">Signout?</h3>
        <p className="text-sm text-brand-muted mb-6">Are you sure you want to sign out?</p>
        <div className="flex items-center justify-end gap-6">
          <button onClick={onCancel} className="text-[#3684bf] font-bold uppercase text-sm tracking-wider hover:opacity-80">
            Cancel
          </button>
          <button onClick={onConfirm} className="text-red-500 font-bold uppercase text-sm tracking-wider hover:opacity-80">
            Sign Out
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}

function AddAddressDrawer({ defaultName, defaultMobile, onClose, onSave }) {
  const [isDefault, setIsDefault] = useState(true);
  const [type, setType] = useState("Home");
  const [name, setName] = useState(defaultName || "");
  const [mobile, setMobile] = useState(defaultMobile || "");
  const [line1, setLine1] = useState("");
  const [line2, setLine2] = useState("");
  const [landmark, setLandmark] = useState("");
  const [building, setBuilding] = useState("");
  const [pincode, setPincode] = useState("");
  const [city, setCity] = useState("");
  const [district, setDistrict] = useState("");
  const [state, setState] = useState("");
  const [error, setError] = useState("");

  const onSubmit = (e) => {
    e.preventDefault();
    if (!name.trim() || !mobile.trim() || !line1.trim() || !pincode.trim() || !city.trim() || !district.trim() || !state.trim()) {
      setError("Please fill all required fields.");
      return;
    }
    onSave({ isDefault, type, name, mobile, line1, line2, landmark, building, pincode, city, district, state });
  };

  return createPortal(
    <div className="fixed inset-0 z-[1200] bg-black/50" onClick={onClose}>
      <aside
        className="fixed top-0 right-0 h-full w-full sm:max-w-md bg-white shadow-2xl flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex items-center justify-between px-5 py-4 border-b border-gray-200">
          <h2 className="text-lg font-bold text-brand-ink">Add new address</h2>
          <button onClick={onClose} aria-label="Close" className="text-red-500 p-1 hover:bg-red-50 rounded-full">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </header>

        <form onSubmit={onSubmit} className="flex-1 overflow-y-auto px-5 py-4 space-y-4">
          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={isDefault}
              onChange={(e) => setIsDefault(e.target.checked)}
              className="w-5 h-5 accent-[#3684bf]"
            />
            <span className="font-semibold text-brand-ink">Set as default address</span>
          </label>

          <Select label="Address Type *" value={type} onChange={setType} options={["Home", "Work", "Other"]} />

          <h3 className="font-bold text-brand-ink pt-2">Personal Details</h3>
          <Input label="Name *" value={name} onChange={setName} />
          <Input label="Mobile Number *" value={mobile} onChange={(v) => setMobile(v.replace(/\D/g, "").slice(0, 10))} />

          <h3 className="font-bold text-brand-ink pt-2">Address</h3>
          <button
            type="button"
            className="w-full flex items-center justify-center gap-2 border border-[#3684bf] text-[#3684bf] font-semibold py-2.5 rounded-full hover:bg-blue-50"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
            </svg>
            Use current location
          </button>

          <Input label="Address line 1 *" value={line1} onChange={setLine1} />
          <Input label="Address line 2" value={line2} onChange={setLine2} />
          <Input label="Landmark" value={landmark} onChange={setLandmark} />
          <Input label="Building" value={building} onChange={setBuilding} />
          <Input label="Pincode *" value={pincode} onChange={(v) => setPincode(v.replace(/\D/g, "").slice(0, 6))} />
          <Input label="City *" value={city} onChange={setCity} />
          <Input label="District *" value={district} onChange={setDistrict} />
          <Input label="State *" value={state} onChange={setState} />

          {error && <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{error}</p>}

          <button
            type="submit"
            className="w-full flex items-center justify-center gap-2 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold py-3 rounded-full"
          >
            <span className="text-lg leading-none">+</span> Add Address
          </button>
        </form>
      </aside>
    </div>,
    document.body
  );
}

function Input({ label, value, onChange }) {
  return (
    <div className="relative">
      <input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder=" "
        className="peer w-full border-2 border-gray-300 rounded-lg px-3 pt-4 pb-2 text-sm focus:outline-none focus:border-[#3684bf]"
      />
      <label className="absolute left-3 -top-2 px-1 bg-white text-[11px] font-medium text-gray-500 peer-focus:text-[#3684bf]">
        {label}
      </label>
    </div>
  );
}

function Select({ label, value, onChange, options }) {
  return (
    <div className="relative">
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full border-2 border-gray-300 rounded-lg px-3 pt-4 pb-2 text-sm focus:outline-none focus:border-[#3684bf] appearance-none bg-white"
      >
        {options.map((o) => <option key={o}>{o}</option>)}
      </select>
      <label className="absolute left-3 -top-2 px-1 bg-white text-[11px] font-medium text-gray-500">{label}</label>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none">
        <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
      </svg>
    </div>
  );
}
