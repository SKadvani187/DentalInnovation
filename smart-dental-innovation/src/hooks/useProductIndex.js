import { useEffect, useState } from "react";
import api from "../lib/api";

// Shared, module-level cache of the full product list so multiple banner/promo components
// can resolve a stored product code (e.g. "p-001") to its human name WITHOUT each firing
// its own /products.php request. One fetch, shared across all consumers.
let _cache = null;     // resolved product array
let _promise = null;   // in-flight fetch (dedupes concurrent mounts)

export function useProductIndex() {
  const [products, setProducts] = useState(_cache || []);

  useEffect(() => {
    if (_cache) { setProducts(_cache); return; }
    if (!_promise) {
      _promise = api
        .products()
        .then((rows) => { _cache = Array.isArray(rows) ? rows : []; return _cache; })
        .catch(() => { _promise = null; return []; });
    }
    let alive = true;
    _promise.then((rows) => { if (alive) setProducts(rows); });
    return () => { alive = false; };
  }, []);

  // Real product name for a given code/id, or null if not found (yet). Callers pass this as
  // `name` to navigate("product", { id, name }); routes.to() slugifies it, and falls back to
  // the raw id in the URL when this is null — both forms still resolve at the product page.
  const nameFor = (id) => {
    const p = products.find((x) => String(x.id) === String(id));
    return p ? p.name : null;
  };

  return { products, nameFor };
}
