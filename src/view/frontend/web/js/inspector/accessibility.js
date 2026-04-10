/**
 * MageForge Inspector - Accessibility Tab Rendering & Analysis
 */

export const accessibilityMethods = {
    /**
     * Render Accessibility tab content
     */
    renderAccessibilityTab(container, element) {
        if (!element) return;

        const a11yData = this.analyzeAccessibility(element);

        // Semantic Element
        container.appendChild(this.createInfoSection('Element Type', a11yData.tagName, '#60a5fa'));

        // ARIA Role
        if (a11yData.role) {
            container.appendChild(this.createInfoSection('ARIA Role', a11yData.role, '#a78bfa'));
        }

        // Accessible Name
        if (a11yData.accessibleName) {
            container.appendChild(this.createInfoSection('Accessible Name', a11yData.accessibleName, '#34d399'));
        }

        // ARIA Label
        if (a11yData.ariaLabel) {
            container.appendChild(this.createInfoSection('ARIA Label', a11yData.ariaLabel, '#22d3ee'));
        }

        // ARIA Described By
        if (a11yData.ariaDescribedBy) {
            container.appendChild(this.createInfoSection('ARIA Described By', a11yData.ariaDescribedBy, '#fbbf24'));
        }

        // Alt Text (for images)
        if (a11yData.altText !== null) {
            const altStatus = a11yData.altText ? a11yData.altText : '⚠️ Missing';
            const altColor = a11yData.altText ? '#34d399' : '#ef4444';
            container.appendChild(this.createInfoSection('Alt Text', altStatus, altColor));
        }

        // Lazy Loading (for images)
        if (a11yData.lazyLoading !== null) {
            const { lazyColor } = this.getLazyLoadingStyle(a11yData.lazyLoading);
            container.appendChild(this.createInfoSection('Lazy Loading', a11yData.lazyLoading, lazyColor));
        }

        // Tabindex
        if (a11yData.tabindex !== null) {
            container.appendChild(this.createInfoSection('Tab Index', a11yData.tabindex, '#fb923c'));
        }

        // Focusable State
        const focusableText = a11yData.isFocusable ? '✅ Yes' : '❌ No';
        const focusableColor = a11yData.isFocusable ? '#34d399' : '#94a3b8';
        container.appendChild(this.createInfoSection('Focusable', focusableText, focusableColor));

        // ARIA Hidden
        if (a11yData.ariaHidden) {
            container.appendChild(this.createInfoSection('ARIA Hidden', a11yData.ariaHidden, '#ef4444'));
        }

        // Interactive Element
        const interactiveText = a11yData.isInteractive ? '✅ Yes' : '❌ No';
        const interactiveColor = a11yData.isInteractive ? '#34d399' : '#94a3b8';
        container.appendChild(this.createInfoSection('Interactive', interactiveText, interactiveColor));
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
};
