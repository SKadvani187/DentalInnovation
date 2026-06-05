import { useState } from "react";
import { createPortal } from "react-dom";
import { useAuth } from "../../context/AuthContext";
import { useUI } from "../../context/UIContext";
import { useSettings } from "../../context/SettingsContext";

export default function AccountPage() {
  const { user, updateProfile, logout } = useAuth();
  const { navigate, openModal } = useUI();
  const { company = {} } = useSettings();
  const supportPhone = company.phone || "+919328762586";
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(user?.name || "");
  const [email, setEmail] = useState(user?.email || "");
  const [address, setAddress] = useState(user?.address || "");
  const [gst, setGst] = useState(!!user?.gstNumber);
  const [gstNumber, setGstNumber] = useState(user?.gstNumber || "");
  const [legalName, setLegalName] = useState(user?.legalName || "");
  const [signoutOpen, setSignoutOpen] = useState(false);

  if (!user) {
    return (
      <div className="max-w-[1400px] mx-auto px-4 py-12 text-center">
        <p className="text-brand-muted mb-4">Please sign in to view your account.</p>
        <button
          onClick={() => openModal("auth")}
          className="bg-[#3684bf] text-white font-bold px-6 py-2.5 rounded-md hover:bg-[#1f5f96]"
        >
          Sign In
        </button>
      </div>
    );
  }

  const onSave = (e) => {
    e.preventDefault();
    updateProfile({ name, email, address });
    setEditing(false);
  };

  const confirmSignOut = () => {
    logout();
    setSignoutOpen(false);
    navigate("home");
  };

  return (
    <div className="max-w-[1200px] mx-auto px-4 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <aside className="lg:col-span-1">
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
            <SidebarItem icon="orders" label="My Orders" onClick={() => navigate("orders")} />
            <SidebarItem icon="wishlist" label="Wishlist" onClick={() => navigate("wishlist")} />
            <SidebarItem icon="address" label="Address" onClick={() => navigate("address")} />
            <SidebarItem icon="signout" label="Sign Out" onClick={() => setSignoutOpen(true)} />
          </div>

          <p className="text-sm text-brand-muted mt-4">
            For any queries,<br />
            Call us at <a href={`tel:${supportPhone.replace(/\s/g,'')}`} className="text-[#3684bf] font-semibold">{supportPhone}</a>
          </p>
        </aside>

        <section className="lg:col-span-2">
          <h2 className="text-xl font-bold text-brand-ink mb-4">Account Information</h2>
          <div className="border border-gray-200 rounded-xl p-6">
            <form onSubmit={onSave} className="space-y-5">
              <Field
                label="Full Name"
                value={name}
                onChange={setName}
                disabled={!editing}
              />
              <Field
                label="Email ID"
                value={email}
                onChange={setEmail}
                disabled={!editing}
                warning={!email}
                type="email"
              />
              <Field
                label="Default Address"
                value={address}
                onChange={setAddress}
                disabled={!editing}
                warning={!address}
                placeholder="No Address Added"
              />

              <div className="bg-gray-50 rounded-lg overflow-hidden">
                <div className="px-4 py-3 flex items-center justify-between">
                  <div className="text-sm">
                    Use GST invoices - <a className="text-[#3684bf] font-semibold cursor-pointer">Know More.</a>
                  </div>
                  <button
                    type="button"
                    onClick={() => setGst((v) => !v)}
                    className={`w-11 h-6 rounded-full relative transition ${gst ? "bg-[#3684bf]" : "bg-gray-300"}`}
                  >
                    <span className={`absolute top-0.5 w-5 h-5 bg-white rounded-full transition ${gst ? "left-5" : "left-0.5"}`} />
                  </button>
                </div>
                {gst && (
                  <div className="px-4 pb-4 space-y-3">
                    <div className="bg-white border border-gray-200 rounded-lg">
                      <input
                        value={gstNumber}
                        onChange={(e) => setGstNumber(e.target.value.toUpperCase())}
                        placeholder="GST Number"
                        className="w-full px-3 py-3 bg-transparent focus:outline-none text-sm"
                      />
                    </div>
                    <div className="bg-white border border-gray-200 rounded-lg">
                      <input
                        value={legalName}
                        onChange={(e) => setLegalName(e.target.value)}
                        placeholder="Legal Name"
                        className="w-full px-3 py-3 bg-transparent focus:outline-none text-sm"
                      />
                    </div>
                    <div className="flex justify-end">
                      <button
                        type="button"
                        onClick={() => updateProfile({ gstNumber, legalName })}
                        className="text-[#3684bf] font-bold uppercase text-sm tracking-wider hover:opacity-80"
                      >
                        Save
                      </button>
                    </div>
                  </div>
                )}
              </div>

              {editing ? (
                <div className="flex gap-3">
                  <button
                    type="submit"
                    className="flex-1 bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold py-3 rounded-md"
                  >
                    Save
                  </button>
                  <button
                    type="button"
                    onClick={() => { setEditing(false); setName(user.name || ""); setEmail(user.email || ""); setAddress(user.address || ""); }}
                    className="px-6 border border-gray-300 text-brand-ink font-bold py-3 rounded-md hover:bg-gray-50"
                  >
                    Cancel
                  </button>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={() => setEditing(true)}
                  className="w-full bg-[#3684bf] hover:bg-[#1f5f96] text-white font-bold py-3 rounded-md"
                >
                  Edit
                </button>
              )}
            </form>
          </div>
        </section>
      </div>

      {signoutOpen && (
        <SignOutConfirm onCancel={() => setSignoutOpen(false)} onConfirm={confirmSignOut} />
      )}
    </div>
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
      <div
        className="w-full max-w-sm bg-white rounded-lg shadow-2xl p-6"
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="text-xl font-bold text-brand-ink mb-2">Signout?</h3>
        <p className="text-sm text-brand-muted mb-6">Are you sure you want to sign out?</p>
        <div className="flex items-center justify-end gap-6">
          <button
            onClick={onCancel}
            className="text-[#3684bf] font-bold uppercase text-sm tracking-wider hover:opacity-80"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            className="text-red-500 font-bold uppercase text-sm tracking-wider hover:opacity-80"
          >
            Sign Out
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}

function Field({ label, value, onChange, disabled, warning, type = "text", placeholder }) {
  return (
    <div>
      <label className="text-xs text-brand-muted block mb-1">{label}</label>
      <div className="flex items-center justify-between border-b border-gray-300 py-2">
        <input
          type={type}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          disabled={disabled}
          placeholder={placeholder}
          className={`flex-1 bg-transparent focus:outline-none text-brand-ink ${disabled ? "cursor-default" : ""} ${!value ? "font-semibold" : ""}`}
        />
        {warning && (
          <svg width="20" height="20" viewBox="0 0 24 24" fill="#f97316">
            <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" />
          </svg>
        )}
      </div>
    </div>
  );
}

function SidebarItem({ icon, label, onClick }) {
  const icons = {
    orders: <path d="M19 7h-3V5.5C16 3.57 14.43 2 12.5 2h-1C9.57 2 8 3.57 8 5.5V7H5c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM10 5.5c0-.83.67-1.5 1.5-1.5h1c.83 0 1.5.67 1.5 1.5V7h-4V5.5z" />,
    wishlist: <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />,
    address: <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />,
    signout: <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3h-8c-1.1 0-2 .9-2 2v4h2V5h8v14h-8v-4h-2v4c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />,
  };
  return (
    <button
      onClick={onClick}
      className="w-full flex items-center gap-3 px-5 py-4 hover:bg-gray-50 text-left"
    >
      <svg width="20" height="20" viewBox="0 0 24 24" fill="#3684bf">{icons[icon]}</svg>
      <span className="font-semibold text-brand-ink">{label}</span>
    </button>
  );
}
