// Fetches the combined home feed from the API. All content is DB-only; the empty
// initial shape just keeps consumers from crashing before the API responds.
import { useEffect, useState } from "react";
import api from "../lib/api";

const STATIC = {
  sections: {
    bestsellers: [],
    newArrivals: [],
    implantology: [],
    handpieces: [],
    matrixSystem: [],
    endodontics: [],
  },
  categories: [],
  testimonials: [],
};

export function useHomeData() {
  const [data, setData] = useState(STATIC);
  const [loading, setLoading] = useState(true);
  const [source, setSource] = useState("static"); // "api" | "static"

  useEffect(() => {
    let alive = true;
    api
      .home()
      .then((j) => {
        if (!alive) return;
        // Merge over STATIC so a partial API response never drops keys (avoids undefined sections crash).
        setData((prev) => ({
          sections: { ...prev.sections, ...(j.sections || {}) },
          categories: j.categories?.length ? j.categories : prev.categories,
          testimonials: j.testimonials?.length ? j.testimonials : prev.testimonials,
        }));
        setSource("api");
      })
      .catch((err) => {
        // keep static fallback
        console.warn("[home] API failed, using static data:", err.message);
        setSource("static");
      })
      .finally(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, []);

  return { ...data, loading, source };
}
