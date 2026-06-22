/**
 * MageForge Toolbar Audit – Missing ARIA landmark regions
 *
 * Checks that the page provides the minimum landmark structure required for
 * keyboard and screen-reader navigation (WCAG 2.4.1 – Bypass Blocks).
 *
 * Checked landmarks:
 *   - <main> or [role="main"]         – primary content
 *   - <nav>  or [role="navigation"]   – at least one navigation region
 *
 * Page-level check: no DOM elements to highlight, badge-only result.
 */

const KEY = "missing-landmarks";

/** @param {string} tag @param {string} role @returns {boolean} */
function hasLandmark(tag, role) {
  return (
    !!document.querySelector(`${tag}:not(.mageforge-toolbar *)`) ||
    !!document.querySelector(`[role="${role}"]:not(.mageforge-toolbar *)`)
  );
}

/** @type {import('./index.js').AuditDefinition} */
export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 8v-2a2 2 0 0 1 2 -2h2"></path><path d="M4 16v2a2 2 0 0 0 2 2h2"></path><path d="M16 4h2a2 2 0 0 1 2 2v2"></path><path d="M16 20h2a2 2 0 0 0 2 -2v-2"></path><rect x="9" y="9" width="6" height="6" rx="1"></rect></svg>',
  label: "Missing Landmarks",
  description:
    "Flags missing &lt;main&gt; or &lt;nav&gt; landmark regions (WCAG 2.4.1)",
  run(context, active) {
    if (!active) return;

    const missing = [];
    if (!hasLandmark("main", "main")) missing.push("<main>");
    if (!hasLandmark("nav", "navigation")) missing.push("<nav>");

    const count = missing.length;
    const type = count > 0 ? "warning" : "success";
    context.setAuditCounterBadge(KEY, String(count), type);

    if (count > 0) {
      context.setAuditDescription(
        KEY,
        `Missing landmark${count > 1 ? "s" : ""}: ${missing.join(", ")}`,
      );
    }
  },
};
