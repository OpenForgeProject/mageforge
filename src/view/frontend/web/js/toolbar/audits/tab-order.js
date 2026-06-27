/**
 * MageForge Toolbar Audit – Tab Order
 *
 * Visualises the keyboard tab order of all focusable elements on the page.
 * Renders numbered badges and connecting lines in the correct navigation order.
 * Elements with tabindex > 0 are highlighted in red (A11y anti-pattern).
 * Clicking the audit again removes the overlay (toggle).
 */

import { addSharedCallback, removeSharedCallback } from "./highlight.js";

const OVERLAY_ID = "mageforge-tab-order-overlay";
const CSS_ID = "mageforge-tab-order-css";
const CSS_URL = new URL("../../../css/audits/tab-order.css", import.meta.url)
  .href;

const FOCUSABLE_SELECTOR = [
  "a[href]",
  "button:not([disabled])",
  'input:not([disabled]):not([type="hidden"])',
  "select:not([disabled])",
  "textarea:not([disabled])",
  '[tabindex]:not([tabindex="-1"])',
  "details > summary",
].join(", ");

function injectCss() {
  if (document.getElementById(CSS_ID)) {
    return;
  }
  const link = document.createElement("link");
  link.id = CSS_ID;
  link.rel = "stylesheet";
  link.href = CSS_URL;
  document.head.appendChild(link);
}

function isVisible(el) {
  if (el.offsetWidth === 0 || el.offsetHeight === 0) {
    return false;
  }
  const style = getComputedStyle(el);
  return style.visibility !== "hidden" && style.display !== "none";
}

/**
 * Cached overlay state: the sorted element list and their corresponding badge
 * and SVG line DOM nodes, stored after renderOverlay() to enable cheap
 * repositioning without rebuilding the DOM on scroll/resize.
 *
 * @type {{ sorted: Element[], badges: HTMLSpanElement[], lines: SVGLineElement[] } | null}
 */
let overlayState = null;

/**
 * Returns true if the element lies completely outside the visible area of an
 * ancestor with overflow:hidden/clip (e.g. a carousel slide that is off-canvas).
 *
 * @param {Element} el
 * @param {DOMRect} [preComputedRect] - Pre-computed bounding rect to avoid a duplicate call
 */
function isClippedByAncestor(el, preComputedRect) {
  const elRect = preComputedRect ?? el.getBoundingClientRect();
  let ancestor = el.parentElement;
  while (ancestor && ancestor !== document.documentElement) {
    const style = getComputedStyle(ancestor);
    const clipsX = ["hidden", "clip", "scroll", "auto"].includes(
      style.overflowX,
    );
    const clipsY = ["hidden", "clip", "scroll", "auto"].includes(
      style.overflowY,
    );
    if (clipsX || clipsY) {
      const aRect = ancestor.getBoundingClientRect();
      // No intersection at all → element is fully clipped away
      if (
        elRect.right <= aRect.left ||
        elRect.left >= aRect.right ||
        elRect.bottom <= aRect.top ||
        elRect.top >= aRect.bottom
      ) {
        return true;
      }
    }
    ancestor = ancestor.parentElement;
  }
  return false;
}

function getTabIndex(el) {
  const value = parseInt(el.getAttribute("tabindex"), 10);
  return isNaN(value) ? 0 : value;
}

/**
 * Sort focusable elements into true tab order:
 * 1. Elements with tabindex > 0, ascending by value, then DOM order
 * 2. Elements with tabindex = 0 or no tabindex, in DOM order
 */
function sortByTabOrder(elements) {
  const positive = elements
    .filter((el) => getTabIndex(el) > 0)
    .sort((a, b) => {
      const diff = getTabIndex(a) - getTabIndex(b);
      return diff !== 0
        ? diff
        : a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING
          ? -1
          : 1;
    });

  const natural = elements.filter((el) => getTabIndex(el) <= 0);

  return [...positive, ...natural];
}

/**
 * Builds the full tab-order overlay (badges + SVG lines) from scratch.
 * Uses a batched read-then-write pattern to avoid layout thrashing.
 * Stores element → badge/line references in overlayState so that
 * repositionOverlay() can update positions cheaply on every scroll/resize
 * frame without rebuilding the DOM.
 *
 * @param {Element[]} sorted - focusable elements in tab order
 */
function renderOverlay(sorted) {
  document.getElementById(OVERLAY_ID)?.remove();

  const overlay = document.createElement("div");
  overlay.id = OVERLAY_ID;
  overlay.className = "mageforge-tab-order-overlay";

  // SVG layer for connecting lines (rendered behind badges)
  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.classList.add("mageforge-tab-order-svg");
  svg.setAttribute("aria-hidden", "true");
  overlay.appendChild(svg);

  document.body.appendChild(overlay);

  // --- Batched read phase: all getBoundingClientRect calls before any write ---
  const rects = sorted.map((el) => el.getBoundingClientRect());
  const clipped = sorted.map((el, i) => isClippedByAncestor(el, rects[i]));

  // --- Batched write phase: create and position all badges ---
  const badges = sorted.map((el, index) => {
    const cx = Math.round(rects[index].left + rects[index].width / 2);
    const cy = Math.round(rects[index].top);
    const badge = document.createElement("span");
    badge.className =
      "mageforge-tab-order-badge" +
      (getTabIndex(el) > 0 ? " mageforge-tab-order-badge--negative" : "") +
      (clipped[index] ? " mageforge-tab-order-badge--clipped" : "");
    badge.textContent = index + 1;
    badge.style.left = cx + "px";
    badge.style.top = cy + "px";
    overlay.appendChild(badge);
    return badge;
  });

  // Draw connecting lines between consecutive badges
  const lines = [];
  for (let i = 0; i < sorted.length - 1; i++) {
    const from = rects[i];
    const to = rects[i + 1];
    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
    line.classList.add("mageforge-tab-order-line");
    if (getTabIndex(sorted[i]) > 0 || getTabIndex(sorted[i + 1]) > 0) {
      line.classList.add("mageforge-tab-order-line--negative");
    } else if (clipped[i] || clipped[i + 1]) {
      line.classList.add("mageforge-tab-order-line--clipped");
    }
    line.setAttribute("x1", Math.round(from.left + from.width / 2));
    line.setAttribute("y1", Math.round(from.top));
    line.setAttribute("x2", Math.round(to.left + to.width / 2));
    line.setAttribute("y2", Math.round(to.top));
    svg.appendChild(line);
    lines.push(line);
  }

  overlayState = { sorted, badges, lines };
}

/**
 * Updates the position of all tab-order badges and SVG connecting lines to
 * match the current viewport positions of their elements. Uses a batched
 * read-then-write pattern to avoid layout thrashing.
 *
 * Called via the shared RAF scheduler on every scroll/resize frame instead of
 * rebuilding the entire overlay DOM from scratch.
 */
function repositionOverlay() {
  if (!overlayState || !document.getElementById(OVERLAY_ID)) return;

  const { sorted, badges, lines } = overlayState;

  // --- Batched read phase ---
  const rects = sorted.map((el) => el.getBoundingClientRect());

  // --- Batched write phase: badge positions ---
  badges.forEach((badge, i) => {
    badge.style.left = Math.round(rects[i].left + rects[i].width / 2) + "px";
    badge.style.top = Math.round(rects[i].top) + "px";
  });

  // --- Batched write phase: SVG line endpoints ---
  lines.forEach((line, i) => {
    line.setAttribute("x1", Math.round(rects[i].left + rects[i].width / 2));
    line.setAttribute("y1", Math.round(rects[i].top));
    line.setAttribute(
      "x2",
      Math.round(rects[i + 1].left + rects[i + 1].width / 2),
    );
    line.setAttribute("y2", Math.round(rects[i + 1].top));
  });
}

/** @type {import('./index.js').AuditDefinition} */
export default {
  key: "tab-order",
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 7a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path><path d="M14 15a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path><path d="M15 6a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M3 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M9 17l5 -1.5"></path><path d="M6.5 8.5l7.81 5.37"></path><path d="M7 7l8 -1"></path></svg>',
  label: "Show Tab Order",
  description: "Visualise keyboard tab order",

  /**
   * @param {object} context - Alpine toolbar component instance
   * @param {boolean} active  - true = activate, false = deactivate
   */
  run(context, active) {
    injectCss();

    if (!active) {
      removeSharedCallback(repositionOverlay);
      overlayState = null;
      document.getElementById(OVERLAY_ID)?.remove();
      return;
    }

    const allFocusable = Array.from(
      document.querySelectorAll(FOCUSABLE_SELECTOR),
    )
      .filter((el) => !el.closest(".mageforge-toolbar"))
      .filter(isVisible);

    if (allFocusable.length === 0) {
      context.setAuditCounterBadge("tab-order", "0", "error");
      return;
    }

    const sorted = sortByTabOrder(allFocusable);
    const hasNegative = sorted.some((el) => getTabIndex(el) > 0);

    renderOverlay(sorted);

    // Register repositionOverlay as a shared per-frame callback so badge
    // positions are updated on scroll/resize without rebuilding the DOM.
    addSharedCallback(repositionOverlay);

    const type = hasNegative ? "error" : "success";
    context.setAuditCounterBadge("tab-order", `${sorted.length}`, type);
  },
};
