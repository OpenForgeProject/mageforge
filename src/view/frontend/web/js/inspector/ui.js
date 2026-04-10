/**
 * MageForge Inspector - UI Element Creation & Badge Positioning
 */

export const uiMethods = {
    /**
     * Create highlight overlay box
     */
    createHighlightBox() {
        this.highlightBox = document.createElement('div');
        this.highlightBox.className = 'mageforge-inspector mageforge-inspector-highlight';

        // Propagate theme from root element to injected body element
        if (this.$el && this.$el.hasAttribute('data-theme')) {
            this.highlightBox.setAttribute('data-theme', this.$el.getAttribute('data-theme'));
        }

        this.highlightBox.style.display = 'none';

        document.body.appendChild(this.highlightBox);
    },

    /**
     * Create info badge overlay
     */
    createInfoBadge() {
        this.infoBadge = document.createElement('div');
        this.infoBadge.className = 'mageforge-inspector mageforge-inspector-info-badge';

        // Propagate theme from root element to injected body element
        if (this.$el && this.$el.hasAttribute('data-theme')) {
            this.infoBadge.setAttribute('data-theme', this.$el.getAttribute('data-theme'));
        }

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

        // Propagate theme from root element to injected body element
        if (this.$el && this.$el.hasAttribute('data-theme')) {
            this.floatingButton.setAttribute('data-theme', this.$el.getAttribute('data-theme'));
        }

        this.floatingButton.type = 'button';
        this.floatingButton.title = 'Activate Inspector (Ctrl+Shift+I)';
        this.floatingButton.innerHTML = `
            <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor" height="20" width="20">
                <g stroke-width="0"></g>
                <g stroke-linecap="round" stroke-linejoin="round"></g>
                <g>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1 3l1-1h12l1 1v6h-1V3H2v8h5v1H2l-1-1V3zm14.707 9.707L9 6v9.414l2.707-2.707h4zM10 13V8.414l3.293 3.293h-2L10 13z"></path>
                </g>
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
            this.floatingButton.classList.add('mageforge-active');
        } else {
            this.floatingButton.classList.remove('mageforge-active');
        }
    },

    /**
     * Show highlight overlay on element
     */
    showHighlight(element) {
        // If element has display:contents, use first child for dimensions
        let targetElement = element;
        const style = window.getComputedStyle(element);

        if (style.display === 'contents' && element.children.length > 0) {
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
};
