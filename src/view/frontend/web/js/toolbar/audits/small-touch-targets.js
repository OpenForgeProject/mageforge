/**
 * MageForge Toolbar Audit – Small Touch Targets
 *
 * Interactive elements smaller than 44×44 CSS pixels are hard to tap on
 * touch devices (WCAG 2.5.5 Target Size, Level AAA; WCAG 2.5.8 Level AA).
 *
 * Icon source: Tabler Icons (MIT)
 */

import { applyHighlight, clearHighlight } from './highlight.js';

const MIN_SIZE = 24;

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'small-touch-targets',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path><path d="M12 7a5 5 0 1 0 5 5"></path><path d="M15 3l0 4l4 0"></path><path d="M15 7l5 -5"></path></svg>',
    label: 'Small Touch Targets',
    description: `Highlight interactive elements smaller than ${MIN_SIZE}×${MIN_SIZE} px (WCAG 2.5.8 AA)`,

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const selector = [
            'a[href]:not([aria-disabled="true"])',
            'button:not([disabled]):not([aria-disabled="true"])',
            '[role="button"]:not([aria-disabled="true"])',
            '[role="link"]:not([aria-disabled="true"])',
            '[role="checkbox"]:not([aria-disabled="true"])',
            '[role="radio"]:not([aria-disabled="true"])',
            '[role="switch"]:not([aria-disabled="true"])',
            'input:not([type="hidden"]):not([disabled]):not([aria-disabled="true"])',
            'select:not([disabled]):not([aria-disabled="true"])',
            'textarea:not([disabled]):not([aria-disabled="true"])',
        ].join(', ');

        const elements = Array.from(document.querySelectorAll(selector)).filter(el => {
            if (el.matches('[disabled], [aria-disabled="true"]')) return false;
            if (!el.offsetParent && getComputedStyle(el).position !== 'fixed') return false;
            const style = getComputedStyle(el);
            if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') return false;

            const rect = el.getBoundingClientRect();
            return rect.width < MIN_SIZE || rect.height < MIN_SIZE;
        });

        applyHighlight(elements, this.key, context);
    },
};
