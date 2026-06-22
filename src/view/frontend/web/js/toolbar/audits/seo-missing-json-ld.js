/**
 * MageForge Toolbar Audit – Missing JSON-LD structured data
 *
 * JSON-LD is the recommended format for structured data (Product, Breadcrumb,
 * Organization, etc.) on Magento storefronts. Absence prevents rich results
 * in Google Search and reduces click-through rates.
 *
 * Page-level check: no DOM element to highlight, badge-only result.
 */

const KEY = "seo-missing-json-ld";

export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 4a2 2 0 0 0 -2 2v3a2 3 0 0 1 -2 3a2 3 0 0 1 2 3v3a2 2 0 0 0 2 2"></path><path d="M17 4a2 2 0 0 1 2 2v3a2 3 0 0 1 2 3a2 3 0 0 1 -2 3v3a2 2 0 0 1 -2 2"></path></svg>',
  label: "Missing JSON-LD",
  description: "Flags pages with no JSON-LD structured data block",
  run(context, active) {
    if (!active) return;
    const hasJsonLd =
      document.querySelector('script[type="application/ld+json"]') !== null;
    context.setAuditCounterBadge(
      KEY,
      hasJsonLd ? "0" : "1",
      hasJsonLd ? "success" : "warning",
    );
  },
};
