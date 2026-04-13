/**
 * MageForge Toolbar Audit – Images without width/height
 *
 * Images missing explicit width and height attributes cause Cumulative Layout Shift (CLS).
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'images-without-dimensions',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 5h11"></path><path d="M12 7l2 -2l-2 -2"></path><path d="M5 3l-2 2l2 2"></path><path d="M19 10v11"></path><path d="M17 19l2 2l2 -2"></path><path d="M21 12l-2 -2l-2 2"></path><path d="M3 12a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v7a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2l0 -7"></path></svg>',
    label: 'Images without Dimensions',
    description: 'Highlight images missing width/height',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const images = Array.from(document.querySelectorAll('img')).filter(img => {
            if (!img.offsetParent && getComputedStyle(img).position !== 'fixed') return false;
            const style = getComputedStyle(img);
            if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') return false;
            return !img.hasAttribute('width') || !img.hasAttribute('height');
        });

        applyHighlight(images, this.key, context);
    },
};
