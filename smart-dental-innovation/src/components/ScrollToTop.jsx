import { useEffect } from "react";
import { useLocation } from "react-router-dom";

// Scrolls to the top whenever the route changes. Replaces the scroll-to-top that the
// old UIContext.navigate() performed on every view switch. Renders nothing.
export default function ScrollToTop() {
  const { pathname, search } = useLocation();
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: "instant" });
  }, [pathname, search]);
  return null;
}
