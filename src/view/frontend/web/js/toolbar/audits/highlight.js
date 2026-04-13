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
 * Creates a fixed-position overlay <span> that tracks an <img> element's
 * position in the viewport. Updates on scroll (all containers) and resize.
 * Returns a cleanup function that removes the overlay and all listeners.
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

    // ResizeObserver on <html> catches both window resize and layout shifts
    const ro = new ResizeObserver(update);
    ro.observe(document.documentElement);

    // capture: true catches scroll events from any scrollable container
    window.addEventListener('scroll', update, { passive: true, capture: true });

    // Named function so update() can reference it via hoisting.
    // ro is always assigned before cleanup() is ever invoked (the initial
    // update() call only triggers cleanup() when img.isConnected is false,
    // which cannot happen at construction time).
    function cleanup() {
        ro.disconnect();
        window.removeEventListener('scroll', update, { capture: true });
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
