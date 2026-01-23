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

            // Dispatch init event for Hyvä integration
            this.$dispatch('mageforge:inspector:init');
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

            while (comment = walker.nextNode()) {
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
            this.highlightBox.className = 'mageforge-inspector mf-inspector-highlight';

            // Add inline styles to ensure it's visible
            this.highlightBox.style.cssText = `
                position: absolute;
                background: rgba(59, 130, 246, 0.2);
                border: 2px solid rgb(59, 130, 246);
                pointer-events: none;
                z-index: 9999999;
                display: none;
                box-sizing: border-box;
            `;

            document.body.appendChild(this.highlightBox);
        },

        /**
         * Create info badge overlay
         */
        createInfoBadge() {
            this.infoBadge = document.createElement('div');
            this.infoBadge.className = 'mageforge-inspector mf-inspector-info-badge';

            // Modern Tailwind-style design
            this.infoBadge.style.cssText = `
                position: absolute;
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.98) 0%, rgba(30, 41, 59, 0.98) 100%);
                backdrop-filter: blur(12px);
                color: white;
                padding: 16px;
                border-radius: 12px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
                font-size: 11px;
                line-height: 1.6;
                pointer-events: auto;
                z-index: 10000000;
                display: none;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.05);
                min-width: 320px;
                max-width: 520px;
                word-wrap: break-word;
                border: 1px solid rgba(148, 163, 184, 0.15);
            `;

            // Create arrow element with modern styling
            const arrow = document.createElement('div');
            arrow.className = 'mageforge-inspector-arrow';
            arrow.style.cssText = `
                position: absolute;
                top: -8px;
                left: 24px;
                width: 0;
                height: 0;
                border-left: 8px solid transparent;
                border-right: 8px solid transparent;
                border-bottom: 8px solid rgba(15, 23, 42, 0.98);
                pointer-events: none;
                filter: drop-shadow(0 -2px 4px rgba(0,0,0,0.2));
            `;
            this.infoBadge.appendChild(arrow);

            document.body.appendChild(this.infoBadge);
        },

        /**
         * Create floating button for inspector activation
         */
        createFloatingButton() {
            this.floatingButton = document.createElement('button');
            this.floatingButton.className = 'mageforge-inspector mf-inspector-float-button';
            this.floatingButton.type = 'button';
            this.floatingButton.title = 'Activate Inspector (Ctrl+Shift+I)';

            // Generate unique ID for SVG gradient to avoid collisions
            const gradientId = 'mf-gradient-' + Math.random().toString(36).substr(2, 9);

            // Modern floating button design
            this.floatingButton.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                height: 36px;
                padding: 0 14px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                z-index: 9999998;
                display: flex;
                align-items: center;
                gap: 8px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                font-size: 12px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2);
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: auto;
                backdrop-filter: blur(8px);
                letter-spacing: 0.025em;
            `;

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

            // Hover effect
            this.floatingButton.onmouseenter = () => {
                this.floatingButton.style.transform = 'translateY(-2px)';
                this.floatingButton.style.boxShadow = '0 8px 20px rgba(59, 130, 246, 0.5), 0 4px 8px rgba(0, 0, 0, 0.3)';
            };

            this.floatingButton.onmouseleave = () => {
                if (!this.isOpen) {
                    this.floatingButton.style.transform = 'translateY(0)';
                    this.floatingButton.style.boxShadow = '0 4px 12px rgba(59, 130, 246, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2)';
                }
            };

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
                this.floatingButton.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                this.floatingButton.style.transform = 'translateY(-2px)';
                this.floatingButton.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.2), 0 8px 20px rgba(16, 185, 129, 0.5)';
            } else {
                // Inactive state
                this.floatingButton.style.background = 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)';
                this.floatingButton.style.transform = 'translateY(0)';
                this.floatingButton.style.boxShadow = '0 4px 12px rgba(59, 130, 246, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2)';
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
        },

        /**
         * Unpin and close the badge
         */
        unpinBadge() {
            this.isPinned = false;
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
            closeBtn.style.cssText = `
                position: absolute;
                top: 12px;
                right: 12px;
                width: 28px;
                height: 28px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 6px;
                color: #94a3b8;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
                z-index: 10;
                font-family: inherit;
                line-height: 1;
                padding: 0;
            `;

            closeBtn.onmouseenter = () => {
                closeBtn.style.background = 'rgba(239, 68, 68, 0.15)';
                closeBtn.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                closeBtn.style.color = '#ef4444';
                closeBtn.style.transform = 'scale(1.05)';
            };

            closeBtn.onmouseleave = () => {
                closeBtn.style.background = 'rgba(255, 255, 255, 0.05)';
                closeBtn.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                closeBtn.style.color = '#94a3b8';
                closeBtn.style.transform = 'scale(1)';
            };

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
            tabContainer.style.cssText = 'margin-bottom: 16px;';

            // Tab header
            const tabHeader = document.createElement('div');
            tabHeader.style.cssText = `
                display: flex;
                gap: 4px;
                margin-bottom: 16px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.15);
            `;

            // Define tabs
            const tabs = [
                { id: 'structure', label: 'Structure', icon: '🏰' },
                { id: 'accessibility', label: 'Accessibility', icon: '♿' },
                { id: 'coming-soon', label: 'Coming Soon', icon: '🚀' }
                // Future tabs can be added here:
                // { id: 'performance', label: 'Performance', icon: '⚡' },
                // { id: 'seo', label: 'SEO', icon: '🔍' },
            ];

            // Tab content container
            const tabContentContainer = document.createElement('div');

            // Create tab buttons
            tabs.forEach(tab => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = tab.label;
                button.style.cssText = `
                    padding: 8px 12px;
                    background: ${this.activeTab === tab.id ? 'rgba(59, 130, 246, 0.15)' : 'transparent'};
                    color: ${this.activeTab === tab.id ? '#60a5fa' : '#94a3b8'};
                    border: none;
                    border-bottom: 2px solid ${this.activeTab === tab.id ? '#60a5fa' : 'transparent'};
                    cursor: pointer;
                    font-size: 11px;
                    font-weight: 600;
                    letter-spacing: 0.025em;
                    transition: all 0.2s ease;
                    border-radius: 6px 6px 0 0;
                    font-family: inherit;
                `;

                button.onmouseenter = () => {
                    if (this.activeTab !== tab.id) {
                        button.style.background = 'rgba(255, 255, 255, 0.05)';
                        button.style.color = '#cbd5e1';
                    }
                };

                button.onmouseleave = () => {
                    if (this.activeTab !== tab.id) {
                        button.style.background = 'transparent';
                        button.style.color = '#94a3b8';
                    }
                };

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
            } else if (tabId === 'coming-soon') {
                this.renderComingSoonTab(container);
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
            inheritanceNote.style.cssText = `
                background: rgba(251, 191, 36, 0.1);
                border: 1px solid rgba(251, 191, 36, 0.3);
                border-radius: 8px;
                padding: 10px 12px;
                margin-bottom: 16px;
                font-size: 11px;
                color: #fbbf24;
                display: flex;
                align-items: center;
                gap: 8px;
            `;
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
            noDataDiv.style.cssText = `
                text-align: center;
                padding: 24px 16px;
                color: #94a3b8;
                font-size: 12px;
                line-height: 1.6;
            `;
            noDataDiv.innerHTML = `
                <div style="font-size: 24px; margin-bottom: 12px;">📋</div>
                <div style="color: #cbd5e1; font-weight: 600; margin-bottom: 8px;">No Template Data</div>
                <div style="color: #94a3b8;">This element is not inside a Magento template block</div>
                <div style="color: #64748b; font-size: 11px; margin-top: 8px;">Element: <code style="background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;">&lt;${element.tagName.toLowerCase()}&gt;</code></div>
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
         * Render Coming Soon tab content
         */
        renderComingSoonTab(container) {
            // Coming Soon content
            const comingSoonDiv = document.createElement('div');
            comingSoonDiv.style.cssText = `
                text-align: center;
                padding: 24px 16px;
                color: #94a3b8;
                font-size: 12px;
                line-height: 1.6;
            `;
            comingSoonDiv.innerHTML = `
                <div style="font-size: 32px; margin-bottom: 12px;">🚀</div>
                <div style="color: #cbd5e1; font-weight: 600; margin-bottom: 8px;">Coming Soon</div>
                <div style="color: #94a3b8; margin-bottom: 16px;">MageForge is currently building something wonderful for you.</div>
            `;

            // Feature Request Button
            const featureButton = document.createElement('a');
            featureButton.href = 'https://github.com/OpenForgeProject/mageforge/issues/new?template=feature_request.md';
            featureButton.target = '_blank';
            featureButton.rel = 'noopener noreferrer';
            featureButton.style.cssText = `
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 600;
                transition: all 0.2s ease;
                cursor: pointer;
                border: 1px solid rgba(59, 130, 246, 0.3);
            `;
            featureButton.innerHTML = `
                <span style="font-size: 14px;">💡</span>
                <span>Request a Feature</span>
            `;

            featureButton.onmouseenter = () => {
                featureButton.style.transform = 'translateY(-2px)';
                featureButton.style.boxShadow = '0 8px 16px rgba(59, 130, 246, 0.4)';
            };

            featureButton.onmouseleave = () => {
                featureButton.style.transform = 'translateY(0)';
                featureButton.style.boxShadow = 'none';
            };

            comingSoonDiv.appendChild(featureButton);
            container.appendChild(comingSoonDiv);
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
         * Create branding footer
         */
        createBrandingFooter() {
            const brandingDiv = document.createElement('div');
            brandingDiv.style.cssText = `
                margin-top: 16px;
                padding-top: 12px;
                border-top: 1px solid rgba(148, 163, 184, 0.12);
                text-align: center;
                font-size: 10px;
                color: #94a3b8;
                font-weight: 500;
                letter-spacing: 0.025em;
            `;
            brandingDiv.innerHTML = 'Made with <span style="color: #ff6b6b; font-size: 12px;">🧡</span> by <span style="color: #60a5fa; font-weight: 600;">MageForge</span>';
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
            container.style.cssText = 'margin-bottom: 12px;';

            const titleDiv = document.createElement('div');
            titleDiv.style.cssText = `color: ${titleColor}; font-weight: 600; margin-bottom: 6px; font-size: 11px; letter-spacing: 0.025em; text-transform: uppercase; opacity: 0.9;`;
            titleDiv.textContent = title;

            const textSpan = document.createElement('span');
            textSpan.style.cssText = `
                color: #f1f5f9;
                font-size: 12px;
                word-break: break-all;
                cursor: pointer;
                display: inline-block;
                transition: all 0.2s ease;
                padding: 6px 10px;
                background: rgba(255, 255, 255, 0.03);
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.08);
                width: 100%;
                box-sizing: border-box;
                font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
                pointer-events: auto;
            `;
            textSpan.textContent = text;
            textSpan.title = 'Click to copy';

            const originalText = text;

            // Hover effect
            textSpan.onmouseenter = () => {
                textSpan.style.background = 'rgba(255, 255, 255, 0.06)';
                textSpan.style.borderColor = 'rgba(255, 255, 255, 0.15)';
                textSpan.style.transform = 'translateY(-1px)';
            };

            textSpan.onmouseleave = () => {
                if (textSpan.textContent !== 'copied!') {
                    textSpan.style.background = 'rgba(255, 255, 255, 0.03)';
                    textSpan.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                    textSpan.style.transform = 'translateY(0)';
                }
            };

            // Click to copy
            textSpan.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Try to copy
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(originalText).then(() => {
                        textSpan.textContent = 'copied!';
                        textSpan.style.color = '#10b981';
                        textSpan.style.background = 'rgba(16, 185, 129, 0.1)';
                        textSpan.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                        textSpan.style.fontWeight = '600';
                        setTimeout(() => {
                            textSpan.textContent = originalText;
                            textSpan.style.color = '#f1f5f9';
                            textSpan.style.background = 'rgba(255, 255, 255, 0.03)';
                            textSpan.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                            textSpan.style.fontWeight = 'normal';
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
                    element.style.color = '#10b981';
                    element.style.background = 'rgba(16, 185, 129, 0.1)';
                    element.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                    element.style.fontWeight = '600';
                    setTimeout(() => {
                        element.textContent = originalText;
                        element.style.color = '#f1f5f9';
                        element.style.background = 'rgba(255, 255, 255, 0.03)';
                        element.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                        element.style.fontWeight = 'normal';
                    }, 1500);
                } else {
                    throw new Error('Copy failed');
                }
            } catch (err) {
                element.textContent = 'failed';
                element.style.color = '#ef4444';
                element.style.background = 'rgba(239, 68, 68, 0.1)';
                element.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                setTimeout(() => {
                    element.textContent = originalText;
                    element.style.color = '#f1f5f9';
                    element.style.background = 'rgba(255, 255, 255, 0.03)';
                    element.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                }, 1500);
            }

            document.body.removeChild(textarea);
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
