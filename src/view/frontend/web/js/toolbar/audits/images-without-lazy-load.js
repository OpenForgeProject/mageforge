/**
 * MageForge Toolbar Audit – Images without lazy loading
 *
 * Images below the fold that lack loading="lazy" are loaded eagerly,
 * wasting bandwidth and slowing initial page load.
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'images-without-lazy-load',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 12l6 -3l-8 -4l-8 4l6 3"></path><path d="M10 12l-6 3l8 4l8 -4l-6 -3l-2 1l-2 -1" fill="currentColor"></path></svg>',
    label: 'Images without Lazy Load',
    description: 'Highlight off-screen images missing loading="lazy"',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const viewportBottom = window.innerHeight;

        const images = Array.from(document.querySelectorAll('img')).filter(img => {
            if (!img.offsetParent && getComputedStyle(img).position !== 'fixed') return false;
            const style = getComputedStyle(img);
            if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0) return false;
            if (img.getAttribute('loading') === 'lazy') return false;
            const rect = img.getBoundingClientRect();
            return rect.top > viewportBottom;
        });

        applyHighlight(images, this.key, context);
    },
};
