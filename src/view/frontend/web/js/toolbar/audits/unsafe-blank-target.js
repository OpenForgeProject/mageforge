/**
 * MageForge Toolbar Audit – Unsafe target="_blank"
 *
 * Links with target="_blank" that have neither rel="noopener" nor
 * rel="noreferrer" are vulnerable to reverse tabnapping: the opened page
 * can access window.opener and redirect the original tab.
 * Either token alone is sufficient to prevent the attack.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'unsafe-blank-target',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6"></path><path d="M11 13l9 -9"></path><path d="M15 4h5v5"></path></svg>',
    label: 'Unsafe Blank Target',
    description: 'Highlight target="_blank" links with neither rel="noopener" nor rel="noreferrer"',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const elements = Array.from(
            document.querySelectorAll('a[target="_blank"]')
        ).filter(el => {
            if (!el.offsetParent && getComputedStyle(el).position !== 'fixed') return false;
            const style = getComputedStyle(el);
            if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0) return false;

            const rel = (el.getAttribute('rel') || '').toLowerCase().split(/\s+/);
            return !rel.includes('noopener') && !rel.includes('noreferrer');
        });

        applyHighlight(elements, this.key, context);
    },
};
