/**
 * MageForge Toolbar Audit – Missing lang attribute
 *
 * The lang attribute on the <html> element declares the page language to
 * browsers, screen readers, and search engines. Missing lang breaks
 * assistive technology and may hurt multilingual SEO.
 *
 * Page-level check: no DOM element to highlight, badge-only result.
 */

const KEY = "seo-missing-lang";

export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 5h7"></path><path d="M9 3v2c0 4.418 -2.239 8 -5 8"></path><path d="M5 9c0 2.144 2.952 3.908 6.7 4"></path><path d="M12 20l4 -9l4 9"></path><path d="M19.1 18h-6.2"></path></svg>',
  label: "Missing lang Attribute",
  description: "Flags a missing or empty lang attribute on the <html> element",
  run(context, active) {
    if (!active) return;
    const lang = document.documentElement.getAttribute("lang");
    const hasIssue = !lang || !lang.trim();
    context.setAuditCounterBadge(
      KEY,
      hasIssue ? "1" : "0",
      hasIssue ? "warning" : "success",
    );
  },
};
