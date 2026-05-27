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
     * Walks up the DOM via closest() to find the nearest ancestor (or self)
     * that carries a data-mageforge-id attribute.
     *
     * @param {Element} element
     * @returns {{ data: Object, elements: Element[] }|null}
     */
    findBlockForElement(element) {
        const blockEl = element.closest('[data-mageforge-id]');
        if (!blockEl) return null;

        return this._parseBlockElement(blockEl);
    },
};
