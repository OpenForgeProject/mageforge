/**
 * MageForge Toolbar - DOM construction and menu controls
 */

export const uiMethods = {
    createToolbar() {
        this.container = document.createElement('div');
        this.container.className = 'mageforge-toolbar';

        if (this.$el && this.$el.hasAttribute('data-theme')) {
            this.container.setAttribute('data-theme', this.$el.getAttribute('data-theme'));
        }

        // Menu popup (before buttons so it sits correctly in stacking context)
        this.menu = document.createElement('div');
        this.menu.className = 'mageforge-toolbar-menu';
        this.menu.style.display = 'none';

        const menuTitle = document.createElement('div');
        menuTitle.className = 'mageforge-toolbar-menu-title';
        menuTitle.innerHTML = `
            <span class="mageforge-toolbar-menu-title-text">MageForge Toolbar</span>
            <button type="button" class="mageforge-toolbar-menu-close" title="Close & deactivate all">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"></path></svg>
            </button>
        `;
        menuTitle.querySelector('.mageforge-toolbar-menu-close').onclick = (e) => {
            e.stopPropagation();
            this.deactivateAllAudits();
            this.closeMenu();
        };
        this.menu.appendChild(menuTitle);

        this.getAudits().forEach(audit => {
            this.menu.appendChild(this.createMenuItem(
                audit.key,
                audit.icon,
                audit.label,
                audit.description,
                () => this.runAudit(audit.key)
            ));
        });

        // Footer – Toggle All
        const menuFooter = document.createElement('div');
        menuFooter.className = 'mageforge-toolbar-menu-footer';
        this.toggleAllButton = document.createElement('button');
        this.toggleAllButton.type = 'button';
        this.toggleAllButton.className = 'mageforge-toolbar-menu-toggle-all';
        this.toggleAllButton.textContent = 'Activate All';
        this.toggleAllButton.onclick = (e) => {
            e.stopPropagation();
            this.toggleAllAudits();
        };
        menuFooter.appendChild(this.toggleAllButton);
        const credit = document.createElement('div');
        credit.className = 'mageforge-toolbar-menu-credit';
        credit.innerHTML = `Built with ♥ by <a href="https://github.com/OpenForgeProject/mageforge" target="_blank" rel="noopener noreferrer" class="mageforge-toolbar-menu-credit-link">MageForge</a>`;
        menuFooter.appendChild(credit);
        this.menu.appendChild(menuFooter);

        // Burger button (left)
        this.burgerButton = document.createElement('button');
        this.burgerButton.className = 'mageforge-toolbar-burger';
        this.burgerButton.type = 'button';
        this.burgerButton.title = 'Audit tools';
        this.burgerButton.innerHTML = `
            <span class="mageforge-toolbar-burger-icon">
                <span class="mageforge-toolbar-burger-bar"></span>
                <span class="mageforge-toolbar-burger-bar"></span>
                <span class="mageforge-toolbar-burger-bar"></span>
            </span>
            <span class="mageforge-toolbar-burger-label">Toolbar</span>
        `;
        this.burgerButton.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleMenu();
        };

        // Inspector toggle button (right) – delegates to inspector via custom event
        this.inspectorButton = document.createElement('button');
        this.inspectorButton.className = 'mageforge-inspector-float-button';
        this.inspectorButton.type = 'button';
        this.inspectorButton.title = 'Activate Inspector (Ctrl+Shift+I)';
        this.inspectorButton.innerHTML = `
            <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor" height="20" width="20">
                <g stroke-width="0"></g>
                <g stroke-linecap="round" stroke-linejoin="round"></g>
                <g>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1 3l1-1h12l1 1v6h-1V3H2v8h5v1H2l-1-1V3zm14.707 9.707L9 6v9.414l2.707-2.707h4zM10 13V8.414l3.293 3.293h-2L10 13z"></path>
                </g>
            </svg>
            <span>Inspector</span>
        `;
        this.inspectorButton.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            window.dispatchEvent(new CustomEvent('mageforge:toolbar:toggle-inspector'));
        };

        this.container.appendChild(this.menu);
        this.container.appendChild(this.burgerButton);

        // Close menu when clicking outside the toolbar
        this._outsideClickHandler = (e) => {
            if (this.menuOpen && !this.container.contains(e.target)) {
                this.closeMenu();
            }
        };
        document.addEventListener('click', this._outsideClickHandler);

        document.body.appendChild(this.container);
    },

    /**
     * Create a single audit menu item button
     *
     * @param {string} key
     * @param {string} icon
     * @param {string} label
     * @param {string} description
     * @param {Function} callback
     * @return {HTMLButtonElement}
     */
    createMenuItem(key, icon, label, description, callback) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'mageforge-toolbar-menu-item';
        item.dataset.auditKey = key;

        const iconEl = document.createElement('span');
        iconEl.className = 'mageforge-toolbar-menu-icon';
        iconEl.innerHTML = icon;

        const labelEl = document.createElement('span');
        labelEl.className = 'mageforge-toolbar-menu-label';
        labelEl.textContent = label;

        const statusEl = document.createElement('span');
        statusEl.className = 'mageforge-toolbar-menu-status';

        const labelRowEl = document.createElement('span');
        labelRowEl.className = 'mageforge-toolbar-menu-label-row';
        labelRowEl.appendChild(labelEl);
        labelRowEl.appendChild(statusEl);

        const descEl = document.createElement('span');
        descEl.className = 'mageforge-toolbar-menu-desc';
        descEl.textContent = description;

        const textEl = document.createElement('span');
        textEl.className = 'mageforge-toolbar-menu-text';
        textEl.appendChild(labelRowEl);
        textEl.appendChild(descEl);

        const toggleEl = document.createElement('span');
        toggleEl.className = 'mageforge-toolbar-menu-toggle';

        item.appendChild(iconEl);
        item.appendChild(textEl);
        item.appendChild(toggleEl);

        item.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            callback();
        };
        return item;
    },

    /**
     * Update the visual active state of an audit menu item.
     *
     * @param {string} key
     * @param {boolean} active
     */
    setAuditActive(key, active) {
        if (!this.menu) return;
        const item = this.menu.querySelector(`[data-audit-key="${key}"]`);
        if (!item) return;
        item.classList.toggle('mageforge-active', active);
        if (!active) {
            item.classList.remove('mageforge-active--error');
            const status = item.querySelector('.mageforge-toolbar-menu-status');
            if (status) {
                status.textContent = '';
                status.className = 'mageforge-toolbar-menu-status';
            }
        }
        this.updateToggleAllButton();
    },

    /**
     * Sync the Toggle All button label to current active state.
     */
    updateToggleAllButton() {
        if (!this.toggleAllButton) return;
        const allActive = this.activeAudits.size === this.getAudits().length;
        this.toggleAllButton.textContent = allActive ? 'Deactivate All' : 'Activate All';
    },

    toggleMenu() {
        this.menuOpen ? this.closeMenu() : this.openMenu();
    },

    openMenu() {
        this.menuOpen = true;
        this.menu.style.display = 'block';
        this.burgerButton.classList.add('mageforge-active');
    },

    closeMenu() {
        this.menuOpen = false;
        this.menu.style.display = 'none';
        this.burgerButton.classList.remove('mageforge-active');
    },
};
