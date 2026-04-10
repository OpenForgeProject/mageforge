/**
 * MageForge Inspector - Web Vitals Tracking & Performance Data Utilities
 */

export const vitalsMethods = {
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
            this.performanceObservers.push(lcpObserver);

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
            this.performanceObservers.push(clsObserver);

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
            this.performanceObservers.push(inpObserver);

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
            this.performanceObservers.push(paintObserver);

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
            this.performanceObservers.push(longTaskObserver);

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
            this.performanceObservers.push(elementTimingObserver);
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
};
