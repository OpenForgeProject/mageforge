/**
 * MageForge Toolbar Audit – Inline event handler attributes
 *
 * Inline event handlers (onclick, onchange, etc.) violate Content Security
 * Policy. On Magento storefronts that enforce a CSP header, they are blocked
 * silently and cause broken functionality that is hard to debug.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { createAudit } from "./createAudit.js";

const EVENT_ATTRS = [
  "onclick",
  "ondblclick",
  "onmousedown",
  "onmouseup",
  "onmouseover",
  "onmouseout",
  "onmousemove",
  "onkeydown",
  "onkeyup",
  "onkeypress",
  "onchange",
  "oninput",
  "onfocus",
  "onblur",
  "onsubmit",
  "onreset",
  "onselect",
  "onload",
  "onerror",
];

const SELECTOR = EVENT_ATTRS.map((a) => `[${a}]`).join(", ");

export default createAudit(
  {
    key: "inline-event-handlers",
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7.904 17.563a1.2 1.2 0 0 0 2.228 .308l2.09 -3.093l4.907 4.907a1.067 1.067 0 0 0 1.509 0l1.047 -1.047a1.067 1.067 0 0 0 0 -1.509l-4.907 -4.907l3.113 -2.09a1.2 1.2 0 0 0 -.309 -2.228l-13.582 -3.904l3.904 13.563z"></path></svg>',
    label: "Inline Event Handlers",
    description: "Highlights elements with inline event handler attributes (CSP risk)",
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
