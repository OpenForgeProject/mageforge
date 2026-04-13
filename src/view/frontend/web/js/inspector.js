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
import { blockDataMap } from './inspector/blockData.js';

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

        // Block detection cache
        cachedBlocks: null,
        lastBlocksCacheTime: 0,

        // Window event handler refs (for cleanup)
        _inspectorStateHandler: null,

        // ====================================================================
        // Lifecycle
        // ====================================================================

        init() {
            // Bind event handlers to preserve context
            this.mouseMoveHandler = (e) => this.handleMouseMove(e);
            this.clickHandler = (e) => this.handleClick(e);

            this.setupKeyboardShortcuts();
            this.createHighlightBox();
            this.createInfoBadge();
            this.initWebVitalsTracking();

            // Defer page timings until the load event so that loadEventEnd and
            // domContentLoadedEventEnd are actually populated by the browser.
            if (document.readyState === 'complete') {
                setTimeout(() => this.cachePageTimings(), 0);
            } else {
                window.addEventListener('load', () => setTimeout(() => this.cachePageTimings(), 0), { once: true });
            }

            // Listen for inspector-state sync from toolbar
            this._inspectorStateHandler = (e) => {
                if (this._inspectorFloatButton) {
                    this._inspectorFloatButton.classList.toggle('mageforge-active', e.detail.active);
                }
            };
            window.addEventListener('mageforge:toolbar:inspector-state', this._inspectorStateHandler);

            // Append inspector button to toolbar container.
            // The toolbar initialises before the inspector, but guard with a
            // MutationObserver fallback for edge cases where it hasn't rendered yet.
            this._appendInspectorButton();

            // Dispatch init event for Hyvä integration
            this.$dispatch('mageforge:inspector:init');
        },

        _createInspectorFloatButton() {
            const btn = document.createElement('button');
            btn.className = 'mageforge-inspector-float-button';
            btn.type = 'button';
            btn.title = 'Activate Inspector (Ctrl+Shift+I)';
            btn.innerHTML = `
                <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor" height="20" width="20">
                    <g stroke-width="0"></g>
                    <g stroke-linecap="round" stroke-linejoin="round"></g>
                    <g>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M1 3l1-1h12l1 1v6h-1V3H2v8h5v1H2l-1-1V3zm14.707 9.707L9 6v9.414l2.707-2.707h4zM10 13V8.414l3.293 3.293h-2L10 13z"></path>
                    </g>
                </svg>
                <span>Inspector</span>
            `;
            btn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleInspector();
            };
            return btn;
        },

        _appendInspectorButton() {
            const _attach = (container) => {
                container.querySelector('.mageforge-inspector-float-button')?.remove();
                this._inspectorFloatButton = this._createInspectorFloatButton();
                container.appendChild(this._inspectorFloatButton);
            };

            const toolbarContainer = document.querySelector('.mageforge-toolbar');
            if (toolbarContainer) {
                _attach(toolbarContainer);
                return;
            }

            // Toolbar not in DOM yet – wait for it
            const observer = new MutationObserver(() => {
                const container = document.querySelector('.mageforge-toolbar');
                if (container) {
                    observer.disconnect();
                    _attach(container);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
            this._buttonObserver = observer;
        },

        destroy() {
            // Remove window event listeners
            if (this._inspectorStateHandler) {
                window.removeEventListener('mageforge:toolbar:inspector-state', this._inspectorStateHandler);
                this._inspectorStateHandler = null;
            }

            // Disconnect button injection observer if still running
            if (this._buttonObserver) {
                this._buttonObserver.disconnect();
                this._buttonObserver = null;
            }

            // Clear pending hover timeout
            if (this.hoverTimeout) {
                clearTimeout(this.hoverTimeout);
                this.hoverTimeout = null;
            }

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
            if (this.connectorSvg) {
                this.connectorSvg.remove();
                this.connectorSvg = null;
            }
            if (this._inspectorFloatButton) {
                this._inspectorFloatButton.remove();
                this._inspectorFloatButton = null;
            }
        },

        // ====================================================================
        // Panel Data
        // ====================================================================

        updatePanelData(element) {
            const data = blockDataMap.get(element);

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
