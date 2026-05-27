/**
 * MageForge Inspector - DOM Traversal & Block Detection
 */

export const domMethods = {
    /**
     * Find all MageForge block elements in DOM.
     *
     * Blocks are identified by the data-mageforge-id attribute injected by
     * InspectorHints on the first root HTML element of each rendered block.
     *
     * @returns {Array<{ data: Object, elements: Element[] }>}
     */
    findAllMageForgeBlocks() {
        const blocks = [];
        const elements = document.querySelectorAll('[data-mageforge-id]');
        for (const el of elements) {
            const block = this._parseBlockElement(el);
            if (block) {
                blocks.push(block);
            }
        }

        return blocks;
    },

    /**
     * Parse block metadata from an element's data-mageforge-block attribute.
     *
     * @param {Element} el
     * @returns {{ data: Object, elements: Element[] }|null}
     */
    _parseBlockElement(el) {
        const blockJson = el.getAttribute('data-mageforge-block');
        if (!blockJson) return null;

        try {
            const data = JSON.parse(blockJson);
            data.id = el.getAttribute('data-mageforge-id');
            return {
                data,
                elements: [el, ...el.querySelectorAll('*')],
            };
        } catch (e) {
            console.error('Failed to parse MageForge block data:', e);
            return null;
        }
    },

    /**
     * Find the MageForge block that contains a given element.
     *
     * Primary: walks up via closest() for the nearest [data-mageforge-id] ancestor.
     * Fallback: for PageBuilder content with multiple root elements (rows), only the
     * first root gets data-mageforge-id injected. Walk up to the root [data-content-type]
     * element and search siblings for the nearest [data-mageforge-id].
     *
     * @param {Element} element
     * @returns {{ data: Object, elements: Element[] }|null}
     */
    findBlockForElement(element) {
        const blockEl = element.closest('[data-mageforge-id]');
        if (blockEl) return this._parseBlockElement(blockEl);

        // PageBuilder fallback: multi-root CMS blocks (e.g. multiple rows)
        const rootPb = this._findRootPageBuilderElement(element);
        if (rootPb) {
            const sibling = this._findNearestMageForgeBlock(rootPb);
            if (sibling) return this._parseBlockElement(sibling);
        }

        return null;
    },

    /**
     * Walk up the DOM to find the topmost [data-content-type] element (PageBuilder root).
     *
     * @param {Element} element
     * @returns {Element|null}
     */
    _findRootPageBuilderElement(element) {
        let current = element;
        let rootPb = null;
        while (current && current !== document.body) {
            if (current.hasAttribute('data-content-type')) {
                rootPb = current;
            }
            current = current.parentElement;
        }
        return rootPb;
    },

    /**
     * Search preceding and following siblings for the nearest [data-mageforge-id] element.
     *
     * @param {Element} element
     * @returns {Element|null}
     */
    _findNearestMageForgeBlock(element) {
        let sibling = element.previousElementSibling;
        while (sibling) {
            if (sibling.hasAttribute('data-mageforge-id')) return sibling;
            sibling = sibling.previousElementSibling;
        }
        sibling = element.nextElementSibling;
        while (sibling) {
            if (sibling.hasAttribute('data-mageforge-id')) return sibling;
            sibling = sibling.nextElementSibling;
        }
        return null;
    },
};
