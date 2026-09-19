import { useEffect } from "react";
import { useUI } from "../context/UIContext";

/**
 * Tells the customer when their session has ended.
 *
 * AuthContext raises "sdi:session-expired" after the server rejects a token with a 401. It cannot
 * show the toast itself: UIProvider is nested INSIDE AuthProvider, so showToast is not reachable
 * from there. This sits inside UIProvider and bridges the two.
 *
 * Without it the sign-out is silent — the header would simply stop greeting you, which reads as a
 * glitch rather than an explanation.
 */
export default function SessionExpiredNotice() {
  const { showToast } = useUI();

  useEffect(() => {
    const onExpired = () =>
      showToast("Your session has ended. Please sign in again to place an order.", "info");
    window.addEventListener("sdi:session-expired", onExpired);
    return () => window.removeEventListener("sdi:session-expired", onExpired);
  }, [showToast]);

  return null;
}
