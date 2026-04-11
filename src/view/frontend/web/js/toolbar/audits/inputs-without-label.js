/**
 * MageForge Toolbar Audit – Inputs without label
 *
 * Form inputs without an associated label or aria-label are inaccessible
 * to screen reader users.
 */

const HIGHLIGHT_CLASS = 'mageforge-audit-inputs-without-label';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'inputs-without-label',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3a3 3 0 0 0 -3 3v12a3 3 0 0 0 3 3"/><path d="M6 3a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3"/><path d="M13 7h7a1 1 0 0 1 1 1v8a1 1 0 0 1 -1 1h-7"/><path d="M5 7h-1a1 1 0 0 0 -1 1v8a1 1 0 0 0 1 1h1"/><path d="M17 12h.01"/><path d="M13 12h.01"/></svg>',
    label: 'Inputs without Label',
    description: 'Highlight form inputs missing a label or aria-label',

    /**
     * @param {object} context - Alpine toolbar component instance
     */
    run(context) {
        const existing = document.querySelectorAll(`.${HIGHLIGHT_CLASS}`);
        if (existing.length > 0) {
            existing.forEach(el => el.classList.remove(HIGHLIGHT_CLASS));
            return;
        }

        const inputs = Array.from(document.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"]), select, textarea'
        )).filter(input => {
            // aria-label or aria-labelledby present
            if (input.hasAttribute('aria-label') && input.getAttribute('aria-label').trim()) return false;
            if (input.hasAttribute('aria-labelledby') && document.getElementById(input.getAttribute('aria-labelledby'))) return false;
            // title as fallback label
            if (input.hasAttribute('title') && input.getAttribute('title').trim()) return false;
            // <label for="id"> association
            if (input.id && document.querySelector(`label[for="${input.id}"]`)) return false;
            // implicit label (input is a descendant of <label>)
            if (input.closest('label')) return false;
            return true;
        });

        if (inputs.length === 0) {
            context.setAuditCounterBadge('inputs-without-label', '0', 'success');
            return;
        }

        inputs.forEach(el => el.classList.add(HIGHLIGHT_CLASS));
        inputs[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        context.setAuditCounterBadge('inputs-without-label', `${inputs.length}`, 'error');
    },
};
