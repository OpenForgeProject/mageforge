/**
 * MageForge Toolbar Audit – Images without ALT
 */

const HIGHLIGHT_CLASS = 'mageforge-audit-images-without-alt';

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
            document.querySelectorAll(`.${HIGHLIGHT_CLASS}`).forEach(el => el.classList.remove(HIGHLIGHT_CLASS));
            return;
        }

        const images = Array.from(document.querySelectorAll('img')).filter(img => {
            if (!img.offsetParent && getComputedStyle(img).position !== 'fixed') return false;
            const style = getComputedStyle(img);
            if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') return false;
            return !img.hasAttribute('alt') || img.getAttribute('alt').trim() === '';
        });

        if (images.length === 0) {
            context.setAuditCounterBadge('images-without-alt', '0', 'success');
            return;
        }

        images.forEach(img => img.classList.add(HIGHLIGHT_CLASS));
        images[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        context.setAuditCounterBadge('images-without-alt', `${images.length}`, 'error');
    },
};
