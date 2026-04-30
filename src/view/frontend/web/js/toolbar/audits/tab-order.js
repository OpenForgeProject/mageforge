/**
 * MageForge Toolbar Audit – Tab Order
 *
 * Visualises the keyboard tab order of all focusable elements on the page.
 * Renders numbered badges and connecting lines in the correct navigation order.
 * Elements with tabindex > 0 are highlighted in red (A11y anti-pattern).
 * Clicking the audit again removes the overlay (toggle).
 */

const OVERLAY_ID = 'mageforge-tab-order-overlay';
const CSS_ID = 'mageforge-tab-order-css';
const CSS_URL = new URL('../../../css/audits/tab-order.css', import.meta.url).href;

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    'details > summary',
].join(', ');

function injectCss() {
    if (document.getElementById(CSS_ID)) {
        return;
    }
    const link = document.createElement('link');
    link.id = CSS_ID;
    link.rel = 'stylesheet';
    link.href = CSS_URL;
    document.head.appendChild(link);
}

function isVisible(el) {
    if (el.offsetWidth === 0 || el.offsetHeight === 0) {
        return false;
    }
    const style = getComputedStyle(el);
    return style.visibility !== 'hidden' && style.display !== 'none';
}

/**
 * Returns true if the element lies completely outside the visible area of an
 * ancestor with overflow:hidden/clip (e.g. a carousel slide that is off-canvas).
 */
function isClippedByAncestor(el) {
    const elRect = el.getBoundingClientRect();
    let ancestor = el.parentElement;
    while (ancestor && ancestor !== document.documentElement) {
        const style = getComputedStyle(ancestor);
        const clipsX = ['hidden', 'clip', 'scroll', 'auto'].includes(style.overflowX);
        const clipsY = ['hidden', 'clip', 'scroll', 'auto'].includes(style.overflowY);
        if (clipsX || clipsY) {
            const aRect = ancestor.getBoundingClientRect();
            // No intersection at all → element is fully clipped away
            if (
                elRect.right  <= aRect.left  ||
                elRect.left   >= aRect.right ||
                elRect.bottom <= aRect.top   ||
                elRect.top    >= aRect.bottom
            ) {
                return true;
            }
        }
        ancestor = ancestor.parentElement;
    }
    return false;
}

function getTabIndex(el) {
    const value = parseInt(el.getAttribute('tabindex'), 10);
    return isNaN(value) ? 0 : value;
}

/**
 * Sort focusable elements into true tab order:
 * 1. Elements with tabindex > 0, ascending by value, then DOM order
 * 2. Elements with tabindex = 0 or no tabindex, in DOM order
 */
function sortByTabOrder(elements) {
    const positive = elements.filter(el => getTabIndex(el) > 0)
        .sort((a, b) => {
            const diff = getTabIndex(a) - getTabIndex(b);
            return diff !== 0 ? diff : (
                a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
            );
        });

    const natural = elements.filter(el => getTabIndex(el) <= 0);

    return [...positive, ...natural];
}

/**
 * (Re-)renders the overlay: removes any existing overlay and redraws all
 * badges and connecting SVG lines at their current viewport positions.
 *
 * @param {Element[]} sorted - focusable elements in tab order
 */
function renderOverlay(sorted) {
    const existing = document.getElementById(OVERLAY_ID);
    if (existing) {
        existing.remove();
    }

    // Build overlay
    const overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.className = 'mageforge-tab-order-overlay';

    // SVG layer for connecting lines (rendered behind badges)
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.classList.add('mageforge-tab-order-svg');
    svg.setAttribute('aria-hidden', 'true');
    overlay.appendChild(svg);

    document.body.appendChild(overlay);

    // Place badges and record centre points
    const centres = sorted.map((el, index) => {
        const rect = el.getBoundingClientRect();
        const cx = Math.round(rect.left + rect.width / 2);
        const cy = Math.round(rect.top);

        const clipped = isClippedByAncestor(el);
        const badge = document.createElement('span');
        badge.className = 'mageforge-tab-order-badge' +
            (getTabIndex(el) > 0 ? ' mageforge-tab-order-badge--negative' : '') +
            (clipped ? ' mageforge-tab-order-badge--clipped' : '');
        badge.textContent = index + 1;
        badge.style.left = cx + 'px';
        badge.style.top = cy + 'px';
        overlay.appendChild(badge);

        return { cx, cy, negative: getTabIndex(el) > 0, clipped };
    });

    // Draw connecting lines between consecutive badges
    for (let i = 0; i < centres.length - 1; i++) {
        const from = centres[i];
        const to = centres[i + 1];
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.classList.add('mageforge-tab-order-line');
        if (from.negative || to.negative) {
            line.classList.add('mageforge-tab-order-line--negative');
        } else if (from.clipped || to.clipped) {
            line.classList.add('mageforge-tab-order-line--clipped');
        }
        line.setAttribute('x1', from.cx);
        line.setAttribute('y1', from.cy);
        line.setAttribute('x2', to.cx);
        line.setAttribute('y2', to.cy);
        svg.appendChild(line);
    }
}

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'tab-order',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 7a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path><path d="M14 15a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"></path><path d="M15 6a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M3 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"></path><path d="M9 17l5 -1.5"></path><path d="M6.5 8.5l7.81 5.37"></path><path d="M7 7l8 -1"></path></svg>',
    label: 'Show Tab Order',
    description: 'Visualise keyboard tab order',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        injectCss();

        if (!active) {
            context._tabOrderObserver?.disconnect();
            context._tabOrderObserver = null;
            if (context._tabOrderScrollHandler) {
                document.removeEventListener('scroll', context._tabOrderScrollHandler, { capture: true });
                context._tabOrderScrollHandler = null;
            }
            document.getElementById(OVERLAY_ID)?.remove();
            return;
        }

        const allFocusable = Array.from(document.querySelectorAll(FOCUSABLE_SELECTOR))
            .filter(el => !el.closest('.mageforge-toolbar'))
            .filter(isVisible);

        if (allFocusable.length === 0) {
            context.setAuditCounterBadge('tab-order', '0', 'error');
            return;
        }

        const sorted = sortByTabOrder(allFocusable);
        const hasNegative = sorted.some(el => getTabIndex(el) > 0);

        renderOverlay(sorted);

        // Always recompute from the live DOM so detached / newly added elements are handled correctly
        const rerender = () => renderOverlay(
            sortByTabOrder(
                Array.from(document.querySelectorAll(FOCUSABLE_SELECTOR))
                    .filter(el => !el.closest('.mageforge-toolbar'))
                    .filter(isVisible)
            )
        );

        // Re-render on resize or scroll (e.g. DevTools panel, page scroll)
        context._tabOrderObserver = new ResizeObserver(() => {
            if (!document.getElementById(OVERLAY_ID)) {
                context._tabOrderObserver?.disconnect();
                context._tabOrderObserver = null;
                return;
            }
            rerender();
        });
        context._tabOrderObserver.observe(document.body);

        let scrollRaf = null;
        context._tabOrderScrollHandler = () => {
            if (scrollRaf) return;
            scrollRaf = requestAnimationFrame(() => {
                scrollRaf = null;
                if (document.getElementById(OVERLAY_ID)) {
                    rerender();
                }
            });
        };
        document.addEventListener('scroll', context._tabOrderScrollHandler, { capture: true, passive: true });

        const type = hasNegative ? 'error' : 'success';
        context.setAuditCounterBadge('tab-order', `${sorted.length}`, type);
    },
};
