/**
 * MageForge Toolbar - Standalone audit toolbar.
 */

import { uiMethods } from './toolbar/ui.js';
import { auditMethods } from './toolbar/audits.js';

function _registerMageforgeToolbar() {
    Alpine.data('mageforgeToolbar', () => ({
        // ====================================================================
        // State
        // ====================================================================
        menuOpen: false,

        /** @type {Set<string>} Keys of currently active audits */
        activeAudits: new Set(),

        /** @type {Set<string>} Keys of currently collapsed groups */
        collapsedGroups: new Set(),

        /** @type {HTMLDivElement|null} */
        container: null,

        /** @type {HTMLButtonElement|null} */
        burgerButton: null,

        /** @type {HTMLDivElement|null} */
        menu: null,

        /** @type {HTMLButtonElement|null} */
        toggleAllButton: null,

        // ====================================================================
        // Lifecycle
        // ====================================================================

        init() {
            try {
                const saved = localStorage.getItem('mageforge-toolbar-collapsed-groups');
                if (saved) {
                    try { JSON.parse(saved).forEach(key => this.collapsedGroups.add(key)); } catch (_) {}
                }
            } catch (_) {}
            this.createToolbar();
        },

        destroy() {
            if (this._outsideClickHandler) {
                document.removeEventListener('click', this._outsideClickHandler);
                this._outsideClickHandler = null;
            }
            if (this.container) {
                this.container.remove();
                this.container = null;
            }
        },

        // ====================================================================
        // Mixins
        // ====================================================================

        ...uiMethods,
        ...auditMethods,
    }));
}

// re-initialise any [x-data="mageforgeToolbar"] elements that Alpine skipped
// because the component was not yet registered at that point.
// Otherwise, register on alpine:init which fires before Alpine processes the DOM.
if (typeof Alpine !== 'undefined') {
    _registerMageforgeToolbar();
    document.querySelectorAll('[x-data="mageforgeToolbar"]').forEach(function (el) {
        if (typeof Alpine.initTree === 'function') {
            Alpine.initTree(el);
        }
    });
} else {
    document.addEventListener('alpine:init', _registerMageforgeToolbar);
}
