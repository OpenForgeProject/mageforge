/**
 * MageForge Toolbar – Shared highlight helpers
 *
 * Audits that mark elements by adding a CSS class use these two helpers
 * instead of duplicating the same logic. The CSS class is derived from the
 * audit key: `mageforge-audit-<key>`.
 */

const OVERLAY_CLASS = 'mageforge-audit-img-overlay';

/**
 * Module-level registry: tracks one overlay cleanup function per <img>
 * element and the set of audit keys currently relying on that overlay.
 * Using a WeakMap means entries are automatically eligible for GC once
 * the image node itself is collected.
 *
 * @type {WeakMap<HTMLImageElement, { cleanup: function, keys: Set<string> }>}
 */
const imgOverlayRegistry = new WeakMap();

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
 * Creates a fixed-position overlay <span> that tracks an <img> element's
 * position in the viewport. Shares a single RAF-throttled scroll/resize
 * handler across all active overlays instead of creating one per image.
 * Returns a cleanup function that removes the overlay and deregisters it.
 *
 * @param {HTMLImageElement} img
 * @returns {function} cleanup
 */
function createImgOverlay(img) {
    const overlay = document.createElement('span');
    overlay.className = OVERLAY_CLASS;
    document.body.appendChild(overlay);

    function update() {
        if (!img.isConnected) {
            cleanup();
            imgOverlayRegistry.delete(img);
            return;
        }
        const rect = img.getBoundingClientRect();
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
 * destroys any associated image overlays.
 *
 * @param {string} key - Audit key (e.g. 'images-without-alt')
 */
export function clearHighlight(key) {
    const cls = `mageforge-audit-${key}`;
    document.querySelectorAll(`.${cls}`).forEach(el => {
        el.classList.remove(cls);
        if (el.tagName === 'IMG') {
            const entry = imgOverlayRegistry.get(el);
            if (entry) {
                entry.keys.delete(key);
                if (entry.keys.size === 0) {
                    entry.cleanup();
                    imgOverlayRegistry.delete(el);
                }
            }
        }
    });
}

/**
 * Highlights a set of elements by adding the audit CSS class, scrolls to the
 * first result, and updates the counter badge on the toolbar menu item.
 *
 * For <img> elements a fixed-position overlay is injected so the red
 * background is visible regardless of parent overflow or border-radius.
 *
 * @param {Element[]}  elements - Elements to mark
 * @param {string}     key      - Audit key (e.g. 'images-without-alt')
 * @param {object}     context  - Alpine toolbar component instance
 */
export function applyHighlight(elements, key, context) {
    if (elements.length === 0) {
        context.setAuditCounterBadge(key, '0', 'success');
        return;
    }
    const cls = `mageforge-audit-${key}`;
    elements.forEach(el => {
        el.classList.add(cls);
        if (el.tagName === 'IMG') {
            const existing = imgOverlayRegistry.get(el);
            if (existing) {
                existing.keys.add(key);
            } else {
                imgOverlayRegistry.set(el, {
                    cleanup: createImgOverlay(el),
                    keys: new Set([key]),
                });
            }
        }
    });
    elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    context.setAuditCounterBadge(key, `${elements.length}`, 'error');
}
