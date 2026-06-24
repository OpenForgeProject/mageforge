/**
 * MageForge Toolbar Audit – Missing canonical link
 *
 * A canonical link tells search engines which URL is the authoritative version
 * of a page. Missing canonicals on Magento stores cause duplicate-content
 * penalties for filtered/sorted category pages and pagination.
 *
 * Page-level check: no DOM element to highlight, badge-only result.
 */

const KEY = "seo-missing-canonical";

export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 15l6 -6"></path><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"></path><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"></path></svg>',
  label: "Missing Canonical",
  description: 'Flags a missing <link rel="canonical"> element',
  run(context, active) {
    if (!active) return;
    const canonical = document.querySelector('link[rel="canonical"]');
    const hasIssue = !canonical || !canonical.getAttribute("href")?.trim();
    context.setAuditCounterBadge(
      KEY,
      hasIssue ? "1" : "0",
      hasIssue ? "warning" : "success",
    );
  },
};
