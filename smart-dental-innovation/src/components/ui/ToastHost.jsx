import { createPortal } from "react-dom";
import { useUI } from "../../context/UIContext";

const ICONS = {
  success: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
    </svg>
  ),
  error: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
    </svg>
  ),
  info: (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
    </svg>
  ),
};

export default function ToastHost() {
  const { toasts, dismissToast } = useUI();
  if (!toasts.length) return null;
  return createPortal(
    <div className="fixed top-4 left-1/2 -translate-x-1/2 z-[1300] flex flex-col items-center gap-2 pointer-events-none">
      {toasts.map((t) => (
        <div
          key={t.id}
          role="alert"
          className={`global-toast global-toast--${t.type} pointer-events-auto`}
        >
          <span className="global-toast__icon">{ICONS[t.type] || ICONS.info}</span>
          <span className="global-toast__msg">{t.message}</span>
          <button
            onClick={() => dismissToast(t.id)}
            aria-label="Close"
            className="global-toast__close"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </div>
      ))}
    </div>,
    document.body
  );
}
