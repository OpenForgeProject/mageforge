/**
 * MageForge Toolbar Audit – Empty Links & Buttons
 *
 * Links and buttons without an accessible name are unusable for screen
 * reader and keyboard users (WCAG 2.1 SC 4.1.2, 2.4.6).
 */

import { applyHighlight, clearHighlight } from './highlight.js';

/** @type {import('./index.js').AuditDefinition} */
export default {
    key: 'empty-interactive',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M17 12h-14"></path><path d="M6 9l-3 3l3 3"></path><path d="M20 6v.01"></path><path d="M20 12v.01"></path><path d="M20 18v.01"></path></svg>',
    label: 'Empty Links & Buttons',
    description: 'Highlight links and buttons missing an accessible name',

    /**
     * @param {object} context - Alpine toolbar component instance
     * @param {boolean} active  - true = activate, false = deactivate
     */
    run(context, active) {
        if (!active) {
            clearHighlight(this.key);
            return;
        }

        const elements = Array.from(document.querySelectorAll('a[href], button')).filter(el => {
            // Visibility check
            if (!el.offsetParent && getComputedStyle(el).position !== 'fixed') return false;
            const style = getComputedStyle(el);
            if (style.visibility === 'hidden' || style.display === 'none' || style.opacity === '0') return false;

            // Accessible name sources
            if (el.getAttribute('aria-label')?.trim()) return false;
            if (el.getAttribute('title')?.trim()) return false;
            if (el.getAttribute('aria-labelledby')?.trim().split(/\s+/).some(id => document.getElementById(id)?.textContent.trim())) return false;

            // Text content (excluding whitespace-only)
            if (el.textContent.trim()) return false;

            // Child <img> with non-empty alt (trimmed)
            if (Array.from(el.querySelectorAll('img[alt]')).some(img => img.getAttribute('alt')?.trim())) return false;

            // Child <svg> with a <title> element
            if (el.querySelector('svg title')?.textContent.trim()) return false;

            return true;
        });

        applyHighlight(elements, this.key, context);
    },
};
