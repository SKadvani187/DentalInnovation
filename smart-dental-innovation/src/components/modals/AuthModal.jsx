import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";
import { useAuth } from "../../context/AuthContext";

const OTP_LEN = 6;
const RESEND_SECS = 30;

export default function AuthModal() {
  const { modal, closeModal } = useUI();
  const { requestOtp, verifyOtp } = useAuth();

  const [step, setStep] = useState("mobile");
  const [mobile, setMobile] = useState("");
  const [otp, setOtp] = useState(Array(OTP_LEN).fill(""));
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");
  const [info, setInfo] = useState("");
  const [resendIn, setResendIn] = useState(0);
  const [loading, setLoading] = useState(false);
  const otpRefs = useRef([]);

  useEffect(() => {
    if (modal !== "auth") {
      setStep("mobile");
      setMobile("");
      setOtp(Array(OTP_LEN).fill(""));
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
    const onKey = (e) => e.key === "Escape" && closeModal();
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [modal, closeModal]);

  useEffect(() => {
    if (resendIn <= 0) return;
    const t = setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => clearTimeout(t);
  }, [resendIn]);

  if (modal !== "auth") return null;

  const startOtp = (m) => {
    setError("");
    setInfo("");
    setLoading(true);
    const res = requestOtp(m);
    setLoading(false);
    if (!res.ok) {
      setToast(res.error);
      return false;
    }
    setStep("otp");
    setResendIn(RESEND_SECS);
    setInfo(`OTP sent to +91 ${m}. (Demo OTP: ${res.demoOtp})`);
    setTimeout(() => otpRefs.current[0]?.focus(), 50);
    return true;
  };

  const onMobileSubmit = (e) => {
    e.preventDefault();
    if (mobile.length !== 10) {
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
      return next;
    });
    if (v && i < OTP_LEN - 1) otpRefs.current[i + 1]?.focus();
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
  };

  const onVerify = (e) => {
    e.preventDefault();
    setError("");
    const code = otp.join("");
    if (code.length !== OTP_LEN) {
      setError("Enter the 6-digit OTP.");
      return;
    }
    setLoading(true);
    const res = verifyOtp({ mobile, otp: code });
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
          className="auth-toast"
          onClick={(e) => e.stopPropagation()}
          role="alert"
        >
          <span className="auth-toast__icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
            </svg>
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
          <div className="flex items-center gap-3 mb-5 pr-8">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor" className="text-gray-900 shrink-0">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4m0 2c-2.67 0-8 1.34-8 4v1c0 .55.45 1 1 1h14c.55 0 1-.45 1-1v-1c0-2.66-5.33-4-8-4" />
            </svg>
            <div className="flex flex-col leading-tight">
              <h2 className="text-2xl font-bold text-gray-900">
                {step === "mobile" ? "Sign In" : "Verify OTP"}
              </h2>
              <p className="text-sm text-gray-500 mt-0.5">
                {step === "mobile"
                  ? "Please sign in to continue with checkout"
                  : `Enter the 6-digit code sent to +91 ${mobile}`}
              </p>
            </div>
          </div>

          {step === "mobile" && (
            <form onSubmit={onMobileSubmit}>
              <div className="relative mb-5">
                <label className="absolute -top-2 left-3 px-1 bg-white text-[11px] font-medium text-gray-500 z-10">
                  Mobile Number
                </label>
                <div className="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-3 focus-within:border-gray-500">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="text-gray-500 shrink-0">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />
                  </svg>
                  <span className="text-gray-700 font-medium">+91</span>
                  <input
                    autoFocus
                    type="tel"
                    inputMode="numeric"
                    maxLength={10}
                    value={mobile}
                    onChange={(e) => setMobile(e.target.value.replace(/\D/g, "").slice(0, 10))}
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
              <div className="flex justify-between gap-2 mb-3" onPaste={onOtpPaste}>
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
                    className="w-11 h-12 text-center text-lg font-bold border border-gray-300 rounded-md focus:outline-none focus:border-blue-500"
                  />
                ))}
              </div>

              {info && !error && (
                <p className="text-[11px] text-gray-500 text-center mb-2">{info}</p>
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
