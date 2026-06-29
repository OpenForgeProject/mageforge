// ── Constants ──────────────────────────────────────────────────────────────

export const LOGO_SVG_PATH =
  "M176 0L0 101.614V297L176 398.614L352 297V101.614L176 0ZM39 275.5V124L76.2391 101.614L101.5 162L126.5 73.4393L164.5 51.5V346.939L126.5 325V188L108.5 239H95L76.2391 188V297L39 275.5ZM187.5 346.939V51.5L313 124V170H275.5V146.368L225.5 117.5V188H280V226.5H225.5V325L187.5 346.939Z";

export const ICON_HOME =
  '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';

export const GROUP_ICONS = {
  wcag: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
  "html-quality":
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
  performance:
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>',
  seo: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
  "structured-data":
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4a2 2 0 0 0-2 2v3a2 3 0 0 1-2 3a2 3 0 0 1 2 3v3a2 2 0 0 0 2 2"></path><path d="M17 4a2 2 0 0 1 2 2v3a2 3 0 0 1 2 3a2 3 0 0 1-2 3v3a2 2 0 0 1-2 2"></path></svg>',
};

export const GAUGE_ARC_LENGTH = 157.08; // π × r(50) — half-arc stroke length
export const SCORE_RING_CIRCUMFERENCE = 113.1; // 2π × r(18) — ring stroke length

export function createLogoSvg(fill) {
  return `<svg width="24" height="27" viewBox="0 0 352 399" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="${LOGO_SVG_PATH}" fill="${fill}"></path></svg>`;
}

/**
 * Generate a short random ID for use in SVG gradient/clip-path IDs.
 * Falls back to Math.random() when crypto.randomUUID() is unavailable
 * (e.g. non-secure contexts).
 *
 * @param {string} prefix
 * @returns {string}
 */
export function generateId(prefix) {
  const rand =
    globalThis.crypto?.randomUUID?.()?.slice(0, 8) ??
    Math.random().toString(36).slice(2, 10);
  return `mf-${prefix}-${rand}`;
}
