/**
 * MageForge Toolbar Audit – Images without ALT
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'images-without-alt',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12"></path><path d="M4 16l16 0"></path><path d="M4 12l3 -3c.928 -.893 2.072 -.893 3 0l4 4"></path><path d="M13 12l2 -2c.928 -.893 2.072 -.893 3 0l2 2"></path><path d="M14 7l.01 0"></path></svg>',
    label: 'Images without ALT',
    description: 'Highlight images without alt attributes',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const visible = Array.from(document.querySelectorAll('img')).filter(img => {
            if (!img.offsetParent && getComputedStyle(img).position !== 'fixed') return false;
            const style = getComputedStyle(img);
            return style.visibility !== 'hidden' && style.display !== 'none' && style.opacity !== '0';
        });

        // Errors: missing alt attribute or whitespace-only alt (e.g. alt="  ")
        const errors = visible.filter(img => {
            if (!img.hasAttribute('alt')) return true;
            const val = img.getAttribute('alt');
            return val.length > 0 && val.trim() === '';
        });

        // Warnings: explicit alt="" – intentionally decorative, but flagged for review
        const warnings = visible.filter(img => img.getAttribute('alt') === '');

        const total = errors.length + warnings.length;
        if (total === 0) {
            context.setAuditCounterBadge(this.key, '0', 'success');
            return;
        }

        applyHighlight(errors,   this.key, context, { skipBadge: true });
        applyHighlight(warnings, this.key, context, { severity: 'warning', skipBadge: true });

        // Scroll to first issue (errors take priority)
        const first = errors[0] ?? warnings[0];
        if (first && !context._batchRunning) first.scrollIntoView({ behavior: 'smooth', block: 'center' });

        context.setAuditCounterBadge(this.key, `${total}`, errors.length > 0 ? 'error' : 'warning');
    },
};
