import { useState } from "react";
import { useUI } from "../../context/UIContext";

export default function ContactPage() {
  const { navigate } = useUI();
  const [form, setForm] = useState({ name: "", phone: "", email: "", description: "" });
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState("");

  const onChange = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const onSubmit = (e) => {
    e.preventDefault();
    setError("");
    if (!form.name || !form.phone || !form.email || !form.description) {
      setError("Please fill in all required fields.");
      return;
    }
    if (!/^[6-9]\d{9}$/.test(form.phone)) {
      setError("Please enter a valid 10-digit phone number.");
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      setError("Please enter a valid email address.");
      return;
    }
    setSubmitted(true);
    setForm({ name: "", phone: "", email: "", description: "" });
  };

  return (
    <div className="max-w-[1400px] mx-auto px-4 py-6">
      <nav className="flex items-center gap-2 text-sm text-brand-muted mb-6">
        <button onClick={() => navigate("home")} className="hover:text-[#3684bf]">Home</button>
        <span>/</span>
        <span className="text-brand-ink font-semibold">Contact Us</span>
      </nav>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <div className="lg:col-span-7">
          <div className="border border-[#3684bf] rounded-2xl p-6 lg:p-8">
            <h1 className="text-3xl font-bold text-brand-ink">Got a question in mind?</h1>
            <p className="text-sm text-brand-muted mt-2 mb-6">
              Fill in this form or{" "}
              <a className="text-brand-ink underline font-semibold cursor-pointer">send us an e-mail</a>
            </p>

            {submitted ? (
              <div className="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
                Thanks! We received your message and will get back within 24 hours.
              </div>
            ) : (
              <form onSubmit={onSubmit} className="space-y-4">
                <FloatField id="name" label="Name *" value={form.name} onChange={onChange("name")} />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <FloatField
                    id="phone"
                    label="Phone Number *"
                    type="tel"
                    inputMode="numeric"
                    maxLength={10}
                    value={form.phone}
                    onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value.replace(/\D/g, "").slice(0, 10) }))}
                  />
                  <FloatField id="email" label="Email *" type="email" value={form.email} onChange={onChange("email")} />
                </div>
                <FloatField
                  id="description"
                  label="Description *"
                  value={form.description}
                  onChange={onChange("description")}
                  textarea
                  rows={5}
                />

                {error && (
                  <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{error}</p>
                )}

                <button
                  type="submit"
                  className="otp-btn w-full py-3.5 rounded-lg text-white font-bold text-sm uppercase tracking-wider"
                >
                  Submit
                </button>
              </form>
            )}
          </div>
        </div>

        <div className="lg:col-span-5">
          <h2 className="text-3xl font-bold text-brand-ink mb-6">Do not hesitate to call</h2>

          <div className="space-y-6">
            <ContactBlock
              label="For Order Inquiries"
              icon={
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#3684bf">
                  <path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" />
                </svg>
              }
              value="+91 93287 62586"
              href="tel:+919328762586"
            />
            <ContactBlock
              label="For Sales Inquiries"
              icon={
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#3684bf">
                  <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                </svg>
              }
              value="smartdentalinnovations.web@gmail.com"
              href="mailto:smartdentalinnovations.web@gmail.com"
            />
            <ContactBlock
              label="Other Inquires"
              icon={
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#3684bf">
                  <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                </svg>
              }
              value="smartdentalinnovations.web@gmail.com"
              href="mailto:smartdentalinnovations.web@gmail.com"
            />
          </div>
        </div>
      </div>

      <OurOffice />
    </div>
  );
}

function OurOffice() {
  const cards = [
    {
      title: "Address",
      text: "Third floor, Swastik Plaza, 308, Savlia Cir, Yogi Chowk Ground, Chikuwadi, Varachha, Surat, Gujarat 395006",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#3684bf">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z" />
        </svg>
      ),
    },
    {
      title: "Phone",
      text: "+91 93287 62586",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#3684bf">
          <path d="M20.487 17.14l-4.065-3.696a1 1 0 00-1.391.043l-2.393 2.461c-.576-.11-1.734-.471-2.926-1.66-1.192-1.193-1.553-2.354-1.66-2.926l2.459-2.394a1 1 0 00.043-1.391L6.859 3.514a1 1 0 00-1.391-.087l-2.18 1.872c-.108.105-.171.252-.181.412-.024.49-.085 1.305-.255 2.176C2.55 9.652 1.917 12.78 6.07 16.93c4.151 4.15 7.278 3.517 8.99 3.218.847-.17 1.661-.231 2.151-.255.16-.012.307-.075.412-.181l2.171-2.18a1 1 0 00.005-1.391z" />
        </svg>
      ),
    },
    {
      title: "Email",
      text: "smartdentalinnovations.web@gmail.com",
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="#3684bf">
          <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
        </svg>
      ),
    },
  ];

  return (
    <section className="mt-16 border-t border-gray-200 pt-14">
      <div className="text-center mb-10">
        <h2 className="text-4xl font-bold text-brand-ink">Our Office</h2>
        <p className="mt-2 text-brand-muted">
          We guarantee that you'll be satisfied with the results. So why wait?
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
        {cards.map((c) => (
          <div
            key={c.title}
            className="office-card group border border-gray-200 rounded-2xl p-8 text-center bg-white cursor-pointer"
          >
            <div className="mx-auto w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mb-5 transition-transform duration-300 group-hover:scale-110">
              {c.icon}
            </div>
            <h3 className="font-bold text-brand-ink text-lg mb-2 transition-colors duration-300 group-hover:text-[#3684bf]">
              {c.title}
            </h3>
            <p className="text-sm text-brand-muted leading-relaxed">{c.text}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

function FloatField({ id, label, value, onChange, type = "text", textarea, rows, ...rest }) {
  const filled = String(value || "").length > 0;
  return (
    <div className="relative">
      <label
        htmlFor={id}
        className={`absolute left-3 transition-all pointer-events-none px-1 bg-white ${
          filled ? "-top-2 text-[11px] text-[#3684bf]" : "top-3.5 text-sm text-brand-muted"
        }`}
      >
        {label}
      </label>
      {textarea ? (
        <textarea
          id={id}
          rows={rows || 4}
          value={value}
          onChange={onChange}
          className="w-full border border-gray-300 rounded-md px-3 pt-4 pb-2 text-sm focus:outline-none focus:border-[#3684bf]"
          {...rest}
        />
      ) : (
        <input
          id={id}
          type={type}
          value={value}
          onChange={onChange}
          className="w-full border border-gray-300 rounded-md px-3 py-3.5 text-sm focus:outline-none focus:border-[#3684bf]"
          {...rest}
        />
      )}
    </div>
  );
}

function ContactBlock({ label, icon, value, href }) {
  return (
    <div>
      <p className="text-sm text-brand-muted mb-2">{label}</p>
      <a href={href} className="flex items-center gap-2 text-[#3684bf] font-semibold text-base hover:underline break-all">
        {icon}
        <span className="underline">{value}</span>
      </a>
    </div>
  );
}
