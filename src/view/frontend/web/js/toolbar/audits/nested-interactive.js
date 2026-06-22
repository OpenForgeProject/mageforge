/**
 * MageForge Toolbar Audit – Nested interactive elements
 *
 * Placing interactive elements inside each other (<a> in <a>, <button> in
 * <a>, etc.) is invalid HTML and causes unpredictable browser behaviour.
 * Screen readers and keyboard users are particularly affected.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { createAudit } from "./createAudit.js";

// Select the inner (nested) element – that is the one to fix or remove.
const SELECTOR = "a a, a button, button a, button button";

export default createAudit(
  {
    key: "nested-interactive",
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M8 8m0 2a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2z"></path><path d="M16 8v-2a2 2 0 0 0 -2 -2h-8a2 2 0 0 0 -2 2v8a2 2 0 0 0 2 2h2"></path></svg>',
    label: "Nested Interactive Elements",
    description: "Highlights interactive elements nested inside other interactive elements",
  },
  () => {
    return Array.from(document.querySelectorAll(SELECTOR)).filter((el) => {
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
  },
);
