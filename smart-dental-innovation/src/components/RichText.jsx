import DOMPurify from "dompurify";

// Tags/attributes the admin Content-tab editor can produce. Anything else is stripped.
const ALLOWED_TAGS = ["b", "strong", "i", "em", "u", "p", "br", "ul", "ol", "li", "h3", "h4", "a", "span", "div",
  // table tags — some sections (e.g. Key Specifications) are authored as Feature/Specification tables.
  "table", "thead", "tbody", "tfoot", "tr", "td", "th", "caption"];
const ALLOWED_ATTR = ["href", "target", "rel"];

// Looks like HTML? (has at least one tag). Old DB rows are plain text with newlines.
const looksHtml = (s) => /<[a-z!/][\s\S]*>/i.test(s);

// Force every link to open safely (defense against tab-nabbing).
if (typeof window !== "undefined" && DOMPurify.addHook) {
  DOMPurify.addHook("afterSanitizeAttributes", (node) => {
    if (node.tagName === "A" && node.getAttribute("href")) {
      node.setAttribute("target", "_blank");
      node.setAttribute("rel", "noopener noreferrer nofollow");
    }
  });
}

/**
 * Render admin-authored rich text safely. HTML is sanitized with DOMPurify before
 * being injected. Legacy plain-text values (no tags) keep their line breaks via <br>.
 */
export default function RichText({ html, className = "" }) {
  if (html == null || String(html).trim() === "") return null;
  // Plain-text values: collapse any run of blank lines/whitespace between lines to a SINGLE <br>
  // so double "\n\n" in the source doesn't render as a big empty gap between sentences.
  const raw = looksHtml(html)
    ? String(html)
    : String(html).trim().replace(/[ \t]*\r?\n\s*/g, "<br>");
  const clean = DOMPurify.sanitize(raw, { ALLOWED_TAGS, ALLOWED_ATTR });
  return <div className={`rich-text ${className}`} dangerouslySetInnerHTML={{ __html: clean }} />;
}
