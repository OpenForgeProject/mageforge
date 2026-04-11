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

        /** @type {HTMLDivElement|null} */
        container: null,

        /** @type {HTMLButtonElement|null} */
        burgerButton: null,

        /** @type {HTMLDivElement|null} */
        menu: null,

        /** @type {HTMLButtonElement|null} */
        inspectorButton: null,

        // ====================================================================
        // Lifecycle
        // ====================================================================

        init() {
            this.createToolbar();

            window.addEventListener('mageforge:toolbar:inspector-state', (e) => {
                this.setInspectorActive(e.detail.active);
            });
        },

        destroy() {
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

if (typeof Alpine !== 'undefined') {
    _registerMageforgeToolbar();
} else {
    document.addEventListener('alpine:init', _registerMageforgeToolbar);
}
