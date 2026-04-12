/**
 * MageForge Toolbar Audit – Low contrast text
 *
 * Highlights text elements that fail WCAG AA contrast requirements:
 * - 4.5:1 for normal text
 * - 3:1 for large text (>=18pt or >=14pt bold)
 */

const HIGHLIGHT_CLASS = 'mageforge-audit-low-contrast';

const _colorCanvas = document.createElement('canvas');
_colorCanvas.width = _colorCanvas.height = 1;
const _colorCtx = _colorCanvas.getContext('2d', { willReadFrequently: true });

/**
 * Parse any CSS color string the browser understands into [r, g, b, a].
 * Uses Canvas to let the browser handle all color spaces (rgb, oklch, lab, etc.).
 *
 * @param {string} color
 * @returns {number[]|null} [r, g, b, a] where a is 0–1, or null on failure
 */
function parseColor(color) {
    if (!color || color === 'transparent') return null;
    _colorCtx.clearRect(0, 0, 1, 1);
    _colorCtx.fillStyle = '#fe01fe'; // sentinel: vivid pink never used as real text color
    const sentinel = _colorCtx.fillStyle; // read back canonical form
    _colorCtx.fillStyle = color;
    // If fillStyle is unchanged, the browser could not parse the color
    if (_colorCtx.fillStyle === sentinel) return null;
    _colorCtx.fillRect(0, 0, 1, 1);
    const d = _colorCtx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2], d[3] / 255];
}

/**
 * Walk up the DOM to find the effective (non-transparent) background color,
 * blending semi-transparent layers over the accumulated background.
 * Returns null if no parseable background is found (e.g. oklch/lab color spaces).
 *
 * @param {Element} el
 * @returns {number[]|null} [r, g, b] or null
 */
function effectiveBackground(el) {
    const layers = [];
    let node = el;
    let foundOpaque = false;
    while (node && node !== document.documentElement) {
        const bg = getComputedStyle(node).backgroundColor;
        const parsed = parseColor(bg);
        if (parsed) {
            if (parsed[3] > 0) {
                layers.push(parsed);
                if (parsed[3] >= 1) {
                    foundOpaque = true;
                    break;
                }
            }
        }
        node = node.parentElement;
    }

    if (!foundOpaque && layers.length === 0) {
        return [255, 255, 255]; // no background found – assume white page
    }

    // Composite layers from bottom to top over white
    let [r, g, b] = [255, 255, 255];
    for (let i = layers.length - 1; i >= 0; i--) {
        const [lr, lg, lb, la] = layers[i];
        r = Math.round(lr * la + r * (1 - la));
        g = Math.round(lg * la + g * (1 - la));
        b = Math.round(lb * la + b * (1 - la));
    }
    return [r, g, b];
}

/**
 * Returns true if the element has direct text content (not just from children).
 *
 * @param {Element} el
 * @returns {boolean}
 */
function hasDirectText(el) {
    for (const node of el.childNodes) {
        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if the element is visible on screen.
 *
 * @param {Element} el
 * @returns {boolean}
 */
function isVisible(el) {
    if (el.offsetWidth === 0 && el.offsetHeight === 0) return false;
    const style = getComputedStyle(el);
    return style.visibility !== 'hidden' && style.display !== 'none' && parseFloat(style.opacity) > 0;
}

/**
 * sRGB channel to linear light value.
 *
 * @param {number} c - 0–255
 * @returns {number}
 */
function toLinear(c) {
    const s = c / 255;
    return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
}

/**
 * Relative luminance per WCAG 2.1.
 *
 * @param {number[]} rgb
 * @returns {number}
 */
function luminance([r, g, b]) {
    return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
}

/**
 * WCAG contrast ratio between two RGB colours.
 *
 * @param {number[]} fg
 * @param {number[]} bg
 * @returns {number}
 */
function contrastRatio(fg, bg) {
    const l1 = luminance(fg);
    const l2 = luminance(bg);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Determine if an element qualifies as "large text" (WCAG definition).
 *
 * @param {Element} el
 * @returns {boolean}
 */
function isLargeText(el) {
    const style = getComputedStyle(el);
    const size = parseFloat(style.fontSize);
    const weight = parseInt(style.fontWeight, 10);
    const ptSize = size * (72 / 96);
    return ptSize >= 18 || (ptSize >= 14 && weight >= 700);
}

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'low-contrast-text',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-9 1.732a8 8 0 0 0 4.001 14.928l-.001 -16a8 8 0 0 0 -4 1.072"></path></svg>',
    label: 'Low Contrast Text',
    description: 'Highlight text failing WCAG AA contrast',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            document.querySelectorAll(`.${HIGHLIGHT_CLASS}`).forEach(el => el.classList.remove(HIGHLIGHT_CLASS));
            return;
        }

        const candidates = Array.from(document.querySelectorAll(
            'p, a, h1, h2, h3, h4, h5, h6, li, td, th, label, button'
        )).filter(el => {
            // Never audit the toolbar itself
            if (el.closest('.mageforge-toolbar')) return false;
            if (!isVisible(el)) return false;
            // Only elements with direct text nodes (avoids duplicates from nested wrappers)
            return hasDirectText(el);
        });

        const failing = candidates.filter(el => {
            const style = getComputedStyle(el);
            const fg = parseColor(style.color);
            if (!fg || fg[3] === 0) return false;

            const bg = effectiveBackground(el);
            const ratio = contrastRatio(fg, bg);
            const threshold = isLargeText(el) ? 3 : 4.5;
            return ratio < threshold;
        });

        if (failing.length === 0) {
            context.setAuditCounterBadge('low-contrast-text', '0', 'success');
            return;
        }

        failing.forEach(el => el.classList.add(HIGHLIGHT_CLASS));
        failing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        context.setAuditCounterBadge('low-contrast-text', `${failing.length}`, 'error');
    },
};
