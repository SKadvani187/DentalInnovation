import { useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { to } from "../lib/routes";

// Drop-in replacement for the old UIContext navigate(name, params) API, now backed by
// react-router. Call sites stay identical: navigate("product", { id: p.id }).
// Scroll-to-top is handled globally by <ScrollToTop> on pathname change.
export function useAppNavigate() {
  const rrNavigate = useNavigate();
  return useCallback((name, params = null) => {
    rrNavigate(to(name, params));
  }, [rrNavigate]);
}
