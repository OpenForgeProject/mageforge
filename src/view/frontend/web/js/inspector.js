/**
 * MageForge Inspector - Frontend Element Inspector for Magento Development
 *
 * Alpine.js component for inspecting templates, blocks, and modules.
 * Logic is split across modules under ./inspector/ and mixed into the
 * Alpine component via object spread.
 */

import { domMethods } from './inspector/dom.js';
import { uiMethods } from './inspector/ui.js';
import { pickerMethods } from './inspector/picker.js';
import { tabsMethods } from './inspector/tabs.js';
import { accessibilityMethods } from './inspector/accessibility.js';
import { performanceMethods } from './inspector/performance.js';
import { vitalsMethods } from './inspector/vitals.js';
import { draggableMethods } from './inspector/draggable.js';

// Extracted into a named function so it can be called either from the
// alpine:init event (normal case) or immediately when Alpine has already
// started (e.g. Hyvä themes where Alpine loads before this deferred script).
function _registerMageforgeInspector() {
    Alpine.data('mageforgeInspector', () => ({
        // ====================================================================
        // State
        // ====================================================================
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
        keydownHandler: null,
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
        dragStartHandler: null,
        dragHandler: null,
        dragEndHandler: null,
        connectorScrollHandler: null,

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
            fcp: null,
            elementTimings: [] // Element Timing API results
        },
        longTasks: [],
        resourceMetrics: null,
        pageTimings: null,
        performanceObservers: [],

        // ====================================================================
        // Lifecycle
        // ====================================================================

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

            // Dispatch init event for Hyvä integration
            this.$dispatch('mageforge:inspector:init');
        },

        destroy() {
            // Remove keyboard listener
            if (this.keydownHandler) {
                document.removeEventListener('keydown', this.keydownHandler);
            }

            // Disconnect all PerformanceObservers
            this.performanceObservers.forEach(observer => observer.disconnect());
            this.performanceObservers = [];

            // Close inspector and clean up active event listeners
            if (this.isOpen || this.isPinned) {
                this.closeInspector();
            }

            // Remove injected DOM elements
            if (this.highlightBox) {
                this.highlightBox.remove();
                this.highlightBox = null;
            }
            if (this.infoBadge) {
                this.infoBadge.remove();
                this.infoBadge = null;
            }
            if (this.floatingButton) {
                this.floatingButton.remove();
                this.floatingButton = null;
            }
            if (this.connectorSvg) {
                this.connectorSvg.remove();
                this.connectorSvg = null;
            }
        },

        // ====================================================================
        // Panel Data
        // ====================================================================

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

        // ====================================================================
        // Mixins
        // ====================================================================

        ...domMethods,
        ...uiMethods,
        ...pickerMethods,
        ...tabsMethods,
        ...accessibilityMethods,
        ...performanceMethods,
        ...vitalsMethods,
        ...draggableMethods,
    }));
}

// If Alpine has already initialised (e.g. it was loaded by Hyvä before this
// deferred script executed), register the component straight away and
// re-initialise any [x-data="mageforgeInspector"] elements that Alpine skipped
// because the component was not yet registered at that point.
// Otherwise, register on alpine:init which fires before Alpine processes the DOM.
if (typeof Alpine !== 'undefined') {
    _registerMageforgeInspector();
    document.querySelectorAll('[x-data="mageforgeInspector"]').forEach(function (el) {
        if (typeof Alpine.initTree === 'function') {
            Alpine.initTree(el);
        }
    });
} else {
    document.addEventListener('alpine:init', _registerMageforgeInspector);
}
