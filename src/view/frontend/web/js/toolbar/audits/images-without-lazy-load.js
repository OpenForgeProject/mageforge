/**
 * MageForge Toolbar Audit – Images without lazy loading
 *
 * Images below the fold that lack loading="lazy" are loaded eagerly,
 * wasting bandwidth and slowing initial page load.
 */

const HIGHLIGHT_CLASS = 'mageforge-audit-images-without-lazy-load';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'images-without-lazy-load',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 12l6 -3l-8 -4l-8 4l6 3"></path><path d="M10 12l-6 3l8 4l8 -4l-6 -3l-2 1l-2 -1" fill="currentColor"></path></svg>',
    label: 'Images without Lazy Load',
    description: 'Highlight off-screen images missing loading="lazy"',

    /**
     * @param {object} context - Alpine toolbar component instance
     */
    run(context) {
        const existing = document.querySelectorAll(`.${HIGHLIGHT_CLASS}`);
        if (existing.length > 0) {
            existing.forEach(el => el.classList.remove(HIGHLIGHT_CLASS));
            return;
        }

        const viewportBottom = window.innerHeight;

        const images = Array.from(document.querySelectorAll('img')).filter(img => {
            if (img.getAttribute('loading') === 'lazy') return false;
            const rect = img.getBoundingClientRect();
            return rect.top > viewportBottom;
        });

        if (images.length === 0) {
            context.setAuditCounterBadge('images-without-lazy-load', '0', 'success');
            return;
        }

        images.forEach(img => img.classList.add(HIGHLIGHT_CLASS));
        images[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        context.setAuditCounterBadge('images-without-lazy-load', `${images.length}`, 'error');
    },
};
