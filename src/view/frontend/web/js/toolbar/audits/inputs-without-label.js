/**
 * MageForge Toolbar Audit – Inputs without label
 *
 * Form inputs without an associated label or aria-label are inaccessible
 * to screen reader users.
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'inputs-without-label',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 3a3 3 0 0 0 -3 3v12a3 3 0 0 0 3 3"></path><path d="M6 3a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3"></path><path d="M13 7h7a1 1 0 0 1 1 1v8a1 1 0 0 1 -1 1h-7"></path><path d="M5 7h-1a1 1 0 0 0 -1 1v8a1 1 0 0 0 1 1h1"></path><path d="M17 12h.01"></path><path d="M13 12h.01"></path></svg>',
    label: 'Inputs without Label',
    description: 'Highlight form inputs missing a label or aria-label',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const inputs = Array.from(document.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"]), select, textarea'
        )).filter(input => {
            // aria-label or aria-labelledby present
            if (input.hasAttribute('aria-label') && input.getAttribute('aria-label').trim()) return false;
            if (input.hasAttribute('aria-labelledby') && input.getAttribute('aria-labelledby').trim().split(/\s+/).some(id => document.getElementById(id))) return false;
            // title as fallback label
            if (input.hasAttribute('title') && input.getAttribute('title').trim()) return false;
            // <label for="id"> association
            if (input.id && document.querySelector(`label[for="${CSS.escape(input.id)}"]`)) return false;
            // implicit label (input is a descendant of <label>)
            if (input.closest('label')) return false;
            return true;
        });

        applyHighlight(inputs, this.key, context);
    },
};
