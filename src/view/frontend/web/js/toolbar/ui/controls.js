/**
 * MageForge Toolbar – Menu lifecycle and focus trap
 *
 * toggleMenu / openMenu / closeMenu / destroyToolbar
 * _getFocusableEls / _trapFocus / _releaseFocusTrap / setTheme
 */

export const controls = {
  toggleMenu() {
    this.menuOpen ? this.closeMenu() : this.openMenu();
  },

  openMenu() {
    this.menuOpen = true;
    this.menu.classList.add("mageforge-menu-open");
    this.burgerButton.classList.add("mageforge-active");
    this.burgerButton.setAttribute("aria-expanded", "true");
    this._trapFocus();
  },

  closeMenu() {
    this.menuOpen = false;
    this.menu.classList.remove("mageforge-menu-open");
    this.burgerButton.classList.remove("mageforge-active");
    this.burgerButton.setAttribute("aria-expanded", "false");
    this._releaseFocusTrap();
    this.burgerButton?.focus();
  },

  /**
   * Returns all currently focusable elements within the open menu.
   *
   * @returns {HTMLElement[]}
   */
  _getFocusableEls() {
    return Array.from(
      this.menu.querySelectorAll(
        'button:not([disabled]):not([tabindex="-1"]), [href]:not([tabindex="-1"]), input:not([disabled]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])',
      ),
    ).filter((el) => !el.closest("[hidden]"));
  },

  /**
   * Trap keyboard focus inside the menu and close on Escape.
   */
  _trapFocus() {
    this._focusTrapHandler = (e) => {
      if (e.key === "Escape") {
        e.preventDefault();
        this.closeMenu();
        return;
      }
      if (e.key !== "Tab") return;
      const focusable = this._getFocusableEls();
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    this.menu.addEventListener("keydown", this._focusTrapHandler);
    const focusable = this._getFocusableEls();
    if (focusable.length) focusable[0].focus();
  },

  /**
   * Remove the focus trap listener and clean up.
   */
  _releaseFocusTrap() {
    if (this._focusTrapHandler) {
      this.menu?.removeEventListener("keydown", this._focusTrapHandler);
      this._focusTrapHandler = null;
    }
  },

  destroyToolbar() {
    if (this._outsideClickHandler) {
      document.removeEventListener("click", this._outsideClickHandler);
      this._outsideClickHandler = null;
    }
    this._releaseFocusTrap();
    if (this.container?.parentNode)
      this.container.parentNode.removeChild(this.container);
    this.container = null;
    this.menu = null;
    this.burgerButton = null;
    this.runAllButton = null;
    this.resetButton = null;
    this._exportBtnRow = null;
    this.menuOpen = false;
  },

  /**
   * Apply a colour theme to the toolbar container.
   *
   * @param {'dark'|'auto'|'light'} theme
   */
  setTheme(theme) {
    this.currentTheme = theme;
    try {
      localStorage.setItem("mageforge-theme", theme);
    } catch (_) {}
    if (this.container) this.container.setAttribute("data-theme", theme);
  },
};
