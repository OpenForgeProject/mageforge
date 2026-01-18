/**
 * MageForge Inspector - Frontend Element Inspector for Magento Development
 *
 * Alpine.js component for inspecting templates, blocks, and modules
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('mageforgeInspector', () => ({
        isOpen: false,
        isPickerActive: false,
        hoveredElement: null,
        selectedElement: null,
        highlightBox: null,
        infoBadge: null,
        floatingButton: null,
        mouseMoveHandler: null,
        clickHandler: null,
        lastBadgeUpdate: 0,
        badgeUpdateDelay: 150, // ms delay to prevent flickering
        panelData: {
            template: '',
            block: '',
            module: '',
        },

        init() {
            // Bind event handlers to preserve context
            this.mouseMoveHandler = (e) => this.handleMouseMove(e);
            this.clickHandler = (e) => this.handleClick(e);

            this.setupKeyboardShortcuts();
            this.createHighlightBox();
            this.createInfoBadge();
            this.createFloatingButton();

            // Dispatch init event for Hyvä integration
            this.$dispatch('mageforge:inspector:init');
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
            this.deactivatePicker();
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
            document.removeEventListener('mousemove', this.mouseMoveHandler);
            document.removeEventListener('click', this.clickHandler, false);
            document.body.style.cursor = '';
            this.hideHighlight();
            this.hoveredElement = null;
            this.lastBadgeUpdate = 0;
        },

        /**
         * Handle mouse move over elements
         */
        handleMouseMove(e) {
            if (!this.isPickerActive) return;

            // Don't update if mouse is over the info badge or floating button
            if ((this.infoBadge && this.infoBadge.contains(e.target)) ||
                (this.floatingButton && this.floatingButton.contains(e.target))) {
                return;
            }

            const element = this.findInspectableElement(e.target);

            if (element && element !== this.hoveredElement) {
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
            if (!this.isPickerActive) return;

            // Don't handle clicks on the info badge
            if (this.infoBadge && (this.infoBadge.contains(e.target) || this.infoBadge === e.target)) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const element = this.findInspectableElement(e.target);

            if (element) {
                this.selectedElement = element;
                this.updatePanelData(element);
                // Keep panel open but stop picking
                this.deactivatePicker();
            }
        },

        /**
         * Find nearest inspectable element with data attributes
         */
        findInspectableElement(target) {
            let element = target;
            let maxDepth = 10;

            while (element && maxDepth > 0) {
                if (element.hasAttribute && element.hasAttribute('data-mageforge-template')) {
                    return element;
                }
                element = element.parentElement;
                maxDepth--;
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
                this.buildBadgeContent(element, rect);
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
        buildBadgeContent(element, rect) {
            const data = {
                template: element.getAttribute('data-mageforge-template') || '',
                blockClass: element.getAttribute('data-mageforge-block') || '',
                module: element.getAttribute('data-mageforge-module') || '',
                viewModel: element.getAttribute('data-mageforge-viewmodel') || '',
                parentBlock: element.getAttribute('data-mageforge-parent') || '',
                blockAlias: element.getAttribute('data-mageforge-alias') || '',
                isOverride: element.getAttribute('data-mageforge-override') === '1'
            };

            // Clear badge
            this.infoBadge.innerHTML = '';

            // Template section with override indicator
            const templateDisplay = data.isOverride ? '🔧 ' + data.template : data.template;
            this.infoBadge.appendChild(this.createInfoSection('📄 Template', templateDisplay, '#60a5fa'));

            // Block section
            this.infoBadge.appendChild(this.createInfoSection('📦 Block', data.blockClass, '#a78bfa'));

            // Optional sections
            if (data.blockAlias) {
                this.infoBadge.appendChild(this.createInfoSection('🏷️ Block Name', data.blockAlias, '#34d399'));
            }
            if (data.parentBlock) {
                this.infoBadge.appendChild(this.createInfoSection('⬆️ Parent Block', data.parentBlock, '#fb923c'));
            }
            if (data.viewModel) {
                this.infoBadge.appendChild(this.createInfoSection('⚡ ViewModel', data.viewModel, '#22d3ee'));
            }

            // Module section
            this.infoBadge.appendChild(this.createInfoSection('📍 Module', data.module, '#fbbf24'));

            // Dimensions section
            const dimensionsDiv = this.createInfoSection('📐 Dimensions', `${Math.round(rect.width)} × ${Math.round(rect.height)} px`, '#6b7280');
            dimensionsDiv.style.borderTop = '1px solid rgba(148, 163, 184, 0.12)';
            dimensionsDiv.style.paddingTop = '12px';
            dimensionsDiv.style.marginTop = '12px';
            this.infoBadge.appendChild(dimensionsDiv);

            // Branding footer
            this.infoBadge.appendChild(this.createBrandingFooter());
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
            brandingDiv.innerHTML = 'Created with <span style="color: #ff6b6b; font-size: 12px;">🧡</span> by <span style="color: #60a5fa; font-weight: 600;">MageForge</span>';
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
            this.panelData.template = element.getAttribute('data-mageforge-template') || 'N/A';
            this.panelData.block = element.getAttribute('data-mageforge-block') || 'N/A';
            this.panelData.module = element.getAttribute('data-mageforge-module') || 'N/A';
        },
    }));
});
