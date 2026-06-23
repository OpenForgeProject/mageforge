/**
 * MageForge Toolbar Audit – Duplicate meta tags
 *
 * Multiple occurrences of unique page-level elements confuse search engines
 * and can override each other unpredictably.
 *
 * Checked for duplicates:
 *   - <title>
 *   - <meta name="description">
 *   - <link rel="canonical">
 *
 * Page-level check: no DOM elements to highlight, badge-only result.
 */

const KEY = "seo-duplicate-meta";

/** @type {import('./index.js').AuditDefinition} */
export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 9.667a2.667 2.667 0 0 1 2.667 -2.667h8a2.667 2.667 0 0 1 2.667 2.667v8a2.667 2.667 0 0 1 -2.667 2.667h-8a2.667 2.667 0 0 1 -2.667 -2.667z"></path><path d="M4.012 16.737a2 2 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1"></path></svg>',
  label: "Duplicate Meta Tags",
  description: "Flags duplicate &lt;title&gt;, meta description, or canonical",
  run(context, active) {
    if (!active) {
      context.setAuditDescription(KEY, this.description);
      return;
    }

    const duplicates = [];

    if (document.querySelectorAll("head > title").length > 1)
      duplicates.push("<title>");

    if (document.querySelectorAll('meta[name="description"]').length > 1)
      duplicates.push('meta[name="description"]');

    if (document.querySelectorAll('link[rel="canonical"]').length > 1)
      duplicates.push('link[rel="canonical"]');

    const count = duplicates.length;
    const type = count > 0 ? "error" : "success";
    context.setAuditCounterBadge(KEY, String(count), type);

    if (count > 0) {
      context.setAuditDescription(
        KEY,
        `Duplicate${count > 1 ? "s" : ""}: ${duplicates.join(", ")}`,
      );
    } else {
      context.setAuditDescription(KEY, this.description);
    }
  }
};
