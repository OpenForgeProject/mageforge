/**
 * MageForge Toolbar Audit – Images without ALT
 */

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'images-without-alt',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12"/><path d="M4 16l16 0"/><path d="M4 12l3 -3c.928 -.893 2.072 -.893 3 0l4 4"/><path d="M13 12l2 -2c.928 -.893 2.072 -.893 3 0l2 2"/><path d="M14 7l.01 0"/></svg>',
    label: 'Images without ALT',
    description: 'Highlighting images without alt attributes',

    /**
     * @param {object} context - Alpine toolbar component instance
     */
    run(context) {
        const existing = document.querySelectorAll('.mageforge-toolbar-audit-highlight');
        if (existing.length > 0) {
            existing.forEach(el => el.classList.remove('mageforge-toolbar-audit-highlight'));
            return;
        }

        const images = Array.from(document.querySelectorAll('img')).filter(img =>
            !img.hasAttribute('alt') || img.getAttribute('alt').trim() === ''
        );

        if (images.length === 0) {
            context.setAuditCounterBadge('images-without-alt', '0', 'success');
            return;
        }

        images.forEach(img => img.classList.add('mageforge-toolbar-audit-highlight'));
        images[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        context.setAuditCounterBadge('images-without-alt', `${images.length}`, 'error');
    },
};
