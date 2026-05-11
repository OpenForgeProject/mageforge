/**
 * MageForge Toolbar – Shared highlight helpers
 *
 * Audits use applyHighlight() / clearHighlight() to mark any DOM element.
 * Every element gets a fixed-position overlay span that tracks its viewport
 * position, so the highlight works regardless of element type, parent
 * overflow, or border-radius. The audit CSS class (mageforge-audit-<key>)
 * is kept on the element purely as a selector marker for clearHighlight().
 */

const AUDIT_OVERLAY_CLASS = 'mageforge-audit-overlay';

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
 * listener serve all active overlays, throttled via requestAnimationFrame.
 * Attached on the first overlay, torn down when the last one is removed.
 *
 * @type {Map<HTMLSpanElement, function>}
 */
const activeOverlays = new Map();
let rafPending = false;
let sharedRo = null;

function scheduleUpdate() {
    if (rafPending) return;
    rafPending = true;
    requestAnimationFrame(() => {
        rafPending = false;
        // Snapshot before iterating: update() calls may delete entries
        // (image disconnected → cleanup) while we are looping.
        for (const updateFn of [...activeOverlays.values()]) {
            updateFn();
        }
    });
}

/**
 * Creates a fixed-position overlay <span> that tracks any element's
 * bounding box in the viewport. Shares a single RAF-throttled scroll/resize
 * handler across all active overlays instead of creating one per element.
 * Returns a cleanup function that removes the overlay and deregisters it.
 *
 * @param {Element} el
 * @param {'error'|'warning'} [severity='error']
 * @returns {function} cleanup
 */
function createOverlay(el, severity = 'error') {
    const overlay = document.createElement('span');
    overlay.className = AUDIT_OVERLAY_CLASS;
    if (severity === 'warning') overlay.classList.add('mageforge-audit-overlay--warning');
    document.body.appendChild(overlay);

    function update() {
        if (!el.isConnected) {
            cleanup();

            return;
        }
        const rect = el.getBoundingClientRect();
        overlay.style.top    = `${rect.top}px`;
        overlay.style.left   = `${rect.left}px`;
        overlay.style.width  = `${rect.width}px`;
        overlay.style.height = `${rect.height}px`;
    }

    update();

    activeOverlays.set(overlay, update);

    // Attach shared listeners only when the first overlay is created.
    if (activeOverlays.size === 1) {
        sharedRo = new ResizeObserver(scheduleUpdate);
        sharedRo.observe(document.documentElement);
        window.addEventListener('scroll', scheduleUpdate, { passive: true, capture: true });
    }

    // Named so update() can reference it before its var declaration (hoisting).
    function cleanup() {
        activeOverlays.delete(overlay);
        // Tear down shared listeners once no overlays remain.
        if (activeOverlays.size === 0) {
            sharedRo?.disconnect();
            sharedRo = null;
            window.removeEventListener('scroll', scheduleUpdate, { capture: true });
        }
        overlay.remove();
    }

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
    document.querySelectorAll(`.${cls}`).forEach(el => {
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
 */
export function applyHighlight(elements, key, context, options = {}) {
    const severity = options.severity ?? 'error';
    const skipBadge = options.skipBadge ?? false;

    // Never flag elements that are part of the MageForge Toolbar itself
    elements = elements.filter(el => !el.closest('.mageforge-toolbar'));

    if (elements.length === 0) {
        if (!skipBadge) context.setAuditCounterBadge(key, '0', 'success');
        return;
    }
    const cls = `mageforge-audit-${key}`;
    elements.forEach(el => {
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
            elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        context.setAuditCounterBadge(key, `${elements.length}`, severity);
    }
}
