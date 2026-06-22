/**
 * MageForge Toolbar Audit – Missing meta description
 *
 * A meta description provides search engines with a summary of the page.
 * Missing or empty descriptions lead to auto-generated snippets in SERPs.
 *
 * Page-level check: no DOM element to highlight, badge-only result.
 */

const KEY = "seo-missing-meta-description";

export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path><path d="M9 13h6"></path><path d="M9 17h3"></path></svg>',
  label: "Missing Meta Description",
  description: "Flags a missing or empty meta description",
  run(context, active) {
    if (!active) return;
    const meta = document.querySelector('meta[name="description"]');
    const hasIssue = !meta || !meta.getAttribute("content")?.trim();
    context.setAuditCounterBadge(
      KEY,
      hasIssue ? "1" : "0",
      hasIssue ? "warning" : "success",
    );
  },
};
