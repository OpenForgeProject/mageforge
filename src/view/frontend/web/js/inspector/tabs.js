/**
 * MageForge Inspector - Tab System & Structure Tab Rendering
 */

export const tabsMethods = {
    /**
     * Create tab system for inspector
     */
    createTabSystem(data, element) {
        // Tab container
        const tabContainer = document.createElement('div');
        tabContainer.className = 'mageforge-tabs-container';

        // Tab header
        const tabHeader = document.createElement('div');
        tabHeader.className = 'mageforge-tabs-header';

        // Define tabs
        const tabs = [
            { id: 'structure', label: 'Structure', icon: '🏰' },
            { id: 'accessibility', label: 'Accessibility', icon: '♿' },
            { id: 'performance', label: 'Cache', icon: '💾' },
            { id: 'core-web-vitals', label: 'Core Web Vitals', icon: '🌐' }
        ];

        // Tab content container
        const tabContentContainer = document.createElement('div');

        // Create tab buttons
        tabs.forEach(tab => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mageforge-tab-button' + (this.activeTab === tab.id ? ' active' : '');

            // Label text
            const textSpan = document.createElement('span');
            textSpan.textContent = tab.label;
            button.appendChild(textSpan);

            // Show "New" badge for Performance and Core Web Vitals if seen < 5 times
            if (['performance', 'core-web-vitals'].includes(tab.id) &&
                (this.featureViews[tab.id] || 0) < this.MAX_NEW_BADGE_VIEWS) {
                const badge = document.createElement('span');
                badge.className = 'mageforge-badge-new';
                badge.textContent = 'NEW';
                button.appendChild(badge);
            }

            button.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.switchTab(tab.id, data, element);
            };

            tabHeader.appendChild(button);
        });

        tabContainer.appendChild(tabHeader);
        tabContainer.appendChild(tabContentContainer);
        this.infoBadge.appendChild(tabContainer);

        // Render initial tab content
        this.renderTabContent(this.activeTab, data, tabContentContainer, element);
    },

    /**
     * Switch to different tab
     */
    switchTab(tabId, data, element) {
        this.activeTab = tabId;

        // Find the element to rebuild
        const targetElement = element || this.hoveredElement || this.selectedElement;
        if (targetElement) {
            this.buildBadgeContent(targetElement);
        }
    },

    /**
     * Render content for specific tab
     */
    renderTabContent(tabId, data, container, element) {
        container.innerHTML = '';

        if (tabId === 'structure') {
            this.renderStructureTab(data, container, element);
        } else if (tabId === 'accessibility') {
            this.renderAccessibilityTab(container, element);
        } else if (tabId === 'performance') {
            this.renderPerformanceTab(container, element);
        } else if (tabId === 'core-web-vitals') {
            this.renderBrowserMetricsTab(container, element);
        }
    },

    /**
     * Render Structure tab content
     */
    renderStructureTab(data, container, element) {
        const hasTemplateData = data.template || data.blockClass || data.module;

        if (!hasTemplateData) {
            this.renderStructureWithParentData(container, element);
            return;
        }

        this.renderStructureSections(data, container);
    },

    /**
     * Render structure tab when element has no direct template data
     */
    renderStructureWithParentData(container, element) {
        // Try to find parent element with block data
        let parent = element.parentElement;
        let parentBlock = null;
        let maxDepth = 10;

        while (parent && maxDepth > 0) {
            parentBlock = this.findBlockForElement(parent);
            if (parentBlock) {
                this.renderInheritedStructure(container, element, parentBlock);
                return;
            }
            parent = parent.parentElement;
            maxDepth--;
        }

        this.renderNoTemplateData(container, element);
    },

    /**
     * Render inherited structure from parent element
     */
    renderInheritedStructure(container, element, parentBlock) {
        const parentData = parentBlock.data || {
            template: '',
            block: '',
            module: '',
            viewModel: '',
            parent: '',
            alias: '',
            override: '0'
        };

        // Convert to expected format
        parentData.blockClass = parentData.block;
        parentData.parentBlock = parentData.parent;
        parentData.blockAlias = parentData.alias;
        parentData.isOverride = parentData.override === '1';

        // Inheritance note
        const inheritanceNote = document.createElement('div');
        inheritanceNote.className = 'mageforge-inheritance-note';
        inheritanceNote.innerHTML = `
            <span style="font-size: 14px;">⬆️</span>
            <div>
                <div style="font-weight: 600; margin-bottom: 2px;">Inherited from Parent</div>
                <div style="color: #fcd34d; font-size: 10px;">This &lt;${element.tagName.toLowerCase()}&gt; element is inside a Magento block</div>
            </div>
        `;
        container.appendChild(inheritanceNote);

        this.renderStructureSections(parentData, container);
    },

    /**
     * Render "No Template Data" message
     */
    renderNoTemplateData(container, element) {
        const noDataDiv = document.createElement('div');
        noDataDiv.className = 'mageforge-no-data';
        noDataDiv.innerHTML = `
            <div class="mageforge-no-data-icon">📋</div>
            <div class="mageforge-no-data-title">No Template Data</div>
            <div class="mageforge-no-data-desc">This element is not inside a Magento template block</div>
            <div class="mageforge-no-data-meta">Element: <code class="mageforge-code-tag">&lt;${element.tagName.toLowerCase()}&gt;</code></div>
        `;
        container.appendChild(noDataDiv);
    },

    /**
     * Render structure sections (template, block, module, etc.)
     */
    renderStructureSections(data, container) {
        // Template section
        container.appendChild(this.createInfoSection('Template', data.template, '#60a5fa'));

        // Block section
        container.appendChild(this.createInfoSection('Block', data.blockClass, '#a78bfa'));

        // Optional sections
        if (data.blockAlias) {
            container.appendChild(this.createInfoSection('Block Name', data.blockAlias, '#34d399'));
        }
        if (data.parentBlock) {
            container.appendChild(this.createInfoSection('Parent Block', data.parentBlock, '#fb923c'));
        }
        if (data.viewModel) {
            container.appendChild(this.createInfoSection('ViewModel', data.viewModel, '#22d3ee'));
        }

        // Module section
        container.appendChild(this.createInfoSection('Module', data.module, '#fbbf24'));
    },
};
