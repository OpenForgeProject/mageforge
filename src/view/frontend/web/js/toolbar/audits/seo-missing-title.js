/**
 * MageForge Toolbar Audit – Missing or empty page title
 *
 * Every page requires a non-empty <title> element. Missing or blank titles
 * prevent correct indexing in search engines and break browser tab labels.
 *
 * Page-level check: no DOM element to highlight, badge-only result.
 */

const KEY = "seo-missing-title";

export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 12h10"></path><path d="M7 5v14"></path><path d="M17 5v14"></path><path d="M15 19h4"></path><path d="M15 5h4"></path><path d="M5 19h4"></path><path d="M5 5h4"></path></svg>',
  label: "Missing Page Title",
  description: "Flags a missing or empty title element",
  run(context, active) {
    if (!active) return;
    const title = document.querySelector("title");
    const hasIssue = !title || !title.textContent.trim();
    context.setAuditCounterBadge(
      KEY,
      hasIssue ? "1" : "0",
      hasIssue ? "error" : "success",
    );
  },
};
