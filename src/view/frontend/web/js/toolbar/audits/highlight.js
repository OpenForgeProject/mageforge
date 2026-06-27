/**
 * MageForge Toolbar – Shared highlight helpers
 *
 * Audits use applyHighlight() / clearHighlight() to mark any DOM element.
 * Every element gets a fixed-position overlay span that tracks its viewport
 * position, so the highlight works regardless of element type, parent
 * overflow, or border-radius. The audit CSS class (mageforge-audit-<key>)
 * is kept on the element purely as a selector marker for clearHighlight().
 */

const AUDIT_OVERLAY_CLASS = "mageforge-audit-overlay";

/**
 * Module-level registry: tracks one overlay per element and the set of
 * audit keys currently relying on it. WeakMap entries are automatically
 * eligible for GC when the element is collected.
 *
 * @type {WeakMap<Element, { cleanup: function, keys: Set<string> }>}
 */
const overlayRegistry = new WeakMap();

/**
 * Shared update machinery – a single ResizeObserver and capturing scroll
 * listener serve all active overlays and any registered extra callbacks,
 * throttled via requestAnimationFrame.
 * Attached when the first overlay is created or the first extra callback is
 * registered; torn down when both collections are empty again.
 *
 * activeOverlays maps each overlay <span> to its tracked element and cleanup
 * function so the RAF can perform a single batched read phase followed by a
 * single write phase, avoiding forced layout reflows between reads and writes.
 *
 * @type {Map<HTMLSpanElement, { el: Element, cleanup: function }>}
 */
const activeOverlays = new Map();

/**
 * Additional per-frame callbacks registered by other audit modules (e.g.
 * tab-order) that need to piggyback on the shared scroll/resize listeners.
 *
 * @type {Set<function>}
 */
const extraCallbacks = new Set();

let rafPending = false;
let sharedRo = null;

/** Attach the shared ResizeObserver and scroll listener if not already active. */
function attachSharedListeners() {
  if (sharedRo) return;
  sharedRo = new ResizeObserver(scheduleUpdate);
  sharedRo.observe(document.documentElement);
  window.addEventListener("scroll", scheduleUpdate, {
    passive: true,
    capture: true,
  });
}

/** Detach shared listeners once neither overlays nor extra callbacks need them. */
function detachSharedListeners() {
  if (activeOverlays.size > 0 || extraCallbacks.size > 0) return;
  sharedRo?.disconnect();
  sharedRo = null;
  window.removeEventListener("scroll", scheduleUpdate, { capture: true });
}

/**
 * Schedule a single RAF-throttled update pass that:
 *   1. Reads  all overlay bounding rects in one batched read  phase
 *   2. Writes all overlay positions      in one batched write phase
 *   3. Runs any registered extra callbacks (e.g. tab-order repositioning)
 *
 * Separating reads from writes prevents the browser from performing a full
 * layout reflow between every read–write pair ("layout thrashing").
 */
function scheduleUpdate() {
  if (rafPending) return;
  rafPending = true;
  requestAnimationFrame(() => {
    rafPending = false;

    // --- Batched read phase: snapshot all bounding rects before any write ---
    const entries = [...activeOverlays.entries()];
    const rects = entries.map(([, { el }]) =>
      el.isConnected ? el.getBoundingClientRect() : null,
    );

    // --- Batched write phase: update all overlay positions ---
    entries.forEach(([overlay, { cleanup }], i) => {
      const rect = rects[i];
      if (!rect) {
        // Element removed from the DOM – clean up its overlay.
        cleanup();
        return;
      }
      overlay.style.top = `${rect.top}px`;
      overlay.style.left = `${rect.left}px`;
      overlay.style.width = `${rect.width}px`;
      overlay.style.height = `${rect.height}px`;
    });

    // --- Extra per-frame callbacks (e.g. tab-order badge repositioning) ---
    for (const cb of extraCallbacks) {
      cb();
    }
  });
}

/**
 * Register a callback to be invoked every animation frame alongside overlay
 * updates. The shared scroll / resize listeners are kept alive while at least
 * one callback is registered, even if no overlay highlights are active.
 *
 * @param {function} fn
 */
export function addSharedCallback(fn) {
  extraCallbacks.add(fn);
  attachSharedListeners();
}

/**
 * Remove a previously registered per-frame callback. Tears down shared
 * listeners when no overlays or callbacks remain.
 *
 * @param {function} fn
 */
export function removeSharedCallback(fn) {
  extraCallbacks.delete(fn);
  detachSharedListeners();
}

/**
 * Creates a fixed-position overlay <span> that tracks any element's bounding
 * box in the viewport. Participates in the shared RAF-throttled update cycle.
 * Returns a cleanup function that removes the overlay and deregisters it.
 *
 * @param {Element} el
 * @param {'error'|'warning'} [severity='error']
 * @returns {function} cleanup
 */
function createOverlay(el, severity = "error") {
  const overlay = document.createElement("span");
  overlay.className = AUDIT_OVERLAY_CLASS;
  if (severity === "warning")
    overlay.classList.add("mageforge-audit-overlay--warning");
  document.body.appendChild(overlay);

  // Set initial position synchronously so the overlay appears immediately.
  if (el.isConnected) {
    const rect = el.getBoundingClientRect();
    overlay.style.top = `${rect.top}px`;
    overlay.style.left = `${rect.left}px`;
    overlay.style.width = `${rect.width}px`;
    overlay.style.height = `${rect.height}px`;
  }

  function cleanup() {
    activeOverlays.delete(overlay);
    overlay.remove();
    detachSharedListeners();
  }

  activeOverlays.set(overlay, { el, cleanup });
  attachSharedListeners();

  return cleanup;
}

/**
 * Removes the highlight class from all previously marked elements and
 * destroys their overlays once no audit keys remain.
 *
 * @param {string} key - Audit key (e.g. 'images-without-alt')
 */
export function clearHighlight(key) {
  const cls = `mageforge-audit-${key}`;
  document.querySelectorAll(`.${cls}`).forEach((el) => {
    el.classList.remove(cls);
    const entry = overlayRegistry.get(el);
    if (entry) {
      entry.keys.delete(key);
      if (entry.keys.size === 0) {
        entry.cleanup();
        overlayRegistry.delete(el);
      }
    }
  });
}

/**
 * Build a short, human-readable CSS selector for labelling a finding.
 *
 * @param {Element} el
 * @returns {string}
 */
export function getReadableSelector(el) {
  if (el.id) return `#${el.id}`;
  const tag = el.tagName.toLowerCase();
  const classes = [...el.classList]
    .filter((c) => !c.startsWith("mageforge"))
    .slice(0, 2)
    .join(".");
  if (classes) return `${tag}.${classes}`;
  const ariaLabel = el.getAttribute("aria-label");
  if (ariaLabel) return `${tag}[aria-label]`;
  if (el.name) return `${tag}[name="${el.name}"]`;
  if (tag === "img" && el.src) {
    const base = el.src.split("/").pop().split("?")[0].slice(0, 24);
    return `img/${base}`;
  }
  return tag;
}

/**
 * Highlights a set of elements by injecting a positioned overlay, scrolls to
 * the first result, and updates the counter badge on the toolbar menu item.
 * Works for any element type – no special casing required in audit code.
 *
 * @param {Element[]}  elements        - Elements to mark
 * @param {string}     key             - Audit key (e.g. 'images-without-alt')
 * @param {object}     context         - Alpine toolbar component instance
 * @param {object}     [options={}]    - Options
 * @param {'error'|'warning'} [options.severity='error'] - Visual severity level
 * @param {boolean}    [options.skipBadge=false]  - Skip badge + scroll update
 * @param {boolean}    [options.autoFindings=false] - Auto-generate findings list for "Affected Elements" panel
 * @param {(el: Element, severity: string) => {selector?: string, action?: string}} [options.formatFinding] - Custom formatter for each finding row
 */
export function applyHighlight(elements, key, context, options = {}) {
  const severity = options.severity ?? "error";
  const skipBadge = options.skipBadge ?? false;
  const autoFindings = options.autoFindings ?? false;
  const formatFinding = options.formatFinding;

  // Never flag elements that are part of the MageForge Toolbar itself
  elements = elements.filter((el) => !el.closest(".mageforge-toolbar"));

  if (elements.length === 0) {
    if (!skipBadge) context.setAuditCounterBadge(key, "0", "success");
    if (autoFindings) context.setAuditFindings(key, []);
    return;
  }
  const cls = `mageforge-audit-${key}`;
  elements.forEach((el) => {
    el.classList.add(cls);
    const existing = overlayRegistry.get(el);
    if (existing) {
      existing.keys.add(key);
    } else {
      overlayRegistry.set(el, {
        cleanup: createOverlay(el, severity),
        keys: new Set([key]),
      });
    }
  });
  if (!skipBadge) {
    if (!context._batchRunning) {
      elements[0].scrollIntoView({ behavior: "smooth", block: "center" });
    }
    context.setAuditCounterBadge(key, `${elements.length}`, severity);
  }

  // Auto-generate findings for the "Affected Elements" panel
  if (autoFindings) {
    const findings = elements.map((el) => {
      const base = { el, selector: getReadableSelector(el), severity };
      return formatFinding ? { ...base, ...formatFinding(el, severity) } : base;
    });
    context.setAuditFindings(key, findings);
  }
}
