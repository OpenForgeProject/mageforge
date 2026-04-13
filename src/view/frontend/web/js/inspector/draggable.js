/**
 * MageForge Inspector - Draggable Badge & SVG Connector
 */

export const draggableMethods = {
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
        this.dragBlurHandler = () => this.handleDragEnd();

        this.infoBadge.addEventListener('mousedown', this.dragStartHandler);
        window.addEventListener('blur', this.dragBlurHandler);
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
        window.removeEventListener('blur', this.dragBlurHandler);

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
        this.initialBadgeX = rect.left + window.scrollX;
        this.initialBadgeY = rect.top + window.scrollY;

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

        let newX = this.initialBadgeX + deltaX;
        let newY = this.initialBadgeY + deltaY;

        // Clamp within viewport so the badge cannot be dragged out of reach
        const margin = 10;
        const badgeWidth = this.infoBadge.offsetWidth;
        const badgeHeight = this.infoBadge.offsetHeight;
        newX = Math.max(window.scrollX + margin, Math.min(newX, window.scrollX + window.innerWidth - badgeWidth - margin));
        newY = Math.max(window.scrollY + margin, Math.min(newY, window.scrollY + window.innerHeight - badgeHeight - margin));

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

        // Update line on scroll (SVG is fixed, coordinates are viewport-relative)
        this.connectorScrollHandler = () => this.updateConnector();
        window.addEventListener('scroll', this.connectorScrollHandler, { passive: true });

        this.updateConnector();
    },

    /**
     * Remove connector
     */
    removeConnector() {
        if (this.connectorScrollHandler) {
            window.removeEventListener('scroll', this.connectorScrollHandler);
            this.connectorScrollHandler = null;
        }
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
};
