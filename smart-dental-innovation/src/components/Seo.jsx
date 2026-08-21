import { useEffect } from "react";

// Per-route SEO. React 19 hoists <title>/<meta>/<link> rendered anywhere in the tree into
// <head>, so a crawler that runs JS (Googlebot does) sees real per-page metadata. JSON-LD is
// injected/cleaned via an effect since <script> isn't hoisted the same way.
const SITE = "DentInno";
const DEFAULT_DESC =
  "DentInno — premium dental products, equipment and consumables for clinics and professionals.";

export default function Seo({ title, description, canonical, image, jsonLd, noindex = false }) {
  const fullTitle = title ? `${title} | ${SITE}` : `${SITE} — Dental Products & Equipment`;
  const desc = description || DEFAULT_DESC;
  const url = canonical || (typeof window !== "undefined" ? window.location.href : "");

  useEffect(() => {
    if (!jsonLd) return;
    const el = document.createElement("script");
    el.type = "application/ld+json";
    el.setAttribute("data-seo-jsonld", "");
    el.text = JSON.stringify(jsonLd);
    document.head.appendChild(el);
    return () => { el.remove(); };
  }, [jsonLd]);

  return (
    <>
      <title>{fullTitle}</title>
      <meta name="description" content={desc} />
      {noindex && <meta name="robots" content="noindex,nofollow" />}
      {url && <link rel="canonical" href={url} />}
      <meta property="og:type" content="website" />
      <meta property="og:title" content={fullTitle} />
      <meta property="og:description" content={desc} />
      {url && <meta property="og:url" content={url} />}
      {image && <meta property="og:image" content={image} />}
      <meta name="twitter:card" content={image ? "summary_large_image" : "summary"} />
      <meta name="twitter:title" content={fullTitle} />
      <meta name="twitter:description" content={desc} />
    </>
  );
}
