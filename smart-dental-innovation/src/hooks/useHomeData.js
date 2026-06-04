// Fetches the combined home feed from the API, with static data as fallback.
// Keeps the storefront working even if the API is down.
import { useEffect, useState } from "react";
import api from "../lib/api";

import {
  bestsellers as sBestsellers,
  newArrivals as sNewArrivals,
  implantology as sImplantology,
  handpieces as sHandpieces,
  matrixSystem as sMatrix,
  endodontics as sEndodontics,
} from "../data/products";
import { categories as sCategories } from "../data/categories";
import { testimonials as sTestimonials } from "../data/testimonials";

const STATIC = {
  sections: {
    bestsellers: sBestsellers,
    newArrivals: sNewArrivals,
    implantology: sImplantology,
    handpieces: sHandpieces,
    matrixSystem: sMatrix,
    endodontics: sEndodontics,
  },
  categories: sCategories,
  testimonials: sTestimonials,
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
        setData({
          sections: j.sections,
          categories: j.categories,
          testimonials: j.testimonials,
        });
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
