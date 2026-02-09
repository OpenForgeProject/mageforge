/**
 * MageForge Inspector - Frontend Element Inspector for Magento Development
 *
 * Alpine.js component for inspecting templates, blocks, and modules
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('mageforgeInspector', () => ({
        isOpen: false,
        isPickerActive: false,
        isPinned: false, // Badge is locked after clicking an element
        hoveredElement: null,
        selectedElement: null,
        highlightBox: null,
        infoBadge: null,
        floatingButton: null,
        mouseMoveHandler: null,
        clickHandler: null,
        hoverTimeout: null,
        hoverDelay: 50, // ms delay for accurate position calculation
        lastBadgeUpdate: 0,
        badgeUpdateDelay: 150, // ms delay to prevent flickering
        activeTab: 'structure', // Current active tab in inspector
        panelData: {
            template: '',
            block: '',
            module: '',
        },

        // Dragging & Connector State
        isDragging: false,
        dragStartX: 0,
        dragStartY: 0,
        initialBadgeX: 0,
        initialBadgeY: 0,
        connectorSvg: null,
        dragHandler: null,
        dragEndHandler: null,

        // Performance Thresholds
        PERF_RENDER_TIME_GOOD: 50, // ms
        PERF_RENDER_TIME_WARNING: 200, // ms
        PERF_DOM_COMPLEXITY_LOW: 50, // nodes
        PERF_DOM_COMPLEXITY_HIGH: 200, // nodes
        PERF_DOM_DEPTH_WARNING: 10, // levels

        // Browser Metrics tracking
        webVitals: {
            lcp: null,
            cls: [],
            inp: null,
            fcp: null,
            elementTimings: [] // Element Timing API results
        },
        longTasks: [],
        resourceMetrics: null,
        pageTimings: null,

        // Feature Discovery
        MAX_NEW_BADGE_VIEWS: 5,
        featureViews: {
            'performance': 0,
            'core-web-vitals': 0
        },

        init() {
            // Bind event handlers to preserve context
            this.mouseMoveHandler = (e) => this.handleMouseMove(e);
            this.clickHandler = (e) => this.handleClick(e);

            // Cache for block detection
            this.cachedBlocks = null;
            this.lastBlocksCacheTime = 0;

            this.setupKeyboardShortcuts();
            this.createHighlightBox();
            this.createInfoBadge();
            this.createFloatingButton();
            this.initWebVitalsTracking();
            this.cachePageTimings();
            this.loadFeatureViews();

            // Dispatch init event for Hyvä integration
            this.$dispatch('mageforge:inspector:init');
        },

        loadFeatureViews() {
            try {
                const stored = localStorage.getItem('mageforge_feature_views');
                if (stored) {
                    this.featureViews = { ...this.featureViews, ...JSON.parse(stored) };
                }
            } catch (e) {
                console.warn('MageForge: Failed to load feature views', e);
            }
        },

        incrementFeatureViews() {
            let changed = false;
            ['performance', 'core-web-vitals'].forEach(feature => {
                if (this.featureViews[feature] < this.MAX_NEW_BADGE_VIEWS) {
                    this.featureViews[feature]++;
                    changed = true;
                }
            });

            if (changed) {
                try {
                    localStorage.setItem('mageforge_feature_views', JSON.stringify(this.featureViews));
                } catch (e) {
                    // Ignore storage errors
                }
            }
        },

        /**
         * Parse MageForge comment markers in DOM
         */
        parseCommentMarker(comment) {
            const text = comment.textContent.trim();

            // Check if it's a start marker
            if (text.startsWith('MAGEFORGE_START ')) {
                const jsonStr = text.substring('MAGEFORGE_START '.length);
                try {
                    // Unescape any escaped comment terminators
                    const unescapedJson = jsonStr.replace(/--&gt;/g, '-->');
                    return {
                        type: 'start',
                        data: JSON.parse(unescapedJson)
                    };
                } catch (e) {
                    console.error('Failed to parse MageForge start marker:', e);
                    return null;
                }
            }

            // Check if it's an end marker
            if (text.startsWith('MAGEFORGE_END ')) {
                const id = text.substring('MAGEFORGE_END '.length).trim();
                return {
                    type: 'end',
                    id: id
                };
            }

            return null;
        },

        /**
         * Find all MageForge block regions in DOM
         */
        findAllMageForgeBlocks() {
            const blocks = [];
            const walker = document.createTreeWalker(
                document.body,
                NodeFilter.SHOW_COMMENT,
                null
            );

            const stack = [];
            let comment;

            while ((comment = walker.nextNode())) {
                const parsed = this.parseCommentMarker(comment);

                if (!parsed) continue;

                if (parsed.type === 'start') {
                    stack.push({
                        startComment: comment,
                        data: parsed.data,
                        elements: []
                    });
                } else if (parsed.type === 'end' && stack.length > 0) {
                    const currentBlock = stack[stack.length - 1];
                    if (currentBlock.data.id === parsed.id) {
                        currentBlock.endComment = comment;

                        // Collect all elements between start and end comments
                        currentBlock.elements = this.getElementsBetweenComments(
                            currentBlock.startComment,
                            currentBlock.endComment
                        );

                        blocks.push(currentBlock);
                        stack.pop();
                    }
                }
            }

            return blocks;
        },

        /**
         * Get all elements between two comment nodes
         */
        getElementsBetweenComments(startComment, endComment) {
            const elements = [];
            let node = startComment.nextSibling;

            while (node && node !== endComment) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    elements.push(node);
                    // Also add all descendants
                    elements.push(...node.querySelectorAll('*'));
                }
                node = node.nextSibling;
            }

            return elements;
        },

        /**
         * Find MageForge block data for a given element
         */
        findBlockForElement(element) {
            // Cache blocks for performance
            if (!this.cachedBlocks || Date.now() - this.lastBlocksCacheTime > 1000) {
                this.cachedBlocks = this.findAllMageForgeBlocks();
                this.lastBlocksCacheTime = Date.now();
            }

            let closestBlock = null;
            let closestDepth = -1;

            // Find the deepest (most specific) block containing this element
            for (const block of this.cachedBlocks) {
                if (block.elements.includes(element)) {
                    // Calculate depth (how many ancestors between element and body)
                    let depth = 0;
                    let node = element;
                    while (node && node !== document.body) {
                        depth++;
                        node = node.parentElement;
                    }

                    if (depth > closestDepth) {
                        closestBlock = block;
                        closestDepth = depth;
                    }
                }
            }

            return closestBlock;
        },

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Ctrl+Shift+I or Cmd+Option+I
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'I') {
                    e.preventDefault();
                    this.toggleInspector();
                }

                // ESC to close
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeInspector();
                }
            });
        },

        /**
         * Create highlight overlay box
         */
        createHighlightBox() {
            this.highlightBox = document.createElement('div');
            this.highlightBox.className = 'mageforge-inspector mageforge-inspector-highlight';
            this.highlightBox.style.display = 'none';

            document.body.appendChild(this.highlightBox);
        },

        /**
         * Create info badge overlay
         */
        createInfoBadge() {
            this.infoBadge = document.createElement('div');
            this.infoBadge.className = 'mageforge-inspector mageforge-inspector-info-badge';
            this.infoBadge.style.display = 'none';

            // Create arrow element
            const arrow = document.createElement('div');
            arrow.className = 'mageforge-inspector-arrow';
            this.infoBadge.appendChild(arrow);

            document.body.appendChild(this.infoBadge);
        },

        /**
         * Create floating button for inspector activation
         */
        createFloatingButton() {
            this.floatingButton = document.createElement('button');
            this.floatingButton.className = 'mageforge-inspector mageforge-inspector-float-button';
            this.floatingButton.type = 'button';
            this.floatingButton.title = 'Activate Inspector (Ctrl+Shift+I)';

            // Generate unique ID for SVG gradient to avoid collisions
            const gradientId = 'mageforge-gradient-' + Math.random().toString(36).substr(2, 9);

            // Icon + Text with unique gradient ID
            this.floatingButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="none" viewBox="0 0 64 64" width="16" height="16" style="flex-shrink: 0;">
                    <defs>
                        <linearGradient id="${gradientId}" x1="32" x2="32" y1="36" y2="4" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#ffc837"/>
                            <stop offset="50%" stop-color="#ff3d00"/>
                            <stop offset="100%" stop-color="#7b1fa2"/>
                        </linearGradient>
                    </defs>
                    <path fill="white" d="M56 36H44v-4c0-1.1046-.8954-2-2-2H16c-2.6569 0-5.1046.8954-7 2.4472V36H8c-1.10457 0-2 .8954-2 2v2c0 1.1046.89543 2 2 2h10.6484c1.6969 3.2887 3.5871 6.4797 5.6503 9.5605L22 56H12c-1.1046 0-2 .8954-2 2v2h44v-2c0-1.1046-.8954-2-2-2H42l-2.2987-4.4395c2.0632-3.0808 3.9534-6.2718 5.6503-9.5605H56c1.1046 0 2-.8954 2-2v-2c0-1.1046-.8954-2-2-2" opacity="0.9"/>
                    <path fill="url(#${gradientId})" d="M32 4S22 18 22 27c0 4.9706 4.4772 9 10 9s10-4.0294 10-9c0-9-10-23-10-23m0 10s-4 6-4 10c0 2.2091 1.7909 4 4 4s4-1.7909 4-4c0-4-4-10-4-10"/>
                    <circle cx="20" cy="18" r="1.5" fill="#ffc837" opacity="0.8"/>
                    <circle cx="44" cy="18" r="1.5" fill="#e0b3ff" opacity="0.8"/>
                    <circle cx="32" cy="10" r="1" fill="#ff3d00" opacity="0.9"/>
                </svg>
                <span>MageForge Inspector</span>
            `;

            // Click to toggle inspector
            this.floatingButton.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleInspector();
            };

            document.body.appendChild(this.floatingButton);
        },

        /**
         * Update floating button state
         */
        updateFloatingButton() {
            if (!this.floatingButton) return;

            if (this.isOpen) {
                // Active state
                this.floatingButton.classList.add('mageforge-active');
            } else {
                // Inactive state
                this.floatingButton.classList.remove('mageforge-active');
            }
        },

        /**
         * Toggle inspector on/off
         */
        toggleInspector() {
            this.isOpen = !this.isOpen;

            if (this.isOpen) {
                this.activatePicker();
                this.$dispatch('mageforge:inspector:opened');
            } else {
                this.deactivatePicker();
                this.$dispatch('mageforge:inspector:closed');
            }

            this.updateFloatingButton();
        },

        /**
         * Close inspector
         */
        closeInspector() {
            this.isOpen = false;
            this.isPinned = false;
            this.removeDraggable();
            this.deactivatePicker();
            this.hideHighlight();
            this.$dispatch('mageforge:inspector:closed');
            this.updateFloatingButton();
        },

        /**
         * Activate element picker mode
         */
        activatePicker() {
            this.isPickerActive = true;
            document.addEventListener('mousemove', this.mouseMoveHandler);
            document.addEventListener('click', this.clickHandler, false); // Don't use capture
            document.body.style.cursor = 'crosshair';
        },

        /**
         * Deactivate element picker mode
         */
        deactivatePicker() {
            this.isPickerActive = false;

            // Clear any pending hover timeout
            if (this.hoverTimeout) {
                clearTimeout(this.hoverTimeout);
                this.hoverTimeout = null;
            }

            document.removeEventListener('mousemove', this.mouseMoveHandler);

            // Keep click handler active if pinned (for click-outside detection)
            if (!this.isPinned) {
                document.removeEventListener('click', this.clickHandler, false);
            }

            document.body.style.cursor = '';

            // Only hide if not pinned
            if (!this.isPinned) {
                this.hideHighlight();
            }

            this.hoveredElement = null;
            this.lastBadgeUpdate = 0;
        },

        /**
         * Handle mouse move over elements
         */
        handleMouseMove(e) {
            if (!this.isPickerActive) return;

            // Don't update if badge is pinned
            if (this.isPinned) return;

            // Don't update if mouse is over the floating button
            if (this.floatingButton && this.floatingButton.contains(e.target)) {
                return;
            }

            // Don't update if mouse is over the info badge
            if (this.infoBadge && this.infoBadge.contains(e.target)) {
                return;
            }

            const element = this.findInspectableElement(e.target);

            // Clear any existing hover timeout
            if (this.hoverTimeout) {
                clearTimeout(this.hoverTimeout);
                this.hoverTimeout = null;
            }

            if (element && element !== this.hoveredElement) {
                // Debounce hover updates for accurate positioning
                this.hoverTimeout = setTimeout(() => {
                    // Throttle badge updates to prevent flickering
                    const now = Date.now();
                    if (now - this.lastBadgeUpdate < this.badgeUpdateDelay) {
                        // Only update highlight, keep badge
                        this.hoveredElement = element;
                        this.showHighlight(element);
                        return;
                    }

                    this.hoveredElement = element;
                    this.lastBadgeUpdate = now;
                    this.showHighlight(element);
                    this.updatePanelData(element);
                    this.showInfoBadge(element);
                    this.incrementFeatureViews();
                }, this.hoverDelay);
            } else if (!element && this.hoveredElement) {
                // Only hide highlight when leaving element, keep badge visible
                if (this.highlightBox) {
                    this.highlightBox.style.display = 'none';
                }
                // Badge stays visible until hovering another element
            }
        },

        /**
         * Handle click on element
         */
        handleClick(e) {
            // Handle click outside badge when pinned
            if (this.isPinned && this.infoBadge) {
                // Check if click is outside badge
                if (!this.infoBadge.contains(e.target) && !this.floatingButton.contains(e.target)) {
                    this.unpinBadge();
                    return;
                }
                // Click inside badge - do nothing, let it stay open
                return;
            }

            if (!this.isPickerActive) return;

            // Don't handle clicks on the info badge during picking
            if (this.infoBadge && (this.infoBadge.contains(e.target) || this.infoBadge === e.target)) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const element = this.findInspectableElement(e.target);

            if (element) {
                this.selectedElement = element;
                this.updatePanelData(element);
                this.pinBadge();
            }
        },

        /**
         * Pin the badge after element selection
         */
        pinBadge() {
            this.isPinned = true;
            this.deactivatePicker();
            // Keep highlight and badge visible
            // Update badge to show close button
            if (this.selectedElement) {
                this.buildBadgeContent(this.selectedElement);
            }
            this.setupDraggable();
        },

        /**
         * Unpin and close the badge
         */
        unpinBadge() {
            this.isPinned = false;
            this.removeDraggable();
            this.hideHighlight();
            this.selectedElement = null;

            // Remove click handler
            document.removeEventListener('click', this.clickHandler, false);

            // Reactivate picker if inspector is still open
            if (this.isOpen) {
                this.activatePicker();
            }
        },

        /**
         * Find nearest inspectable element
         */
        findInspectableElement(target) {
            if (!target) return null;

            // Skip inspector's own elements
            if (target.classList && (target.classList.contains('mageforge-inspector') || target.closest('.mageforge-inspector'))) {
                return null;
            }

            // Skip body and html
            if (target.tagName === 'BODY' || target.tagName === 'HTML') {
                return null;
            }

            // Check if this element is part of a MageForge block
            const block = this.findBlockForElement(target);
            if (block) {
                // Attach block data to element for easy access
                target._mageforgeBlockData = block.data;
                return target;
            }

            return null;
        },



        /**
         * Show highlight overlay on element
         */
        showHighlight(element) {
            // If element has display:contents, use first child for dimensions
            let targetElement = element;
            const style = window.getComputedStyle(element);

            if (style.display === 'contents' && element.children.length > 0) {
                // Use first child element for positioning
                targetElement = element.children[0];
            }

            const rect = targetElement.getBoundingClientRect();

            // Only show if element has dimensions
            if (rect.width === 0 || rect.height === 0) {
                return;
            }

            this.highlightBox.style.display = 'block';
            this.highlightBox.style.top = `${rect.top + window.scrollY}px`;
            this.highlightBox.style.left = `${rect.left + window.scrollX}px`;
            this.highlightBox.style.width = `${rect.width}px`;
            this.highlightBox.style.height = `${rect.height}px`;
        },

        /**
         * Hide highlight overlay
         */
        hideHighlight() {
            if (this.highlightBox) {
                this.highlightBox.style.display = 'none';
            }
            if (this.infoBadge) {
                this.infoBadge.style.display = 'none';
            }
        },

        /**
         * Show info badge with element details
         */
        showInfoBadge(element) {
            const rect = this.getElementRect(element);
            const elementId = element.getAttribute('data-mageforge-id');

            // Only rebuild badge content if it's a different element
            if (this.infoBadge.dataset.currentElement !== elementId) {
                this.buildBadgeContent(element);
                this.infoBadge.dataset.currentElement = elementId;
            }

            this.positionBadge(rect);
        },

        /**
         * Get element rectangle (handles display:contents)
         */
        getElementRect(element) {
            let targetElement = element;
            const style = window.getComputedStyle(element);
            if (style.display === 'contents' && element.children.length > 0) {
                targetElement = element.children[0];
            }
            return targetElement.getBoundingClientRect();
        },

        /**
         * Build badge content with element metadata
         */
        buildBadgeContent(element) {
            const data = element._mageforgeBlockData || {
                template: '',
                block: '',
                module: '',
                viewModel: '',
                parent: '',
                alias: '',
                override: '0'
            };

            // Convert override string to boolean and add aliases for compatibility
            data.isOverride = data.override === '1';
            data.blockClass = data.block;
            data.parentBlock = data.parent;
            data.blockAlias = data.alias;

            // Clear badge
            this.infoBadge.innerHTML = '';

            // Add close button if pinned
            if (this.isPinned) {
                this.infoBadge.appendChild(this.createCloseButton());
            }

            // Create tab system
            this.createTabSystem(data, element);

            // Branding footer
            this.infoBadge.appendChild(this.createBrandingFooter());
        },

        /**
         * Create close button for pinned badge
         */
        createCloseButton() {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'mageforge-inspector-close';
            closeBtn.innerHTML = '✕';
            closeBtn.title = 'Close (or click outside)';

            closeBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.unpinBadge();
            };

            return closeBtn;
        },

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
            container.appendChild(this.createInfoSection('📄 Template', data.template, '#60a5fa'));

            // Block section
            container.appendChild(this.createInfoSection('📦 Block', data.blockClass, '#a78bfa'));

            // Optional sections
            if (data.blockAlias) {
                container.appendChild(this.createInfoSection('🏷️ Block Name', data.blockAlias, '#34d399'));
            }
            if (data.parentBlock) {
                container.appendChild(this.createInfoSection('⬆️ Parent Block', data.parentBlock, '#fb923c'));
            }
            if (data.viewModel) {
                container.appendChild(this.createInfoSection('⚡ ViewModel', data.viewModel, '#22d3ee'));
            }

            // Module section
            container.appendChild(this.createInfoSection('📍 Module', data.module, '#fbbf24'));
        },

        /**
         * Render Accessibility tab content
         */
        renderAccessibilityTab(container, element) {
            if (!element) return;

            const a11yData = this.analyzeAccessibility(element);

            // Semantic Element
            container.appendChild(this.createInfoSection('🏷️ Element Type', a11yData.tagName, '#60a5fa'));

            // ARIA Role
            if (a11yData.role) {
                container.appendChild(this.createInfoSection('👤 ARIA Role', a11yData.role, '#a78bfa'));
            }

            // Accessible Name
            if (a11yData.accessibleName) {
                container.appendChild(this.createInfoSection('🗣️ Accessible Name', a11yData.accessibleName, '#34d399'));
            }

            // ARIA Label
            if (a11yData.ariaLabel) {
                container.appendChild(this.createInfoSection('🏷️ ARIA Label', a11yData.ariaLabel, '#22d3ee'));
            }

            // ARIA Described By
            if (a11yData.ariaDescribedBy) {
                container.appendChild(this.createInfoSection('📝 ARIA Described By', a11yData.ariaDescribedBy, '#fbbf24'));
            }

            // Alt Text (for images)
            if (a11yData.altText !== null) {
                const altStatus = a11yData.altText ? a11yData.altText : '⚠️ Missing';
                const altColor = a11yData.altText ? '#34d399' : '#ef4444';
                container.appendChild(this.createInfoSection('🖼️ Alt Text', altStatus, altColor));
            }

            // Lazy Loading (for images)
            if (a11yData.lazyLoading !== null) {
                const { lazyIcon, lazyColor } = this.getLazyLoadingStyle(a11yData.lazyLoading);
                container.appendChild(this.createInfoSection(`${lazyIcon} Lazy Loading`, a11yData.lazyLoading, lazyColor));
            }

            // Tabindex
            if (a11yData.tabindex !== null) {
                container.appendChild(this.createInfoSection('⌨️ Tab Index', a11yData.tabindex.toString(), '#fb923c'));
            }

            // Focusable State
            const focusableText = a11yData.isFocusable ? '✅ Yes' : '❌ No';
            const focusableColor = a11yData.isFocusable ? '#34d399' : '#94a3b8';
            container.appendChild(this.createInfoSection('🎯 Focusable', focusableText, focusableColor));

            // ARIA Hidden
            if (a11yData.ariaHidden) {
                container.appendChild(this.createInfoSection('👻 ARIA Hidden', a11yData.ariaHidden, '#ef4444'));
            }

            // Interactive Element
            const interactiveText = a11yData.isInteractive ? '✅ Yes' : '❌ No';
            const interactiveColor = a11yData.isInteractive ? '#34d399' : '#94a3b8';
            container.appendChild(this.createInfoSection('🖱️ Interactive', interactiveText, interactiveColor));
        },

        /**
         * Get styling for lazy loading indicator
         */
        getLazyLoadingStyle(lazyLoading) {
            let lazyColor = '#94a3b8';
            let lazyIcon = '⚡';

            if (lazyLoading.includes('Native')) {
                lazyColor = '#34d399';
                lazyIcon = '✅';
            } else if (lazyLoading.includes('JavaScript')) {
                lazyColor = '#22d3ee';
                lazyIcon = '🔧';
            } else if (lazyLoading === 'Not set') {
                lazyColor = '#f59e0b';
                lazyIcon = '⚠️';
            }

            return { lazyIcon, lazyColor };
        },

        /**
         * Render Browser Metrics tab content (element-specific)
         *
         * @param {HTMLElement} container - Tab content container
         * @param {HTMLElement|null} element - Inspected element
         * @return {void}
         */
        renderBrowserMetricsTab(container, element) {
            if (!element) {
                this.renderNoBrowserMetrics(container);
                return;
            }

            let hasMetrics = false;

            if (this.renderRenderTimeMetric(container, element)) hasMetrics = true;
            if (this.renderLCPMetric(container, element)) hasMetrics = true;
            if (this.renderCLSMetric(container, element)) hasMetrics = true;
            if (this.renderINPMetric(container, element)) hasMetrics = true;
            if (this.renderElementTimingMetric(container, element)) hasMetrics = true;
            if (this.renderImageOptimizationMetric(container, element)) hasMetrics = true;
            if (this.renderResourceMetric(container, element)) hasMetrics = true;

            if (!hasMetrics) {
                this.renderNoBrowserMetrics(container);
            }
        },

        renderRenderTimeMetric(container, element) {
            const blockData = this.getBlockMetaData(element);
            if (blockData && blockData.performance) {
                const renderTime = parseFloat(blockData.performance.renderTime);
                const color = this.getRenderTimeColor(renderTime);
                const formattedTime = `${blockData.performance.renderTime} ms`;
                container.appendChild(this.createInfoSection('⏱️ Render Time', formattedTime, color));
                return true;
            }
            return false;
        },

        renderLCPMetric(container, element) {
            if (this.webVitals.lcp && this.webVitals.lcp.element) {
                const isLCP = this.webVitals.lcp.element === element || element.contains(this.webVitals.lcp.element);
                if (isLCP) {
                    const lcpValue = this.webVitals.lcp.value.toFixed(0);
                    const lcpColor = lcpValue < 2500 ? '#34d399' : (lcpValue < 4000 ? '#f59e0b' : '#ef4444');
                    container.appendChild(
                        this.createInfoSection('🎯 LCP (Largest Contentful Paint)', `${lcpValue} ms`, lcpColor)
                    );
                    container.appendChild(
                        this.createInfoSection('⚡ LCP Element', '✅ This element is critical for LCP!', '#ef4444')
                    );
                    return true;
                }
            }
            return false;
        },

        renderCLSMetric(container, element) {
            const elementCLS = this.getElementCLS(element);
            if (elementCLS > 0) {
                const clsColor = elementCLS < 0.1 ? '#34d399' : (elementCLS < 0.25 ? '#f59e0b' : '#ef4444');
                container.appendChild(
                    this.createInfoSection('📐 CLS (Layout Shift)', elementCLS.toFixed(3), clsColor)
                );
                const stabilityScore = Math.max(0, (1 - elementCLS * 4)).toFixed(2);
                const stabilityColor = stabilityScore > 0.75 ? '#34d399' : (stabilityScore > 0.5 ? '#f59e0b' : '#ef4444');
                container.appendChild(
                    this.createInfoSection('⚖️ Layout Stability Score', stabilityScore, stabilityColor)
                );
                return true;
            }
            return false;
        },

        renderINPMetric(container, element) {
            const isInteractive = this.checkIfInteractive(element, element.tagName.toLowerCase(), element.getAttribute('role'));
            if (isInteractive && this.webVitals.inp) {
                const inpValue = this.webVitals.inp.duration.toFixed(0);
                const inpColor = inpValue < 200 ? '#34d399' : (inpValue < 500 ? '#f59e0b' : '#ef4444');
                container.appendChild(
                    this.createInfoSection('⌨️ INP (Interaction)', `${inpValue} ms`, inpColor)
                );
                return true;
            }
            return false;
        },

        renderElementTimingMetric(container, element) {
            const elementTiming = this.getElementTiming(element);
            if (elementTiming) {
                const timingValue = (elementTiming.renderTime || elementTiming.loadTime).toFixed(0);
                const timingColor = timingValue < 2500 ? '#34d399' : (timingValue < 4000 ? '#f59e0b' : '#ef4444');
                container.appendChild(
                    this.createInfoSection('⏰ Element Timing', `${timingValue} ms (${elementTiming.identifier})`, timingColor)
                );
                return true;
            }
            return false;
        },

        renderImageOptimizationMetric(container, element) {
            const imageAnalysis = this.analyzeImageOptimization(element);
            if (imageAnalysis) {
                const modernScore = imageAnalysis.totalImages > 0
                    ? (imageAnalysis.modernFormats / imageAnalysis.totalImages * 100).toFixed(0)
                    : 0;
                const modernColor = modernScore > 75 ? '#34d399' : (modernScore > 25 ? '#f59e0b' : '#ef4444');
                container.appendChild(
                    this.createInfoSection('🖼️ Modern Image Formats', `${modernScore}% (${imageAnalysis.modernFormats}/${imageAnalysis.totalImages})`, modernColor)
                );

                const responsiveScore = imageAnalysis.totalImages > 0
                    ? (imageAnalysis.hasResponsive / imageAnalysis.totalImages * 100).toFixed(0)
                    : 0;
                const responsiveColor = responsiveScore > 75 ? '#34d399' : (responsiveScore > 25 ? '#f59e0b' : '#ef4444');
                const responsiveText = `${imageAnalysis.hasResponsive} of ${imageAnalysis.totalImages} ${imageAnalysis.totalImages === 1 ? 'image uses' : 'images use'} srcset`;
                container.appendChild(
                    this.createInfoSection('📱 Adaptive Images (srcset)', responsiveText, responsiveColor)
                );

                if (imageAnalysis.oversized > 0) {
                    container.appendChild(
                        this.createInfoSection('⚠️ Oversized Images', `${imageAnalysis.oversized} oversized`, '#ef4444')
                    );
                }

                if (imageAnalysis.issues.length > 0) {
                    const issuesText = imageAnalysis.issues.slice(0, 3).join(' • ');
                    const moreText = imageAnalysis.issues.length > 3 ? ` (+${imageAnalysis.issues.length - 3} more)` : '';
                    container.appendChild(
                        this.createInfoSection('💡 Optimization Tips', issuesText + moreText, '#f59e0b')
                    );
                }
                return true;
            }
            return false;
        },

        renderResourceMetric(container, element) {
            const elementResources = this.getElementResources(element);
            if (elementResources.count > 0) {
                this.renderElementResourceMetrics(container, elementResources);
                return true;
            }
            return false;
        },

        /**
         * Render no browser metrics message
         */
        renderNoBrowserMetrics(container) {
            const noDataDiv = document.createElement('div');
            noDataDiv.className = 'mageforge-no-data';
            noDataDiv.innerHTML = `
                <div class="mageforge-no-data-icon">🌐</div>
                <div class="mageforge-no-data-title">No Element-Specific Metrics</div>
                <div class="mageforge-no-data-desc">This element has no measurable browser performance impact</div>
            `;
            container.appendChild(noDataDiv);
        },

        /**
         * Get CLS (Cumulative Layout Shift) for specific element
         *
         * @param {HTMLElement} element
         * @return {number}
         */
        getElementCLS(element) {
            if (!this.webVitals.cls || this.webVitals.cls.length === 0) {
                return 0;
            }

            let totalCLS = 0;
            this.webVitals.cls.forEach(shift => {
                if (shift.sources) {
                    shift.sources.forEach(source => {
                        if (source.node === element || element.contains(source.node) || source.node.contains(element)) {
                            totalCLS += shift.value;
                        }
                    });
                }
            });

            return totalCLS;
        },

        /**
         * Get Element Timing for specific element
         *
         * @param {HTMLElement} element
         * @return {object|null}
         */
        getElementTiming(element) {
            if (!this.webVitals.elementTimings || this.webVitals.elementTimings.length === 0) {
                return null;
            }

            // Check if this element or any child has element timing
            const timing = this.webVitals.elementTimings.find(et =>
                et.element === element || element.contains(et.element)
            );

            return timing || null;
        },

        /**
         * Get resources loaded by element (images, scripts, stylesheets)
         *
         * @param {HTMLElement} element
         * @return {{count: number, size: number, byType: object, items: array}}
         */
        getElementResources(element) {
            const result = {
                count: 0,
                size: 0,
                byType: { script: 0, css: 0, img: 0, font: 0, other: 0 },
                items: []
            };

            // Get all resource URLs from element and children
            const resourceUrls = new Set();

            // Images
            const images = [element, ...element.querySelectorAll('img')];
            images.forEach(img => {
                if (img.tagName === 'IMG' && img.src) {
                    resourceUrls.add(img.src);
                }
            });

            // Scripts
            const scripts = element.querySelectorAll('script[src]');
            scripts.forEach(script => {
                if (script.src) {
                    resourceUrls.add(script.src);
                }
            });

            // Stylesheets
            const links = element.querySelectorAll('link[rel="stylesheet"]');
            links.forEach(link => {
                if (link.href) {
                    resourceUrls.add(link.href);
                }
            });

            // Videos
            const videos = element.querySelectorAll('video[src], source[src]');
            videos.forEach(video => {
                if (video.src) {
                    resourceUrls.add(video.src);
                }
            });

            // Get performance entries for these resources
            const allResources = performance.getEntriesByType('resource');
            resourceUrls.forEach(url => {
                const resource = allResources.find(r => r.name === url);
                if (resource) {
                    result.count++;
                    result.size += resource.transferSize || 0;
                    result.items.push(resource);

                    // Categorize
                    if (resource.name.match(/\.(js|mjs)$/)) result.byType.script++;
                    else if (resource.name.includes('.css')) result.byType.css++;
                    else if (resource.name.match(/\.(jpg|jpeg|png|gif|webp|svg|avif)$/i)) result.byType.img++;
                    else if (resource.name.match(/\.(woff2?|ttf|otf|eot)$/i)) result.byType.font++;
                    else result.byType.other++;
                }
            });

            return result;
        },

        /**
         * Render resource metrics for specific element
         *
         * @param {HTMLElement} container
         * @param {object} resourceData
         */
        renderElementResourceMetrics(container, resourceData) {
            const sizeText = this.formatResourceSize(resourceData.size);
            const resourceLabel = this.determineResourceLabel(resourceData);

            container.appendChild(
                this.createInfoSection('📦 Element Resources', `${resourceData.count} ${resourceLabel} (${sizeText})`, '#60a5fa')
            );

            this.renderResourceBreakdown(container, resourceData);
        },

        formatResourceSize(size) {
            if (size < 1024) {
                return `${size} B`;
            } else if (size < 1024 * 1024) {
                return `${(size / 1024).toFixed(1)} KB`;
            } else {
                return `${(size / (1024 * 1024)).toFixed(2)} MB`;
            }
        },

        determineResourceLabel(resourceData) {
            const hasImages = resourceData.byType.img > 0;
            const hasScripts = resourceData.byType.script > 0;
            const hasCss = resourceData.byType.css > 0;
            const hasFonts = resourceData.byType.font > 0;
            const hasOther = resourceData.byType.other > 0;
            const typeCount = (hasImages ? 1 : 0) + (hasScripts ? 1 : 0) + (hasCss ? 1 : 0) + (hasFonts ? 1 : 0) + (hasOther ? 1 : 0);

            if (typeCount === 1) {
                if (hasImages) return resourceData.byType.img === 1 ? 'Image' : 'Images';
                if (hasScripts) return resourceData.byType.script === 1 ? 'Script' : 'Scripts';
                if (hasCss) return resourceData.byType.css === 1 ? 'Stylesheet' : 'Stylesheets';
                if (hasFonts) return resourceData.byType.font === 1 ? 'Font' : 'Fonts';
                if (hasOther) return resourceData.byType.other === 1 ? 'Resource' : 'Resources';
            }
            return resourceData.count === 1 ? 'Resource' : 'Resources';
        },

        renderResourceBreakdown(container, resourceData) {
            const hasImages = resourceData.byType.img > 0;
            const hasScripts = resourceData.byType.script > 0;
            const hasCss = resourceData.byType.css > 0;
            const hasFonts = resourceData.byType.font > 0;
            const hasOther = resourceData.byType.other > 0;
            const typeCount = (hasImages ? 1 : 0) + (hasScripts ? 1 : 0) + (hasCss ? 1 : 0) + (hasFonts ? 1 : 0) + (hasOther ? 1 : 0);

            if (typeCount > 1) {
                const types = [];
                if (resourceData.byType.img > 0) types.push(`Images: ${resourceData.byType.img}`);
                if (resourceData.byType.script > 0) types.push(`JS: ${resourceData.byType.script}`);
                if (resourceData.byType.css > 0) types.push(`CSS: ${resourceData.byType.css}`);
                if (resourceData.byType.font > 0) types.push(`Fonts: ${resourceData.byType.font}`);
                if (resourceData.byType.other > 0) types.push(`Other: ${resourceData.byType.other}`);

                container.appendChild(
                    this.createInfoSection('📑 Resource Types', types.join(', '), '#a78bfa')
                );
            }
        },

        /**
         * Render Performance tab content
         *
         * @param {HTMLElement} container - Tab content container
         * @param {HTMLElement|null} element - Inspected element
         * @return {void}
         */
        renderPerformanceTab(container, element) {
            // Guard: No element
            if (!element) {
                this.renderNoPerformanceData(container);
                return;
            }

            // Get block metadata (may be null)
            const blockData = this.getBlockMetaData(element);

            // Guard: No block data or missing cache data
            if (!blockData || !blockData.cache) {
                this.renderNoPerformanceData(container);
                return;
            }

            // Render cache section only
            this.renderCacheSection(container, blockData.cache);
        },

        /**
         * Render "No Performance Data" message
         */
        renderNoPerformanceData(container) {
            const noDataDiv = document.createElement('div');
            noDataDiv.className = 'mageforge-no-data';
            noDataDiv.innerHTML = `
                <div class="mageforge-no-data-icon">⚡</div>
                <div class="mageforge-no-data-title">No Performance Data</div>
                <div class="mageforge-no-data-desc">This element is not inside a Magento template block</div>
            `;
            container.appendChild(noDataDiv);
        },

        /**
         * Render render time section
         */
        renderRenderTimeSection(container, performanceData) {
            const renderTime = parseFloat(performanceData.renderTime);
            const color = this.getRenderTimeColor(renderTime);
            const formattedTime = `${performanceData.renderTime} ms`;

            container.appendChild(this.createInfoSection('⏱️ Render Time', formattedTime, color));
        },

        /**
         * Render cache section
         */
        renderCacheSection(container, cacheData) {
            // Page-level cache warning (if page is not cacheable)
            if (cacheData.pageCacheable === false) {
                const warningDiv = document.createElement('div');
                warningDiv.className = 'mageforge-warning-box';
                warningDiv.innerHTML = `
                    <span style="font-size: 14px;">⚠️</span>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 2px; color: #ef4444;">Page Not Cacheable</div>
                        <div style="color: #fca5a5; font-size: 10px;">This entire page cannot be cached (layout XML: cacheable="false")</div>
                        <div style="color: #fca5a5; font-size: 10px; margin-top: 2px;">Block settings below are overridden by page-level config</div>
                    </div>
                `;
                container.appendChild(warningDiv);
            }

            // Block-level cacheable status
            const cacheableText = cacheData.cacheable ? '✅ Yes' : '❌ No';
            const cacheableColor = cacheData.cacheable ? '#34d399' : '#94a3b8';
            const cacheableLabel = cacheData.pageCacheable === false ? '💾 Block Cacheable (ignored)' : '💾 Block Cacheable';
            container.appendChild(this.createInfoSection(cacheableLabel, cacheableText, cacheableColor));

            // Cache lifetime (show for all cacheable blocks)
            if (cacheData.cacheable) {
                const lifetimeText = (cacheData.lifetime === null || cacheData.lifetime === 0)
                    ? 'Unlimited'
                    : `${cacheData.lifetime}s`;
                container.appendChild(this.createInfoSection('⏳ Cache Lifetime', lifetimeText, '#60a5fa'));
            }

            // Cache key
            if (cacheData.key && cacheData.key !== '') {
                container.appendChild(this.createInfoSection('🔑 Cache Key', cacheData.key, '#a78bfa'));
            }

            // Cache tags
            if (cacheData.tags && cacheData.tags.length > 0) {
                const tagsText = cacheData.tags.join(', ');
                container.appendChild(this.createInfoSection('🏷️ Cache Tags', tagsText, '#22d3ee'));
            }
        },

        /**
         * Render DOM complexity section
         */
        renderDOMComplexitySection(container, element) {
            const complexity = this.calculateDOMComplexity(element);
            const rating = this.getComplexityRating(complexity);

            // Child count
            container.appendChild(
                this.createInfoSection('📊 Child Nodes', complexity.childCount.toString(), '#60a5fa')
            );

            // Tree depth
            const depthColor = complexity.depth > this.PERF_DOM_DEPTH_WARNING ? '#f59e0b' : '#34d399';
            container.appendChild(
                this.createInfoSection('🌳 Tree Depth', complexity.depth.toString(), depthColor)
            );

            // Total nodes
            const totalColor = rating === 'high' ? '#ef4444' : (rating === 'medium' ? '#f59e0b' : '#34d399');
            container.appendChild(
                this.createInfoSection('🔢 Total Nodes', complexity.totalNodes.toString(), totalColor)
            );

            // Complexity rating
            const ratingEmoji = rating === 'low' ? '✅' : (rating === 'medium' ? '⚠️' : '❌');
            const ratingText = `${ratingEmoji} ${rating.toUpperCase()}`;
            container.appendChild(
                this.createInfoSection('📈 Complexity', ratingText, totalColor)
            );
        },

        /**
         * Render Web Vitals section
         */
        renderWebVitalsSection(container, element) {
            const vitalsInfo = this.getWebVitalsForElement(element);

            if (vitalsInfo.isLCP) {
                container.appendChild(
                    this.createInfoSection('🎯 LCP Element', '✅ Yes - Performance Critical!', '#ef4444')
                );
            }

            if (vitalsInfo.contributesCLS && vitalsInfo.contributesCLS > 0) {
                container.appendChild(
                    this.createInfoSection('📐 CLS Impact', `${vitalsInfo.contributesCLS.toFixed(3)}`, '#f59e0b')
                );
            }

            if (!vitalsInfo.isLCP && !vitalsInfo.contributesCLS) {
                container.appendChild(
                    this.createInfoSection('✨ Web Vitals', 'Not Critical', '#94a3b8')
                );
            }
        },

        /**
         * Render page timings section
         */
        renderPageTimingsSection(container) {
            if (!this.pageTimings) {
                return;
            }

            container.appendChild(
                this.createInfoSection('📄 DOMContentLoaded', `${this.pageTimings.domContentLoaded} ms`, '#60a5fa')
            );

            container.appendChild(
                this.createInfoSection('🌐 Page Load', `${this.pageTimings.loadComplete} ms`, '#a78bfa')
            );
        },

        // ============================================================================
        // Performance Analysis Utilities
        // ============================================================================

        /**
         * Initialize Web Vitals tracking
         */
        initWebVitalsTracking() {
            // Check if PerformanceObserver is supported
            if (!('PerformanceObserver' in window)) {
                console.warn('[MageForge Inspector] PerformanceObserver not supported');
                return;
            }

            try {
                // Largest Contentful Paint (LCP)
                const lcpObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    this.webVitals.lcp = {
                        element: lastEntry.element,
                        value: lastEntry.renderTime || lastEntry.loadTime,
                        time: lastEntry.startTime
                    };
                });
                lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });

                // Cumulative Layout Shift (CLS)
                const clsObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput) {
                            this.webVitals.cls.push({
                                value: entry.value,
                                time: entry.startTime,
                                sources: entry.sources || []
                            });
                        }
                    }
                });
                clsObserver.observe({ type: 'layout-shift', buffered: true });

                // Interaction to Next Paint (INP) - via first-input as fallback
                const inpObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    if (entries.length > 0) {
                        const firstEntry = entries[0];
                        this.webVitals.inp = {
                            delay: firstEntry.processingStart - firstEntry.startTime,
                            duration: firstEntry.duration,
                            time: firstEntry.startTime
                        };
                    }
                });
                inpObserver.observe({ type: 'first-input', buffered: true });

                // First Contentful Paint (FCP)
                const paintObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.name === 'first-contentful-paint') {
                            this.webVitals.fcp = {
                                value: entry.startTime,
                                time: entry.startTime
                            };
                        }
                    }
                });
                paintObserver.observe({ type: 'paint', buffered: true });

                // Long Tasks (>50ms)
                const longTaskObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        this.longTasks.push({
                            duration: entry.duration,
                            startTime: entry.startTime,
                            attribution: entry.attribution || []
                        });
                    }
                });
                longTaskObserver.observe({ type: 'longtask', buffered: true });

                // Element Timing API - for elements with elementtiming attribute
                const elementTimingObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        this.webVitals.elementTimings.push({
                            element: entry.element,
                            identifier: entry.identifier,
                            renderTime: entry.renderTime,
                            loadTime: entry.loadTime,
                            startTime: entry.startTime
                        });
                    }
                });
                elementTimingObserver.observe({ type: 'element', buffered: true });
            } catch (e) {
                console.warn('[MageForge Inspector] Performance tracking failed:', e);
            }
        },

        /**
         * Cache page timing metrics
         */
        cachePageTimings() {
            // Try modern Navigation Timing API first
            const navEntries = performance.getEntriesByType('navigation');
            if (navEntries && navEntries.length > 0) {
                const nav = navEntries[0];
                this.pageTimings = {
                    domContentLoaded: Math.round(nav.domContentLoadedEventEnd - nav.domContentLoadedEventStart),
                    loadComplete: Math.round(nav.loadEventEnd - nav.fetchStart)
                };
            } else if (performance.timing) {
                // Fallback to older API
                const timing = performance.timing;
                this.pageTimings = {
                    domContentLoaded: Math.round(timing.domContentLoadedEventEnd - timing.navigationStart),
                    loadComplete: Math.round(timing.loadEventEnd - timing.navigationStart)
                };
            }
        },

        /**
         * Calculate DOM complexity metrics
         *
         * @param {HTMLElement} element - The element to analyze
         * @return {{childCount: number, depth: number, totalNodes: number}}
         */
        calculateDOMComplexity(element) {
            if (!element || !(element instanceof HTMLElement)) {
                return { childCount: 0, depth: 0, totalNodes: 0 };
            }

            const childCount = element.childElementCount;
            const totalNodes = element.querySelectorAll('*').length;
            const depth = this.getMaxDepth(element);

            return { childCount, depth, totalNodes };
        },

        /**
         * Get maximum depth of element tree
         *
         * @param {HTMLElement} element
         * @param {number} currentDepth
         * @return {number}
         * @private
         */
        getMaxDepth(element, currentDepth = 0) {
            if (!element.children.length) {
                return currentDepth;
            }

            let maxChildDepth = currentDepth;
            for (const child of element.children) {
                const depth = this.getMaxDepth(child, currentDepth + 1);
                maxChildDepth = Math.max(maxChildDepth, depth);
            }

            return maxChildDepth;
        },

        /**
         * Get complexity rating based on total nodes
         *
         * @param {{childCount: number, depth: number, totalNodes: number}} complexity
         * @return {string} 'low' | 'medium' | 'high'
         */
        getComplexityRating(complexity) {
            if (complexity.totalNodes < this.PERF_DOM_COMPLEXITY_LOW) {
                return 'low';
            } else if (complexity.totalNodes < this.PERF_DOM_COMPLEXITY_HIGH) {
                return 'medium';
            } else {
                return 'high';
            }
        },

        /**
         * Get Web Vitals information for specific element
         *
         * @param {HTMLElement} element
         * @return {{isLCP: boolean, contributesCLS: number, isInteractive: boolean}}
         */
        getWebVitalsForElement(element) {
            const result = {
                isLCP: false,
                contributesCLS: 0,
                isInteractive: false
            };

            // Check if element is LCP candidate
            if (this.webVitals.lcp && this.webVitals.lcp.element) {
                result.isLCP = this.webVitals.lcp.element === element ||
                               element.contains(this.webVitals.lcp.element);
            }

            // Calculate CLS contribution
            if (this.webVitals.cls && this.webVitals.cls.length > 0) {
                this.webVitals.cls.forEach(shift => {
                    if (shift.sources) {
                        shift.sources.forEach(source => {
                            if (source.node === element || element.contains(source.node)) {
                                result.contributesCLS += shift.value;
                            }
                        });
                    }
                });
            }

            return result;
        },

        /**
         * Get color for render time based on thresholds
         *
         * @param {number} renderTimeMs
         * @return {string} Color hex code
         */
        getRenderTimeColor(renderTimeMs) {
            if (renderTimeMs < this.PERF_RENDER_TIME_GOOD) {
                return '#34d399'; // Green
            } else if (renderTimeMs < this.PERF_RENDER_TIME_WARNING) {
                return '#f59e0b'; // Orange/Yellow
            } else {
                return '#ef4444'; // Red
            }
        },

        /**
         * Get block metadata with performance data for element
         *
         * @param {HTMLElement} element
         * @return {Object|null} Block data with performance and cache info
         */
        getBlockMetaData(element) {
            const block = this.findBlockForElement(element);
            if (!block || !block.data) {
                return null;
            }

            const data = block.data;

            // Type validation for performance data
            const hasPerformanceData =
                data.performance &&
                typeof data.performance.renderTime === 'string' &&
                typeof data.performance.timestamp === 'number';

            // Type validation for cache data
            const hasCacheData =
                data.cache &&
                typeof data.cache.cacheable === 'boolean' &&
                (data.cache.lifetime === null || typeof data.cache.lifetime === 'number') &&
                typeof data.cache.key === 'string' &&
                Array.isArray(data.cache.tags);

            if (!hasPerformanceData || !hasCacheData) {
                return null;
            }

            return data;
        },

        /**
         * Analyze accessibility features of an element
         */
        analyzeAccessibility(element) {
            const tagName = element.tagName.toLowerCase();
            const role = element.getAttribute('role') || this.getImplicitRole(tagName);

            return {
                tagName: tagName,
                role: role,
                ariaLabel: element.getAttribute('aria-label'),
                ariaLabelledBy: element.getAttribute('aria-labelledby'),
                ariaDescribedBy: element.getAttribute('aria-describedby'),
                ariaHidden: element.getAttribute('aria-hidden'),
                tabindex: element.getAttribute('tabindex'),
                altText: this.getAltText(element, tagName),
                lazyLoading: this.checkLazyLoading(element, tagName),
                accessibleName: this.determineAccessibleName(element, tagName),
                isFocusable: this.isFocusable(element, element.getAttribute('tabindex')),
                isInteractive: this.checkIfInteractive(element, tagName, role)
            };
        },

        /**
         * Get alt text for images
         */
        getAltText(element, tagName) {
            return tagName === 'img' ? element.getAttribute('alt') : null;
        },

        /**
         * Check lazy loading status for images
         */
        checkLazyLoading(element, tagName) {
            if (tagName !== 'img') return null;

            const loadingAttr = element.getAttribute('loading');
            const hasDataSrc = element.hasAttribute('data-src') || element.hasAttribute('data-lazy');

            if (loadingAttr === 'lazy') {
                return 'Native (loading="lazy")';
            } else if (hasDataSrc) {
                return 'JavaScript (data-src)';
            } else if (loadingAttr === 'eager') {
                return 'Disabled (loading="eager")';
            }
            return 'Not set';
        },

        /**
         * Determine accessible name from various sources
         */
        determineAccessibleName(element, tagName) {
            const ariaLabel = element.getAttribute('aria-label');
            if (ariaLabel) return ariaLabel;

            const ariaLabelledBy = element.getAttribute('aria-labelledby');
            if (ariaLabelledBy) {
                const labelElement = document.getElementById(ariaLabelledBy);
                return labelElement ? labelElement.textContent.trim() : ariaLabelledBy;
            }

            const altText = tagName === 'img' ? element.getAttribute('alt') : null;
            if (altText) return altText;

            const title = element.getAttribute('title');
            if (title) return title;

            const textContent = element.textContent.trim();
            if (textContent && textContent.length < 100) {
                return textContent.substring(0, 50) + (textContent.length > 50 ? '...' : '');
            }

            return null;
        },

        /**
         * Check if element is interactive
         */
        checkIfInteractive(element, tagName, role) {
            const interactiveTags = ['a', 'button', 'input', 'select', 'textarea', 'details', 'summary'];
            const interactiveRoles = ['button', 'link', 'tab', 'menuitem', 'checkbox', 'radio', 'switch'];

            return interactiveTags.includes(tagName) ||
                   interactiveRoles.includes(role) ||
                   element.hasAttribute('onclick') ||
                   element.style.cursor === 'pointer';
        },

        /**
         * Get implicit ARIA role for HTML elements
         */
        getImplicitRole(tagName) {
            const roleMap = {
                'button': 'button',
                'a': 'link',
                'nav': 'navigation',
                'header': 'banner',
                'footer': 'contentinfo',
                'main': 'main',
                'aside': 'complementary',
                'section': 'region',
                'article': 'article',
                'form': 'form',
                'img': 'img',
                'input': 'textbox',
                'h1': 'heading',
                'h2': 'heading',
                'h3': 'heading',
                'h4': 'heading',
                'h5': 'heading',
                'h6': 'heading',
                'ul': 'list',
                'ol': 'list',
                'li': 'listitem'
            };
            return roleMap[tagName] || null;
        },

        /**
         * Check if element is focusable
         */
        isFocusable(element, tabindex) {
            // Explicitly focusable via tabindex
            if (tabindex !== null && parseInt(tabindex) >= 0) {
                return true;
            }

            // Naturally focusable elements
            const focusableTags = ['a', 'button', 'input', 'select', 'textarea', 'details', 'summary'];
            const tagName = element.tagName.toLowerCase();

            if (focusableTags.includes(tagName)) {
                // Check if disabled
                if (element.hasAttribute('disabled')) {
                    return false;
                }
                // Links need href
                if (tagName === 'a' && !element.hasAttribute('href')) {
                    return false;
                }
                return true;
            }

            return false;
        },

        /**
         * Analyze image optimization for element
         *
         * @param {HTMLElement} element
         * @return {object|null} Image optimization metrics
         */
        analyzeImageOptimization(element) {
            // Find all images in/on element
            const images = element.tagName === 'IMG' ? [element] : Array.from(element.querySelectorAll('img'));
            if (images.length === 0) return null;

            const analysis = {
                totalImages: images.length,
                modernFormats: 0,
                hasResponsive: 0,
                oversized: 0,
                issues: []
            };

            images.forEach((img, idx) => {
                const src = img.currentSrc || img.src;
                if (!src) return;

                // Check modern formats (WebP, AVIF)
                if (src.match(/\.(webp|avif)$/i)) {
                    analysis.modernFormats++;
                } else if (src.match(/\.(jpg|jpeg|png|gif)$/i)) {
                    analysis.issues.push(`Image ${idx + 1}: Consider WebP/AVIF format`);
                }

                // Check responsive images
                if (img.hasAttribute('srcset') || img.hasAttribute('sizes')) {
                    analysis.hasResponsive++;
                } else if (img.width > 400) {
                    analysis.issues.push(`Image ${idx + 1}: Missing srcset for responsive optimization`);
                }

                // Check oversizing (rendered size vs natural size)
                if (img.naturalWidth && img.width) {
                    const oversizeRatio = img.naturalWidth / img.width;
                    if (oversizeRatio > 1.5) {
                        analysis.oversized++;
                        const wastedPercent = Math.round((1 - 1/oversizeRatio) * 100);
                        analysis.issues.push(`Image ${idx + 1}: ${wastedPercent}% oversized (${img.naturalWidth}px served, ${img.width}px displayed)`);
                    }
                }
            });

            return analysis;
        },

        /**
         * Create branding footer
         */
        createBrandingFooter() {
            const brandingDiv = document.createElement('div');
            brandingDiv.className = 'mageforge-branding-footer';

            const madeWithDiv = document.createElement('div');
            madeWithDiv.innerHTML = 'Made with <span style="color: #ff6b6b; font-size: 12px;">🧡</span> by <span style="color: #60a5fa; font-weight: 600;">MageForge</span>';
            brandingDiv.appendChild(madeWithDiv);

            const featureLinkDiv = document.createElement('div');
            featureLinkDiv.className = 'mageforge-feature-link-container';

            const featureLink = document.createElement('a');
            featureLink.href = 'https://github.com/OpenForgeProject/mageforge/issues/new?template=feature_request.md';
            featureLink.target = '_blank';
            featureLink.rel = 'noopener noreferrer';
            featureLink.innerHTML = 'You miss a <span style="text-decoration: underline;">Feature?</span>';
            featureLink.className = 'mageforge-feature-link';

            featureLinkDiv.appendChild(featureLink);
            brandingDiv.appendChild(featureLinkDiv);

            return brandingDiv;
        },

        /**
         * Position badge relative to element
         */
        positionBadge(rect) {
            this.infoBadge.style.display = 'block';

            const badgeRect = this.infoBadge.getBoundingClientRect();
            const badgeOffset = 0;

            // Calculate initial position
            let x = rect.left + window.scrollX;
            let y = rect.bottom + window.scrollY + badgeOffset;

            // Validate coordinates
            if (!isFinite(x) || !isFinite(y) || x < 0 || y < 0) {
                x = 10;
                y = 10;
            }

            // Constrain horizontally
            x = this.constrainHorizontally(x, badgeRect.width);

            // Check vertical space and adjust if needed
            const showAbove = this.shouldShowAbove(y, badgeRect.height);
            if (showAbove) {
                y = rect.top + window.scrollY - badgeRect.height - badgeOffset;
                if (y < window.scrollY + 10) {
                    y = window.scrollY + 10;
                }
            }

            // Update badge styling based on position
            this.updateBadgePlacement(showAbove);

            // Apply position
            this.infoBadge.style.left = `${x}px`;
            this.infoBadge.style.top = `${y}px`;
        },

        /**
         * Constrain x position horizontally within viewport
         */
        constrainHorizontally(x, badgeWidth) {
            const maxX = window.innerWidth + window.scrollX - badgeWidth - 10;
            const minX = window.scrollX + 10;

            if (x > maxX) return maxX;
            if (x < minX) return minX;
            return x;
        },

        /**
         * Check if badge should be shown above element
         */
        shouldShowAbove(y, badgeHeight) {
            return y + badgeHeight > window.innerHeight + window.scrollY;
        },

        /**
         * Update badge styling based on placement (above/below)
         */
        updateBadgePlacement(showAbove) {
            const arrow = this.infoBadge.querySelector('.mageforge-inspector-arrow');

            if (showAbove) {
                // Badge above element
                this.infoBadge.style.borderRadius = '12px 12px 0 0';
                if (arrow) {
                    arrow.style.top = 'auto';
                    arrow.style.bottom = '-8px';
                    arrow.style.borderBottom = 'none';
                    arrow.style.borderTop = '8px solid rgba(15, 23, 42, 0.98)';
                }
            } else {
                // Badge below element
                this.infoBadge.style.borderRadius = '0 0 12px 12px';
                if (arrow) {
                    arrow.style.top = '-8px';
                    arrow.style.bottom = 'auto';
                    arrow.style.borderTop = 'none';
                    arrow.style.borderBottom = '8px solid rgba(15, 23, 42, 0.98)';
                }
            }
        },

        /**
         * Create info section with clickable text to copy
         */
        createInfoSection(title, text, titleColor) {
            const container = document.createElement('div');
            container.className = 'mageforge-info-section';

            const titleDiv = document.createElement('div');
            // Map common hex colors to classes, fallback to class
            const colorMap = {
                '#60a5fa': 'mageforge-text-blue',
                '#a78bfa': 'mageforge-text-purple',
                '#34d399': 'mageforge-text-green',
                '#fb923c': 'mageforge-text-orange',
                '#22d3ee': 'mageforge-text-cyan',
                '#fbbf24': 'mageforge-text-yellow',
                '#ef4444': 'mageforge-text-red',
                '#f59e0b': 'mageforge-text-amber',
                '#94a3b8': 'mageforge-text-gray'
            };
            const colorClass = colorMap[titleColor] || 'mageforge-text-gray';

            titleDiv.className = `mageforge-info-title ${colorClass}`;
            titleDiv.textContent = title;

            // Handle custom color if not in map (e.g. from dynamic score)
            if (!colorMap[titleColor] && titleColor && titleColor.startsWith('#')) {
                titleDiv.style.color = titleColor;
            }

            const textSpan = document.createElement('span');
            textSpan.className = 'mageforge-info-value';
            textSpan.textContent = text;
            textSpan.title = 'Click to copy';

            const originalText = text;

            // Click to copy
            textSpan.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Try to copy
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(originalText).then(() => {
                        textSpan.textContent = 'copied!';
                        textSpan.classList.add('copied');
                        setTimeout(() => {
                            textSpan.textContent = originalText;
                            textSpan.classList.remove('copied');
                        }, 1500);
                    }).catch(() => {
                        this.legacyCopy(originalText, textSpan);
                    });
                } else {
                    this.legacyCopy(originalText, textSpan);
                }
            };

            container.appendChild(titleDiv);
            container.appendChild(textSpan);

            return container;
        },

        /**
         * Legacy copy method
         */
        legacyCopy(text, element) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            document.body.appendChild(textarea);
            textarea.select();

            const originalText = text;

            try {
                const success = document.execCommand('copy');
                if (success) {
                    element.textContent = 'copied!';
                    element.classList.add('copied');
                    setTimeout(() => {
                        element.textContent = originalText;
                        element.classList.remove('copied');
                    }, 1500);
                } else {
                    throw new Error('Copy failed');
                }
            } catch (err) {
                element.textContent = 'failed';
                element.classList.add('copy-failed');
                setTimeout(() => {
                    element.textContent = originalText;
                    element.classList.remove('copy-failed');
                }, 1500);
            }

            document.body.removeChild(textarea);
        },

        // ============================================================================
        // Draggable & Connector Logic
        // ============================================================================

        /**
         * Enable dragging for the badge
         */
        setupDraggable() {
            if (!this.infoBadge) return;

            this.infoBadge.classList.add('draggable');

            // Bind handlers
            this.dragStartHandler = (e) => this.handleDragStart(e);
            this.dragHandler = (e) => this.handleDrag(e);
            this.dragEndHandler = (e) => this.handleDragEnd(e);

            this.infoBadge.addEventListener('mousedown', this.dragStartHandler);
        },

        /**
         * Disable dragging
         */
        removeDraggable() {
            if (!this.infoBadge) return;

            this.infoBadge.classList.remove('draggable');
            this.infoBadge.removeEventListener('mousedown', this.dragStartHandler);
            document.removeEventListener('mousemove', this.dragHandler);
            document.removeEventListener('mouseup', this.dragEndHandler);

            this.removeConnector();
        },

        /**
         * Handle drag start
         */
        handleDragStart(e) {
            // Ignore clicks on close button or content internal interactive elements
            if (e.target.closest('button') || e.target.closest('.mageforge-info-value')) {
                return;
            }

            this.isDragging = true;
            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;

            const rect = this.infoBadge.getBoundingClientRect();
            this.initialBadgeX = rect.left;
            this.initialBadgeY = rect.top;

            // Remove static arrow
            const arrow = this.infoBadge.querySelector('.mageforge-inspector-arrow');
            if (arrow) arrow.style.display = 'none';

            // Create connector
            this.createConnector();

            // Bind global move/up handlers
            document.addEventListener('mousemove', this.dragHandler);
            document.addEventListener('mouseup', this.dragEndHandler);
        },

        /**
         * Handle dragging movement
         */
        handleDrag(e) {
            if (!this.isDragging) return;

            const deltaX = e.clientX - this.dragStartX;
            const deltaY = e.clientY - this.dragStartY;

            const newX = this.initialBadgeX + deltaX;
            const newY = this.initialBadgeY + deltaY;

            this.infoBadge.style.left = `${newX}px`;
            this.infoBadge.style.top = `${newY}px`;
            this.infoBadge.style.transform = 'none'; // reset any potential transform

            this.updateConnector();
        },

        /**
         * Handle drag end
         */
        handleDragEnd() {
            this.isDragging = false;
            document.removeEventListener('mousemove', this.dragHandler);
            document.removeEventListener('mouseup', this.dragEndHandler);
        },

        /**
         * Create SVG connector
         */
        createConnector() {
            if (this.connectorSvg) return;

            const ns = 'http://www.w3.org/2000/svg';
            const svg = document.createElementNS(ns, 'svg');
            svg.classList.add('mageforge-connector-svg');

            // Line
            const line = document.createElementNS(ns, 'line');
            line.classList.add('mageforge-connector-line');
            svg.appendChild(line);

            // Dot on element
            const dot = document.createElementNS(ns, 'circle');
            dot.classList.add('mageforge-connector-dot');
            dot.setAttribute('r', '4');
            svg.appendChild(dot);

            document.body.appendChild(svg);
            this.connectorSvg = svg;
            this.updateConnector();
        },

        /**
         * Remove connector
         */
        removeConnector() {
            if (this.connectorSvg) {
                this.connectorSvg.remove();
                this.connectorSvg = null;
            }
            // Restore static arrow if it exists (for next time)
            if (this.infoBadge) {
                const arrow = this.infoBadge.querySelector('.mageforge-inspector-arrow');
                if (arrow) arrow.style.display = '';
            }
        },

        /**
         * Update connector position
         */
        updateConnector() {
            if (!this.connectorSvg || !this.selectedElement || !this.infoBadge) return;

            // Get badge center
            const badgeRect = this.infoBadge.getBoundingClientRect();
            const badgeX = badgeRect.left + badgeRect.width / 2;
            const badgeY = badgeRect.top + badgeRect.height / 2;

            // Get element center (using highlight box as proxy if available, or selectedElement)
            let targetRect;
            if (this.highlightBox && this.highlightBox.style.display !== 'none') {
                 targetRect = this.highlightBox.getBoundingClientRect();
            } else {
                 targetRect = this.getElementRect(this.selectedElement);
            }

            const targetX = targetRect.left + targetRect.width / 2;
            const targetY = targetRect.top + targetRect.height / 2;

            // Update line
            const line = this.connectorSvg.querySelector('line');
            line.setAttribute('x1', badgeX);
            line.setAttribute('y1', badgeY);
            line.setAttribute('x2', targetX);
            line.setAttribute('y2', targetY);

            // Update dot
            const dot = this.connectorSvg.querySelector('circle');
            dot.setAttribute('cx', targetX);
            dot.setAttribute('cy', targetY);
        },

        /**
         * Update panel with element data
         */
        updatePanelData(element) {
            const data = element._mageforgeBlockData;

            if (!data) {
                this.panelData.template = 'N/A';
                this.panelData.block = 'N/A';
                this.panelData.module = 'N/A';
                return;
            }

            this.panelData.template = data.template || 'N/A';
            this.panelData.block = data.block || 'N/A';
            this.panelData.module = data.module || 'N/A';
        },
    }));
});
