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

        if (this.$el && this.$el.getAttribute('data-show-labels') === '0') {
            this.container.classList.add('mageforge-toolbar--no-labels');
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

        // Group audits by their group key; ungrouped items fall through
        const grouped = {};
        const ungrouped = [];
        this.getAudits().forEach(audit => {
            if (audit.group) {
                (grouped[audit.group] = grouped[audit.group] || []).push(audit);
            } else {
                ungrouped.push(audit);
            }
        });

        // Render defined groups in order
        this.getAuditGroups().forEach(group => {
            const items = grouped[group.key];
            if (!items?.length) return;
            this.menu.appendChild(this.createMenuGroup(group.key, group.label, items));
        });

        // Render any ungrouped audits below
        ungrouped.forEach(audit => {
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
            <span class="mageforge-toolbar-burger-label">MageForge Toolbar</span>
        `;
        this.burgerButton.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleMenu();
        };

        this.container.appendChild(this.menu);
        this.container.appendChild(this.burgerButton);
        // Note: inspector button is appended by the mageforgeInspector Alpine component via _appendInspectorButton()

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
     * Create a collapsible group section containing audit menu items.
     *
     * @param {string}   key    - Group key
     * @param {string}   label  - Display label
     * @param {object[]} audits - Audit definitions belonging to this group
     * @return {HTMLDivElement}
     */
    createMenuGroup(key, label, audits) {
        const group = document.createElement('div');
        group.className = 'mageforge-toolbar-menu-group';
        group.dataset.groupKey = key;

        const header = document.createElement('button');
        header.type = 'button';
        header.className = 'mageforge-toolbar-menu-group-header';
        header.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleGroup(key);
        };

        const headerLabel = document.createElement('span');
        headerLabel.className = 'mageforge-toolbar-menu-group-label';
        headerLabel.textContent = label;

        const chevron = document.createElement('span');
        chevron.className = 'mageforge-toolbar-menu-group-chevron';
        chevron.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>`;

        header.appendChild(headerLabel);
        header.appendChild(chevron);
        group.appendChild(header);

        const items = document.createElement('div');
        items.className = 'mageforge-toolbar-menu-group-items';

        audits.forEach(audit => {
            items.appendChild(this.createMenuItem(
                audit.key,
                audit.icon,
                audit.label,
                audit.description,
                () => this.runAudit(audit.key)
            ));
        });

        group.appendChild(items);
        return group;
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

    destroyToolbar() {
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler);
            this._outsideClickHandler = null;
        }
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
    },
};
