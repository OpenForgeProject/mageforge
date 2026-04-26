/**
 * MageForge Toolbar Audit – Buttons without type
 *
 * A <button> without an explicit type attribute defaults to type="submit",
 * which can accidentally submit parent forms. Always set type="button",
 * type="submit", or type="reset" explicitly.
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'buttons-without-type',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M8 13v-8.5a1.5 1.5 0 0 1 3 0v7.5"></path><path d="M11 11.5a1.5 1.5 0 0 1 3 0v1.5"></path><path d="M14 12a1.5 1.5 0 0 1 3 0v2"></path><path d="M17 13.5a1.5 1.5 0 0 1 3 0v3.5a6 6 0 0 1 -6 6h-2h.208a6 6 0 0 1 -5.012 -2.7l-.196 -.3c-.312 -.479 -1.407 -2.388 -3.286 -5.728a1.5 1.5 0 0 1 .536 -2.022a1.867 1.867 0 0 1 2.28 .28l1.47 1.47"></path></svg>',
    label: 'Buttons without a type',
    description: 'Highlight a button missing an explicit type attribute (defaults to submit)',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const buttons = Array.from(document.querySelectorAll('button')).filter(btn => {
            const type = btn.getAttribute('type');
            if (type !== null && type.trim() !== '') return false;
            if (!btn.offsetParent && getComputedStyle(btn).position !== 'fixed') return false;
            const style = getComputedStyle(btn);
            if (style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0) return false;
            return true;
        });

        applyHighlight(buttons, this.key, context);
    },
};
