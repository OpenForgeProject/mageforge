/**
 * MageForge Inspector - Keyboard Shortcuts, Inspector Toggle & Element Picker
 */

export const pickerMethods = {
    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        this.keydownHandler = (e) => {
            // Ctrl+Shift+I or Cmd+Option+I
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                this.toggleInspector();
            }

            // ESC to close
            if (e.key === 'Escape' && this.isOpen) {
                this.closeInspector();
            }
        };
        document.addEventListener('keydown', this.keydownHandler);
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
                const now = Date.now();
                this.hoveredElement = element;
                this.showHighlight(element);
                this.updatePanelData(element);
                this.showInfoBadge(element);
                // Only update the throttle timestamp when enough time has passed
                if (now - this.lastBadgeUpdate >= this.badgeUpdateDelay) {
                    this.lastBadgeUpdate = now;
                }
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
            if (!this.infoBadge.contains(e.target) && (!this.floatingButton || !this.floatingButton.contains(e.target))) {
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
};
