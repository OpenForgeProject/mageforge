/**
 * MageForge Inspector - Performance & Cache Tab + Web Vitals Tab Rendering
 */

export const performanceMethods = {
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
        const cacheableLabel = cacheData.pageCacheable === false ? 'Block Cacheable (ignored)' : 'Block Cacheable';
        container.appendChild(this.createInfoSection(cacheableLabel, cacheableText, cacheableColor));

        // Cache lifetime (show for all cacheable blocks)
        if (cacheData.cacheable) {
            const lifetimeText = (cacheData.lifetime === null || cacheData.lifetime === 0)
                ? 'Unlimited'
                : `${cacheData.lifetime}s`;
            container.appendChild(this.createInfoSection('Cache Lifetime', lifetimeText, '#60a5fa'));
        }

        // Cache key
        if (cacheData.key && cacheData.key !== '') {
            container.appendChild(this.createInfoSection('Cache Key', cacheData.key, '#a78bfa'));
        }

        // Cache tags
        if (cacheData.tags && cacheData.tags.length > 0) {
            const tagsText = cacheData.tags.join(', ');
            container.appendChild(this.createInfoSection('Cache Tags', tagsText, '#22d3ee'));
        }
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
            container.appendChild(this.createInfoSection('PHP Render Time', formattedTime, color));

            const desc = document.createElement('div');
            desc.style.fontSize = '10px';
            desc.style.color = '#94a3b8';
            desc.style.marginTop = '-8px';
            desc.style.marginBottom = '12px';
            desc.textContent = 'Server-side processing time for this block';
            container.appendChild(desc);

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
                    this.createInfoSection('LCP (Largest Contentful Paint)', `${lcpValue} ms`, lcpColor)
                );
                container.appendChild(
                    this.createInfoSection('LCP Element', '✅ This element is critical for LCP!', '#ef4444')
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
                this.createInfoSection('CLS (Layout Shift)', elementCLS.toFixed(3), clsColor)
            );
            const stabilityScore = Math.max(0, (1 - elementCLS * 4)).toFixed(2);
            const stabilityColor = stabilityScore > 0.75 ? '#34d399' : (stabilityScore > 0.5 ? '#f59e0b' : '#ef4444');
            container.appendChild(
                this.createInfoSection('Layout Stability Score', stabilityScore, stabilityColor)
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
                this.createInfoSection('INP (Interaction)', `${inpValue} ms`, inpColor)
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
                this.createInfoSection('Element Timing', `${timingValue} ms (${elementTiming.identifier})`, timingColor)
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
                this.createInfoSection('Modern Image Formats', `${modernScore}% (${imageAnalysis.modernFormats}/${imageAnalysis.totalImages})`, modernColor)
            );

            const responsiveScore = imageAnalysis.totalImages > 0
                ? (imageAnalysis.hasResponsive / imageAnalysis.totalImages * 100).toFixed(0)
                : 0;
            const responsiveColor = responsiveScore > 75 ? '#34d399' : (responsiveScore > 25 ? '#f59e0b' : '#ef4444');
            const responsiveText = `${imageAnalysis.hasResponsive} of ${imageAnalysis.totalImages} ${imageAnalysis.totalImages === 1 ? 'image uses' : 'images use'} srcset`;
            container.appendChild(
                this.createInfoSection('Adaptive Images (srcset)', responsiveText, responsiveColor)
            );

            if (imageAnalysis.oversized > 0) {
                container.appendChild(
                    this.createInfoSection('Oversized Images', `${imageAnalysis.oversized} oversized`, '#ef4444')
                );
            }

            if (imageAnalysis.issues.length > 0) {
                const issuesText = imageAnalysis.issues.slice(0, 3).join(' • ');
                const moreText = imageAnalysis.issues.length > 3 ? ` (+${imageAnalysis.issues.length - 3} more)` : '';
                container.appendChild(
                    this.createInfoSection('Optimization Tips', issuesText + moreText, '#f59e0b')
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
     * Render resource metrics for specific element
     *
     * @param {HTMLElement} container
     * @param {object} resourceData
     */
    renderElementResourceMetrics(container, resourceData) {
        const sizeText = this.formatResourceSize(resourceData.size);
        const resourceLabel = this.determineResourceLabel(resourceData);

        container.appendChild(
            this.createInfoSection('Element Resources', `${resourceData.count} ${resourceLabel} (${sizeText})`, '#60a5fa')
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

    /**
     * Get stats about active resource types
     */
    getResourceTypeStats(resourceData) {
        const definitions = [
            { key: 'img', label: 'Image', plural: 'Images' },
            { key: 'script', label: 'Script', plural: 'Scripts' },
            { key: 'css', label: 'Stylesheet', plural: 'Stylesheets' },
            { key: 'font', label: 'Font', plural: 'Fonts' },
            { key: 'other', label: 'Resource', plural: 'Resources' }
        ];

        const activeTypes = definitions
            .map(def => ({ ...def, count: resourceData.byType[def.key] }))
            .filter(item => item.count > 0);

        return {
            activeTypes,
            typeCount: activeTypes.length
        };
    },

    determineResourceLabel(resourceData) {
        const { activeTypes, typeCount } = this.getResourceTypeStats(resourceData);

        if (typeCount === 1) {
            const type = activeTypes[0];
            return type.count === 1 ? type.label : type.plural;
        }
        return resourceData.count === 1 ? 'Resource' : 'Resources';
    },

    renderResourceBreakdown(container, resourceData) {
        const { activeTypes, typeCount } = this.getResourceTypeStats(resourceData);

        if (typeCount > 1) {
            const typesText = activeTypes
                .map(t => `${t.plural}: ${t.count}`)
                .join(', ');

            container.appendChild(
                this.createInfoSection('Resource Types', typesText, '#a78bfa')
            );
        }
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
};
