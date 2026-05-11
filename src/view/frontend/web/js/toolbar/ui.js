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

        if (this.$el && this.$el.hasAttribute('data-position')) {
            this.container.setAttribute('data-position', this.$el.getAttribute('data-position'));
        }

        if (this.$el && this.$el.getAttribute('data-show-labels') === '0') {
            this.container.classList.add('mageforge-toolbar--no-labels');
        }

        // Menu popup (before buttons so it sits correctly in stacking context)
        this.menu = document.createElement('div');
        this.menu.className = 'mageforge-toolbar-menu';

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

        // Footer – Health Score Gauge + Run All Tests
        const menuFooter = document.createElement('div');
        menuFooter.className = 'mageforge-toolbar-menu-footer';

        const showHealthScore = this.$el?.getAttribute('data-show-health-score') !== '0';

        if (showHealthScore) {
            const ARC_LENGTH = 157.08;
            const healthWrapper = document.createElement('div');
            healthWrapper.className = 'mageforge-toolbar-health-wrapper';

            const gaugeSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            gaugeSvg.setAttribute('viewBox', '0 0 120 70');
            gaugeSvg.setAttribute('class', 'mageforge-toolbar-health-gauge');
            gaugeSvg.setAttribute('aria-hidden', 'true');
            gaugeSvg.innerHTML = `
                <defs>
                    <linearGradient id="mf-gauge-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ef4444"></stop>
                        <stop offset="50%" stop-color="#edb04d"></stop>
                        <stop offset="100%" stop-color="#10b981"></stop>
                    </linearGradient>
                </defs>
                <path d="M 10 65 A 50 50 0 0 1 110 65"
                      fill="none" stroke="rgba(148,163,184,0.15)" stroke-width="10" stroke-linecap="round"></path>
                <path d="M 10 65 A 50 50 0 0 1 110 65"
                      fill="none" stroke="url(#mf-gauge-grad)" stroke-width="10" stroke-linecap="round"
                      stroke-dasharray="0 ${ARC_LENGTH}" class="mageforge-health-gauge-progress"></path>
                <line class="mageforge-health-gauge-needle" x1="60" y1="65" x2="60" y2="20"
                      stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" opacity="0"></line>
                <circle cx="60" cy="65" r="4" fill="rgba(255,255,255,0.4)"></circle>
            `;
            healthWrapper.appendChild(gaugeSvg);

            const scoreTextWrapper = document.createElement('div');
            scoreTextWrapper.className = 'mageforge-toolbar-health-score-text';
            scoreTextWrapper.innerHTML = `
                <div class="mageforge-toolbar-health-score-value">
                    <span class="mageforge-toolbar-health-score-number">--</span><span class="mageforge-toolbar-health-score-max">/100</span>
                </div>
                <div class="mageforge-toolbar-health-score-label">Overall Health Score</div>
            `;
            healthWrapper.appendChild(scoreTextWrapper);
            menuFooter.appendChild(healthWrapper);

            // Run All Tests + Reset button row (with score)
            const buttonRow = document.createElement('div');
            buttonRow.className = 'mageforge-toolbar-menu-button-row';

            this.runAllButton = document.createElement('button');
            this.runAllButton.type = 'button';
            this.runAllButton.className = 'mageforge-toolbar-menu-run-all';
            this.runAllButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"></path></svg>
                RUN ALL TESTS
            `;
            this.runAllButton.onclick = (e) => {
                e.stopPropagation();
                this.runAllAuditsForScore();
            };
            buttonRow.appendChild(this.runAllButton);

            this.resetButton = document.createElement('button');
            this.resetButton.type = 'button';
            this.resetButton.className = 'mageforge-toolbar-menu-reset';
            this.resetButton.title = 'Reset score and deactivate all audits';
            this.resetButton.setAttribute('aria-label', 'Reset score and deactivate all audits');
            this.resetButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>`;
            this.resetButton.onclick = (e) => {
                e.stopPropagation();
                this.resetScore();
            };
            buttonRow.appendChild(this.resetButton);

            menuFooter.appendChild(buttonRow);
        } else {
            // No health score – button row with Run All + Reset
            const buttonRow = document.createElement('div');
            buttonRow.className = 'mageforge-toolbar-menu-button-row';

            this.runAllButton = document.createElement('button');
            this.runAllButton.type = 'button';
            this.runAllButton.className = 'mageforge-toolbar-menu-run-all';
            this.runAllButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"></path></svg>
                RUN ALL TESTS
            `;
            this.runAllButton.onclick = (e) => {
                e.stopPropagation();
                this.runAllAuditsForScore();
            };
            buttonRow.appendChild(this.runAllButton);

            this.resetButton = document.createElement('button');
            this.resetButton.type = 'button';
            this.resetButton.className = 'mageforge-toolbar-menu-reset';
            this.resetButton.title = 'Deactivate all audits';
            this.resetButton.setAttribute('aria-label', 'Deactivate all audits');
            this.resetButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>`;
            this.resetButton.onclick = (e) => {
                e.stopPropagation();
                this.resetScore();
            };
            buttonRow.appendChild(this.resetButton);

            menuFooter.appendChild(buttonRow);
        }

        const credit = document.createElement('div');
        credit.className = 'mageforge-toolbar-menu-credit';
        credit.innerHTML = `Built with <span class="mageforge-toolbar-menu-credit-heart">❤</span> by <a href="https://github.com/OpenForgeProject/mageforge" target="_blank" rel="noopener noreferrer" class="mageforge-toolbar-menu-credit-link">MageForge</a>`;
        menuFooter.appendChild(credit);
        this.menu.appendChild(menuFooter);

        // Burger button (left)
        this.burgerButton = document.createElement('button');
        this.burgerButton.className = 'mageforge-toolbar-burger';
        this.burgerButton.type = 'button';
        this.burgerButton.title = 'Audit tools';
        this.burgerButton.setAttribute('aria-label', 'MageForge Toolbar');
        this.burgerButton.setAttribute('aria-expanded', 'false');
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
        header.setAttribute('aria-expanded', String(!this.collapsedGroups.has(key)));
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

        group.classList.toggle('mageforge-toolbar-menu-group--collapsed', this.collapsedGroups.has(key));

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
        item.setAttribute('aria-pressed', 'false');

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
        descEl.addEventListener('click', e => {
            if (descEl.classList.contains('mageforge-active')) e.stopPropagation();
        });
        descEl.addEventListener('mousedown', e => {
            if (descEl.classList.contains('mageforge-active')) e.stopPropagation();
        });
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
        item.setAttribute('aria-pressed', String(active));
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
     * No-op – retained for compatibility; the Run All Tests button has no dynamic label.
     */
    updateToggleAllButton() {},

    /**
     * Reset the health score gauge and deactivate all audits.
     */
    resetScore() {
        this.deactivateAllAudits();
        if (!this.menu) return;
        const ARC_LENGTH = 157.08;
        const progress = this.menu.querySelector('.mageforge-health-gauge-progress');
        const needle   = this.menu.querySelector('.mageforge-health-gauge-needle');
        const numberEl = this.menu.querySelector('.mageforge-toolbar-health-score-number');
        if (numberEl) numberEl.textContent = '--';
        if (progress) progress.setAttribute('stroke-dasharray', `0 ${ARC_LENGTH}`);
        if (needle)   needle.setAttribute('opacity', '0');
    },

    /**
     * Update the health score gauge and numeric display (0–100).
     *
     * @param {number} score
     */
    updateHealthScore(score) {
        if (!this.menu) return;
        const ARC_LENGTH = 157.08;
        const progress = this.menu.querySelector('.mageforge-health-gauge-progress');
        const needle   = this.menu.querySelector('.mageforge-health-gauge-needle');
        const numberEl = this.menu.querySelector('.mageforge-toolbar-health-score-number');

        if (numberEl) numberEl.textContent = score;

        if (progress) {
            const dash = ((score / 100) * ARC_LENGTH).toFixed(2);
            progress.setAttribute('stroke-dasharray', `${dash} ${ARC_LENGTH}`);
        }

        if (needle) {
            const rad = (1 - score / 100) * Math.PI;
            needle.setAttribute('x2', (60 + 45 * Math.cos(rad)).toFixed(1));
            needle.setAttribute('y2', (65 - 45 * Math.sin(rad)).toFixed(1));
            needle.setAttribute('opacity', '1');
        }
    },

    toggleMenu() {
        this.menuOpen ? this.closeMenu() : this.openMenu();
    },

    openMenu() {
        this.menuOpen = true;
        this.menu.classList.add('mageforge-menu-open');
        this.burgerButton.classList.add('mageforge-active');
        this.burgerButton.setAttribute('aria-expanded', 'true');
    },

    closeMenu() {
        this.menuOpen = false;
        this.menu.classList.remove('mageforge-menu-open');
        this.burgerButton.classList.remove('mageforge-active');
        this.burgerButton.setAttribute('aria-expanded', 'false');
    },

    destroyToolbar() {
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler);
            this._outsideClickHandler = null;
        }
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        this.container = null;
        this.menu = null;
        this.burgerButton = null;
        this.runAllButton = null;
        this.resetButton = null;
        this.menuOpen = false;
    },
};
