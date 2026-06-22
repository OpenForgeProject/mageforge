/**
 * MageForge Toolbar Audit – Heading hierarchy jumps
 *
 * Headings must not skip levels (e.g. H1 → H3 without an H2). Such jumps
 * confuse screen readers and harm both accessibility and SEO crawling.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { createAudit } from "./createAudit.js";

export default createAudit(
  {
    key: "seo-heading-hierarchy",
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M11 6h9"></path><path d="M11 12h9"></path><path d="M12 18h8"></path><path d="M4 16a2 2 0 1 1 4 0c0 .591 -.5 1 -1 1.5l-3 2.5h4"></path><path d="M6 10v-6l-2 2"></path></svg>',
    label: "Heading Hierarchy",
    description: "Highlights headings that skip a level (e.g. H1 → H3)",
  },
  () => {
    const headings = Array.from(
      document.querySelectorAll("h1, h2, h3, h4, h5, h6"),
    ).filter((el) => {
      if (el.closest(".mageforge-toolbar")) return false;
      if (!el.offsetParent && getComputedStyle(el).position !== "fixed")
        return false;
      const style = getComputedStyle(el);
      return (
        style.visibility !== "hidden" &&
        style.display !== "none" &&
        parseFloat(style.opacity) !== 0
      );
    });

    /** @type {Element[]} */
    const offenders = [];
    let prevLevel = 0;

    headings.forEach((el) => {
      const level = parseInt(el.tagName[1], 10);
      if (prevLevel > 0 && level > prevLevel + 1) {
        offenders.push(el);
      }
      prevLevel = level;
    });

    return { errors: [], warnings: offenders };
  },
);
