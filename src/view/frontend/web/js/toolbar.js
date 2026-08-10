/**
 * MageForge Toolbar - Standalone audit toolbar.
 */

import { matchesShortcut } from "./shortcut-parser.js";
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

    /** @type {'dark'|'auto'|'light'} Active colour scheme */
    currentTheme: "dark",

    /** @type {Function|null} Global keydown handler for keyboard shortcuts */
    _keyboardShortcutHandler: null,

    /** @type {boolean} Whether keyboard shortcuts are enabled */
    keyboardShortcutsEnabled: true,

    /** @type {string} Configured keyboard shortcut for toggling all audits */
    shortcut: "Ctrl+Shift+A",

    /** @type {Map<string, 'success'|'warning'|'error'>} In-memory audit badge status (avoids DOM reads in score calc) */
    _auditStatus: new Map(),

    // ====================================================================
    // Lifecycle
    // ====================================================================

    init() {
      this.createToolbar();
      this.currentTheme = this.$el?.getAttribute("data-theme") || "dark";
      this.setTheme(this.currentTheme);
      this.keyboardShortcutsEnabled =
        this.$el?.getAttribute("data-keyboard-shortcuts-enabled") !== "0";
      this.shortcut = this.$el?.getAttribute("data-shortcut") || "Ctrl+Shift+A";

      // Global keyboard shortcut for toggling all audits
      this._keyboardShortcutHandler = (e) => {
        if (!this.keyboardShortcutsEnabled) return;
        if (matchesShortcut(e, this.shortcut)) {
          e.preventDefault();
          this.toggleAllAudits();
        }
      };
      document.addEventListener("keydown", this._keyboardShortcutHandler);
    },

    destroy() {
      if (this._keyboardShortcutHandler) {
        document.removeEventListener("keydown", this._keyboardShortcutHandler);
        this._keyboardShortcutHandler = null;
      }
      this.deactivateAllAudits();
      this.activeAudits.clear();
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
