/**
 * MageForge Toolbar – Shared highlight helpers
 *
 * Audits that mark elements by adding a CSS class use these two helpers
 * instead of duplicating the same logic. The CSS class is derived from the
 * audit key: `mageforge-audit-<key>`.
 */

const OVERLAY_CLASS = 'mageforge-audit-img-overlay';

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

    return () => {
        ro.disconnect();
        window.removeEventListener('scroll', update, { capture: true });
        overlay.remove();
    };
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
        if (el.tagName === 'IMG' && el._mfOverlayCleanup) {
            el._mfOverlayCleanup();
            delete el._mfOverlayCleanup;
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
            el._mfOverlayCleanup = createImgOverlay(el);
        }
    });
    elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    context.setAuditCounterBadge(key, `${elements.length}`, 'error');
}
