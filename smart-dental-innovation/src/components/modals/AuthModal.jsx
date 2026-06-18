import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";
import { useAuth } from "../../context/AuthContext";

const OTP_LEN = 6;
const RESEND_SECS = 30;

export default function AuthModal() {
  const { modal, closeModal } = useUI();
  const { requestOtp, verifyOtp, completeProfile } = useAuth();

  const [step, setStep] = useState("mobile");
  const [mobile, setMobile] = useState("");
  const [otp, setOtp] = useState(Array(OTP_LEN).fill(""));
  const [name, setName] = useState("");
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [toastType, setToastType] = useState("error");
  const [info, setInfo] = useState("");
  const [resendIn, setResendIn] = useState(0);
  const [loading, setLoading] = useState(false);
  const otpRefs = useRef([]);
  const mobileRef = useRef(null);
  const verifyingRef = useRef(false);
  const sendingRef = useRef(false);

  useEffect(() => {
    if (modal === "auth" && step === "mobile") {
      const t = setTimeout(() => mobileRef.current?.focus(), 25);
      return () => clearTimeout(t);
    }
  }, [modal, step]);

  useEffect(() => {
    if (modal !== "auth") {
      setStep("mobile");
      setMobile("");
      setOtp(Array(OTP_LEN).fill(""));
      setName("");
      setError("");
      setToast("");
      setInfo("");
      setResendIn(0);
    }
  }, [modal]);

  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(""), 3000);
    return () => clearTimeout(t);
  }, [toast]);

  useEffect(() => {
    if (modal !== "auth") return;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = "";
    };
  }, [modal]);

  useEffect(() => {
    if (resendIn <= 0) return;
    const t = setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => clearTimeout(t);
  }, [resendIn]);

  if (modal !== "auth") return null;

  const startOtp = async (m) => {
    if (sendingRef.current) return false;   // guard: auto-fire (10th digit) + manual click double-request
    sendingRef.current = true;
    setError("");
    setInfo("");
    setLoading(true);
    const res = await requestOtp(m);
    setLoading(false);
    sendingRef.current = false;
    if (!res.ok) {
      setToastType("error");
      setToast(res.error);
      return false;
    }
    setStep("otp");
    setResendIn(RESEND_SECS);
    const demo = String(res.devOtp || "").slice(0, OTP_LEN);
    setInfo(demo);
    if (demo) console.info("[Dev OTP]", demo);
    setToastType("success");
    setToast(res.sent ? "OTP sent successfully" : (res.devMode ? "Demo mode — OTP shown below" : (res.message || "OTP generated")));
    setTimeout(() => otpRefs.current[0]?.focus(), 50);
    return true;
  };

  const onMobileSubmit = (e) => {
    e.preventDefault();
    if (!/^[6-9]\d{9}$/.test(mobile)) {
      setToastType("error");
      setToast("Please enter a valid 10-digit mobile number");
      return;
    }
    startOtp(mobile);
  };

  const onOtpChange = (i, val) => {
    const v = val.replace(/\D/g, "").slice(-1);
    setOtp((prev) => {
      const next = [...prev];
      next[i] = v;
      if (v && i < OTP_LEN - 1) otpRefs.current[i + 1]?.focus();
      if (next.every((d) => d !== "") && next.join("").length === OTP_LEN) {
        setTimeout(() => autoVerify(next.join("")), 50);
      }
      return next;
    });
  };

  const onOtpKeyDown = (i, e) => {
    if (e.key === "Backspace" && !otp[i] && i > 0) {
      otpRefs.current[i - 1]?.focus();
    }
  };

  const onOtpPaste = (e) => {
    const data = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, OTP_LEN);
    if (!data) return;
    e.preventDefault();
    const next = Array(OTP_LEN).fill("");
    for (let i = 0; i < data.length; i++) next[i] = data[i];
    setOtp(next);
    otpRefs.current[Math.min(data.length, OTP_LEN - 1)]?.focus();
    if (data.length === OTP_LEN) setTimeout(() => autoVerify(data), 50);
  };

  const autoVerify = async (code) => {
    if (verifyingRef.current) return;   // prevent double-fire (auto + submit)
    verifyingRef.current = true;
    setError("");
    setLoading(true);
    const res = await verifyOtp({ mobile, otp: code });
    verifyingRef.current = false;
    setLoading(false);
    if (!res.ok) {
      setToastType("error");
      setToast(res.error || "Invalid OTP");
      setOtp(Array(OTP_LEN).fill(""));
      otpRefs.current[0]?.focus();
      return;
    }
    if (res.isNew) {
      setStep("profile");
      return;
    }
    closeModal();
  };

  const onVerify = async (e) => {
    e.preventDefault();
    if (verifyingRef.current) return;   // prevent double-fire (auto + submit)
    setError("");
    const code = otp.join("");
    if (code.length !== OTP_LEN) {
      setToastType("error");
      setToast(`Enter the ${OTP_LEN}-digit OTP.`);
      return;
    }
    verifyingRef.current = true;
    setLoading(true);
    const res = await verifyOtp({ mobile, otp: code });
    verifyingRef.current = false;
    setLoading(false);
    if (!res.ok) {
      setToastType("error");
      setToast(res.error || "Invalid OTP");
      setOtp(Array(OTP_LEN).fill(""));
      otpRefs.current[0]?.focus();
      return;
    }
    if (res.isNew) {
      setStep("profile");
      return;
    }
    closeModal();
  };

  const onProfileSubmit = async (e) => {
    e.preventDefault();
    setError("");
    if (!name.trim()) {
      setError("Please enter your name.");
      return;
    }
    setLoading(true);
    const res = await completeProfile({ mobile, name });
    setLoading(false);
    if (!res.ok) {
      setError(res.error);
      return;
    }
    closeModal();
  };

  const resend = () => {
    if (resendIn > 0) return;
    setOtp(Array(OTP_LEN).fill(""));
    startOtp(mobile);
  };

  const editNumber = () => {
    setStep("mobile");
    setOtp(Array(OTP_LEN).fill(""));
    setError("");
    setInfo("");
    setResendIn(0);
  };

  return createPortal(
    <div
      className="fixed inset-0 z-[1100] bg-black/50 backdrop-blur-[2px] flex items-center justify-center p-4"
      onClick={closeModal}
      role="dialog"
      aria-modal="true"
    >
      {toast && (
        <div
          className={`auth-toast ${toastType === "success" ? "auth-toast--success" : ""}`}
          onClick={(e) => e.stopPropagation()}
          role="alert"
        >
          <span className="auth-toast__icon">
            {toastType === "success" ? (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
              </svg>
            ) : (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
              </svg>
            )}
          </span>
          <span className="auth-toast__msg">{toast}</span>
          <button onClick={() => setToast("")} aria-label="Close" className="auth-toast__close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </div>
      )}
      <div
        className="relative w-full max-w-[420px] bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.25)] overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        {(step === "otp" || step === "profile") && (
          <button
            onClick={() => { setStep(step === "profile" ? "otp" : "mobile"); setError(""); }}
            aria-label="Back"
            className="absolute top-4 left-4 p-1 text-red-500 hover:bg-red-50 rounded-full"
          >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
          </button>
        )}
        <button
          onClick={closeModal}
          aria-label="Close"
          className="absolute top-4 right-4 p-1 text-red-500 hover:bg-red-50 rounded-full"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>

        <div className="px-6 pt-6 pb-5">
          <div className="flex items-center gap-3 mb-5 pr-8 pl-6">
            {step === "otp" ? (
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="text-gray-900 shrink-0">
                <path d="M12 2L3 7v6c0 5 4 9 9 10 5-1 9-5 9-10V7l-9-5z" />
                <path d="M9 12l2 2 4-4" />
              </svg>
            ) : step === "profile" ? (
              <svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor" className="text-gray-900 shrink-0">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
              </svg>
            ) : (
              <svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor" className="text-gray-900 shrink-0">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
              </svg>
            )}
            <div className="flex flex-col leading-tight">
              <h2 className="text-2xl font-bold text-gray-900">
                {step === "mobile" ? "Sign In" : step === "otp" ? "Verify OTP" : "Complete Profile"}
              </h2>
              <p className="text-sm text-gray-500 mt-0.5">
                {step === "mobile"
                  ? "Please sign in to continue with checkout"
                  : step === "otp"
                  ? `We've sent a ${OTP_LEN}-digit code to +91 ${mobile}`
                  : "Enter your name to complete registration"}
              </p>
            </div>
          </div>

          {step === "mobile" && (
            <form onSubmit={onMobileSubmit}>
              <div className="relative mb-5 group">
                <label className="absolute -top-2 left-3 px-1 bg-white text-[11px] font-medium text-gray-500 z-10 group-focus-within:text-[#3684bf]">
                  Mobile Number
                </label>
                <div className="flex items-center gap-2 border-2 border-gray-300 rounded-lg px-3 py-3 focus-within:border-[#3684bf] transition-colors">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="text-gray-500 shrink-0">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />
                  </svg>
                  <span className="text-gray-700 font-medium">+91</span>
                  <input
                    ref={mobileRef}
                    type="tel"
                    inputMode="numeric"
                    maxLength={10}
                    value={mobile}
                    onChange={(e) => {
                      const v = e.target.value.replace(/\D/g, "").slice(0, 10);
                      setMobile(v);
                      if (v.length === 10 && /^[6-9]\d{9}$/.test(v)) {
                        startOtp(v);
                      }
                    }}
                    className="flex-1 text-base focus:outline-none bg-transparent"
                  />
                </div>
              </div>

              <TrustBadges />

              <button
                type="submit"
                disabled={loading}
                className="otp-btn w-full py-3.5 rounded-lg text-white font-bold text-sm uppercase tracking-wider"
              >
                {loading ? "Sending..." : "Send OTP"}
              </button>
            </form>
          )}

          {step === "otp" && (
            <form onSubmit={onVerify}>
              <div className="flex justify-center gap-2 mb-4" onPaste={onOtpPaste}>
                {otp.map((d, i) => (
                  <input
                    key={i}
                    ref={(el) => (otpRefs.current[i] = el)}
                    type="text"
                    inputMode="numeric"
                    maxLength={1}
                    value={d}
                    onChange={(e) => onOtpChange(i, e.target.value)}
                    onKeyDown={(e) => onOtpKeyDown(i, e)}
                    className="w-11 h-12 sm:w-12 sm:h-14 text-center text-xl font-bold border-2 border-blue-300 rounded-lg focus:outline-none focus:border-blue-500"
                  />
                ))}
              </div>

              {info && !error && (
                <button
                  type="button"
                  onClick={() => {
                    const next = info.split("").slice(0, OTP_LEN);
                    while (next.length < OTP_LEN) next.push("");
                    setOtp(next);
                    otpRefs.current[OTP_LEN - 1]?.focus();
                  }}
                  className="text-[11px] text-gray-400 hover:text-[#3684bf] block mx-auto mb-2"
                >
                  Demo OTP: {info} (click to autofill)
                </button>
              )}
              {error && (
                <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2 mb-2">{error}</p>
              )}

              <div className="flex items-center justify-between text-xs mb-3">
                <button type="button" onClick={editNumber} className="text-blue-600 font-semibold hover:underline">
                  Edit Number
                </button>
                <button
                  type="button"
                  onClick={resend}
                  disabled={resendIn > 0}
                  className="font-semibold text-blue-600 hover:underline disabled:text-gray-400 disabled:no-underline"
                >
                  {resendIn > 0 ? `Resend in ${resendIn}s` : "Resend OTP"}
                </button>
              </div>

              <TrustBadges />

              <button
                type="submit"
                disabled={loading || otp.join("").length !== OTP_LEN}
                className="otp-btn w-full py-3.5 rounded-lg text-white font-bold text-sm uppercase tracking-wider"
              >
                {loading ? "Verifying..." : "Verify & Continue"}
              </button>
            </form>
          )}

          {step === "profile" && (
            <form onSubmit={onProfileSubmit}>
              <div className="relative mb-5">
                <label className="absolute -top-2 left-3 px-1 bg-white text-[11px] font-medium text-gray-500 z-10">
                  Full Name
                </label>
                <div className="flex items-center gap-2 border border-blue-500 rounded-lg px-3 py-3">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" className="text-gray-500 shrink-0">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
                  </svg>
                  <input
                    autoFocus
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className="flex-1 text-base focus:outline-none bg-transparent"
                    placeholder="Enter your name"
                  />
                </div>
              </div>

              {error && (
                <p className="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2 mb-3">{error}</p>
              )}

              <TrustBadges />

              <button
                type="submit"
                disabled={!name.trim()}
                className="otp-btn w-full py-3.5 rounded-lg text-white font-bold text-sm uppercase tracking-wider"
              >
                Complete Registration
              </button>
            </form>
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}

function TrustBadges() {
  return (
    <div className="flex items-center justify-between gap-3 mb-5">
      <div className="flex flex-col items-center shrink-0">
        <div className="relative w-[52px] h-[52px] flex items-center justify-center">
          <svg viewBox="0 0 100 100" className="absolute inset-0 w-full h-full" fill="none">
            <defs>
              <path id="circlePath" d="M 50,50 m -38,0 a 38,38 0 1,1 76,0 a 38,38 0 1,1 -76,0" />
            </defs>
            <text fill="#6b7280" fontSize="13" fontWeight="700" letterSpacing="1">
              <textPath href="#circlePath" startOffset="0">POWERED BY • POWERED BY • </textPath>
            </text>
          </svg>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#374151" strokeWidth="2" className="z-10">
            <path d="M12 2L3 7v6c0 5 4 9 9 10 5-1 9-5 9-10V7l-9-5z" />
          </svg>
        </div>
        <div className="flex items-center gap-1 mt-1">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" className="text-gray-800">
            <path d="M12 2L2 22h20L12 2z" />
          </svg>
          <span className="text-[11px] font-bold text-gray-800 tracking-tight">Storedum</span>
        </div>
      </div>

      <Badge
        icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" className="text-gray-900">
            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-4-4 1.41-1.41L11 12.17l5.59-5.59L18 8l-7 7z" />
          </svg>
        }
        line1="Verified"
        line2="Merchant"
      />
      <Badge
        icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" className="text-gray-900">
            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z" />
          </svg>
        }
        line1="Secure"
        line2="Payments"
      />
      <Badge
        icon={
          <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" className="text-gray-900">
            <path d="M19 7h-3V5.5C16 3.57 14.43 2 12.5 2h-1C9.57 2 8 3.57 8 5.5V7H5c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM10 5.5c0-.83.67-1.5 1.5-1.5h1c.83 0 1.5.67 1.5 1.5V7h-4V5.5zm6.5 9.8l-5 4.7-3-2.8 1.4-1.4 1.6 1.5 3.6-3.4 1.4 1.4z" />
          </svg>
        }
        line1="Buyer"
        line2="Protection"
      />
    </div>
  );
}

function Badge({ icon, line1, line2 }) {
  return (
    <div className="flex items-center gap-1.5 text-gray-900">
      {icon}
      <div className="leading-tight">
        <div className="text-[11px] font-bold">{line1}</div>
        <div className="text-[10px] text-gray-600 font-medium">{line2}</div>
      </div>
    </div>
  );
}
