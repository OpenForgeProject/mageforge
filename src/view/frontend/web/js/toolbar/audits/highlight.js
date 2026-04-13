/**
 * MageForge Toolbar – Shared highlight helpers
 *
 * Audits that mark elements by adding a CSS class use these two helpers
 * instead of duplicating the same logic. The CSS class is derived from the
 * audit key: `mageforge-audit-<key>`.
 */

/**
 * Removes the highlight class from all previously marked elements.
 *
 * @param {string} key - Audit key (e.g. 'images-without-alt')
 */
export function clearHighlight(key) {
    const cls = `mageforge-audit-${key}`;
    document.querySelectorAll(`.${cls}`).forEach(el => el.classList.remove(cls));
}

/**
 * Highlights a set of elements by adding the audit CSS class, scrolls to the
 * first result, and updates the counter badge on the toolbar menu item.
 *
 * @param {Element[]}  elements - Elements to mark
 * @param {string}     key      - Audit key (e.g. 'images-without-alt')
 * @param {object}     context  - Alpine toolbar component instance
 */
export function applyHighlight(elements, key, context) {
    if (elements.length === 0) {
        context.setAuditCounterBadge(key, '0', 'success');
        return;
    }
    const cls = `mageforge-audit-${key}`;
    elements.forEach(el => el.classList.add(cls));
    elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    context.setAuditCounterBadge(key, `${elements.length}`, 'error');
}
