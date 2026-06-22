/**
 * MageForge Toolbar Audit – SVG icons without aria-hidden
 *
 * Inline <svg> elements that are purely decorative (nested inside an
 * interactive element that already has an accessible label) should be
 * hidden from assistive technologies via aria-hidden="true". Without it,
 * screen readers may announce the SVG content redundantly.
 *
 * Rule: <svg> inside <button>, <a>, or [role="button"] that lacks both
 *   aria-hidden="true" and a <title> child element is flagged as a warning.
 */

import { createAudit } from "./createAudit.js";

/** @type {import('./index.js').AuditDefinition} */
export default createAudit(
  {
    key: "svg-icons-aria-hidden",
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><circle cx="12" cy="12" r="9"></circle><path d="M9 12h6"></path><path d="M12 9v6"></path></svg>',
    label: "SVG Icons Without aria-hidden",
    description:
      "Flags decorative SVGs missing aria-hidden inside interactive elements",
  },
  () => {
    return Array.from(
      document.querySelectorAll('button svg, a svg, [role="button"] svg'),
    ).filter((svg) => {
      // Skip toolbar's own SVGs
      if (svg.closest(".mageforge-toolbar")) return false;
      // Already hidden from AT
      if (svg.getAttribute("aria-hidden") === "true") return false;
      // Has a <title> → informative, not decorative
      if (svg.querySelector("title")) return false;
      // Has aria-label or aria-labelledby → informative
      if (svg.getAttribute("aria-label") || svg.getAttribute("aria-labelledby"))
        return false;
      return true;
    });
  },
);
