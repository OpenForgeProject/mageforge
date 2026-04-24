/**
 * MageForge Toolbar Audit – Duplicate IDs
 *
 * IDs must be unique per document. Duplicate IDs break label associations,
 * aria-labelledby / aria-describedby references, fragment links, and cause
 * unpredictable behaviour with JavaScript querySelector.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'duplicate-ids',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 8a4 4 0 0 1 4 4a4 4 0 0 1 4 -4"></path><path d="M7 16a4 4 0 0 0 4 -4a4 4 0 0 0 4 4"></path><path d="M9 12h6"></path><path d="M3 12h2"></path><path d="M19 12h2"></path></svg>',
    label: 'Duplicate IDs',
    description: 'Highlight elements sharing an ID with at least one other element',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            context.setAuditDescription(this.key, this.description);
            return;
        }

        /** @type {Map<string, Element[]>} */
        const idMap = new Map();

        document.querySelectorAll('[id]').forEach(el => {
            const id = el.id;
            if (!id) return;
            if (!idMap.has(id)) {
                idMap.set(id, []);
            }
            idMap.get(id).push(el);
        });

        /** @type {string[]} */
        const duplicateIdNames = [];
        const duplicates = [];
        idMap.forEach((els, id) => {
            if (els.length > 1) {
                duplicateIdNames.push(`#${id} (×${els.length})`);
                els.forEach(el => duplicates.push(el));
            }
        });

        if (duplicates.length > 0) {
            context.setAuditDescription(
                this.key,
                `Duplicate: ${duplicateIdNames.join(', ')}`
            );
        }

        applyHighlight(duplicates, this.key, context);
    },
};
