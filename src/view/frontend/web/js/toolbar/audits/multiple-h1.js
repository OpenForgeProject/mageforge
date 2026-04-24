/**
 * MageForge Toolbar Audit – Multiple H1
 *
 * A page should have exactly one <h1> element. Multiple H1s confuse screen
 * readers and harm SEO by diluting the primary heading signal.
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'multiple-h1',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M19 18v-8l-2 2"></path><path d="M9 18v-8"></path><path d="M5 14h8"></path><path d="M5 10v8"></path></svg>',
    label: 'Multiple H1',
    description: 'Highlight pages with more than one H1 heading',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const h1s = Array.from(document.querySelectorAll('h1')).filter(el => {
            if (!el.offsetParent && getComputedStyle(el).position !== 'fixed') return false;
            const style = getComputedStyle(el);
            if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') return false;
            return true;
        });

        if (h1s.length <= 1) {
            context.setAuditCounterBadge(this.key, '0', 'success');
            return;
        }

        applyHighlight(h1s, this.key, context);
    },
};
