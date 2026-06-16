/**
 * MageForge Toolbar - Standalone audit toolbar.
 */

import { uiMethods } from "./toolbar/ui.js";
import { auditMethods } from "./toolbar/audits.js";

function _registerMageforgeToolbar() {
  Alpine.data("mageforgeToolbar", () => ({
    // ====================================================================
    // State
    // ====================================================================
    menuOpen: false,

    /** @type {Set<string>} Keys of currently active audits */
    activeAudits: new Set(),

    /** @type {string} Key of the currently active tab */
    activeTab: "home",

    /** @type {HTMLDivElement|null} */
    container: null,

    /** @type {HTMLDivElement|null} */
    burgerButton: null,

    /** @type {HTMLDivElement|null} */
    menu: null,

    /** @type {HTMLDivElement|null} */
    runAllButton: null,

    /** @type {HTMLDivElement|null} */
    resetButton: null,

    // ====================================================================
    // Lifecycle
    // ====================================================================

    init() {
      this.createToolbar();
    },

    destroy() {
      this.deactivateAllAudits();
      this.destroyToolbar();
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
if (typeof Alpine !== "undefined") {
  _registerMageforgeToolbar();
  document
    .querySelectorAll('[x-data="mageforgeToolbar"]')
    .forEach(function (el) {
      if (typeof Alpine.initTree === "function") {
        Alpine.initTree(el);
      }
    });
} else {
  document.addEventListener("alpine:init", _registerMageforgeToolbar);
}
