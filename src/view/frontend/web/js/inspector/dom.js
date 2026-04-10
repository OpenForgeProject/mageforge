/**
 * MageForge Inspector - DOM Traversal & Block Detection
 */

export const domMethods = {
    /**
     * Parse MageForge comment markers in DOM
     */
    parseCommentMarker(comment) {
        const text = comment.textContent.trim();

        // Check if it's a start marker
        if (text.startsWith('MAGEFORGE_START ')) {
            const jsonStr = text.substring('MAGEFORGE_START '.length);
            try {
                // Unescape any escaped comment terminators
                const unescapedJson = jsonStr.replace(/--&gt;/g, '-->');
                return {
                    type: 'start',
                    data: JSON.parse(unescapedJson)
                };
            } catch (e) {
                console.error('Failed to parse MageForge start marker:', e);
                return null;
            }
        }

        // Check if it's an end marker
        if (text.startsWith('MAGEFORGE_END ')) {
            const id = text.substring('MAGEFORGE_END '.length).trim();
            return {
                type: 'end',
                id: id
            };
        }

        return null;
    },

    /**
     * Find all MageForge block regions in DOM
     */
    findAllMageForgeBlocks() {
        const blocks = [];
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_COMMENT,
            null
        );

        const stack = [];
        let comment;

        while ((comment = walker.nextNode())) {
            const parsed = this.parseCommentMarker(comment);

            if (!parsed) continue;

            if (parsed.type === 'start') {
                stack.push({
                    startComment: comment,
                    data: parsed.data,
                    elements: []
                });
            } else if (parsed.type === 'end' && stack.length > 0) {
                const currentBlock = stack[stack.length - 1];
                if (currentBlock.data.id === parsed.id) {
                    currentBlock.endComment = comment;

                    // Collect all elements between start and end comments
                    currentBlock.elements = this.getElementsBetweenComments(
                        currentBlock.startComment,
                        currentBlock.endComment
                    );

                    blocks.push(currentBlock);
                    stack.pop();
                }
            }
        }

        return blocks;
    },

    /**
     * Get all elements between two comment nodes
     */
    getElementsBetweenComments(startComment, endComment) {
        const elements = [];
        let node = startComment.nextSibling;

        while (node && node !== endComment) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                elements.push(node);
                // Also add all descendants
                elements.push(...node.querySelectorAll('*'));
            }
            node = node.nextSibling;
        }

        return elements;
    },

    /**
     * Find MageForge block data for a given element
     */
    findBlockForElement(element) {
        // Cache blocks for performance
        if (!this.cachedBlocks || Date.now() - this.lastBlocksCacheTime > 1000) {
            this.cachedBlocks = this.findAllMageForgeBlocks();
            this.lastBlocksCacheTime = Date.now();
        }

        let closestBlock = null;
        let closestDepth = -1;

        // Find the deepest (most specific) block containing this element
        for (const block of this.cachedBlocks) {
            if (block.elements.includes(element)) {
                // Calculate depth (how many ancestors between element and body)
                let depth = 0;
                let node = element;
                while (node && node !== document.body) {
                    depth++;
                    node = node.parentElement;
                }

                if (depth > closestDepth) {
                    closestBlock = block;
                    closestDepth = depth;
                }
            }
        }

        return closestBlock;
    },
};
