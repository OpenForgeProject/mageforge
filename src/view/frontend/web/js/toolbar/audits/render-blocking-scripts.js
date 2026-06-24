/**
 * MageForge Toolbar Audit – Render-blocking scripts
 *
 * External scripts in <head> without defer or async block HTML parsing and
 * delay First Contentful Paint (FCP). Each such script adds latency before
 * the browser can render any content.
 *
 * Note: type="module" scripts are deferred by the HTML spec and are not
 * flagged. Only classic scripts with an src attribute are checked.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { createAudit } from "./createAudit.js";

export default createAudit(
  {
    key: "render-blocking-scripts",
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path><path d="M10 13l-1 2l1 2"></path><path d="M14 13l1 2l-1 2"></path></svg>',
    label: "Render-blocking Scripts",
    description: "Highlights <head> scripts without defer or async",
  },
  () => {
    const scripts = Array.from(
      document.querySelectorAll("head script[src]"),
    ).filter((s) => {
      const type = (s.getAttribute("type") || "").toLowerCase();
      // Module scripts are deferred by spec; non-JS types are not executed
      if (type && type !== "text/javascript") return false;
      return !s.hasAttribute("defer") && !s.hasAttribute("async");
    });

    return { errors: [], warnings: scripts };
  },
);
