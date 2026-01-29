<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\TemplateEngineInterface;

/**
 * Decorates block with inspector data attributes for frontend debugging
 *
 * Injects data-mageforge-* attributes into rendered HTML for the MageForge Inspector
 */
class InspectorHints implements TemplateEngineInterface
{
    private string $magentoRoot;

    /**
     * @param TemplateEngineInterface $subject
     * @param bool $showBlockHints
     * @param Random $random
     */
    public function __construct(
        private readonly TemplateEngineInterface $subject,
        private readonly bool $showBlockHints,
        private readonly Random $random
    ) {
        // Get Magento root directory - try multiple strategies
        // 1. Try from BP constant (most reliable)
        if (defined('BP')) {
            $this->magentoRoot = BP;
        } else {
            // 2. Fallback: Calculate from file location (7 levels up)
            // vendor/openforgeproject/mageforge/src/Model/TemplateEngine/Decorator/InspectorHints.php
            $this->magentoRoot = dirname(__DIR__, 7);
        }
    }

    /**
     * Insert inspector data attributes into the rendered block contents
     *
     * @param BlockInterface $block
     * @param string $templateFile
     * @param array $dictionary
     * @return string
     */
    public function render(BlockInterface $block, $templateFile, array $dictionary = []): string
    {
        $result = $this->subject->render($block, $templateFile, $dictionary);

        if (!$this->showBlockHints) {
            return $result;
        }

        // Only inject attributes if there's actual HTML content
        if (empty(trim($result))) {
            return $result;
        }

        return $this->injectInspectorAttributes($result, $block, $templateFile);
    }

    /**
     * Inject data-mageforge-* attributes into HTML for inspector
     *
     * @param string $html
     * @param BlockInterface $block
     * @param string $templateFile
     * @return string
     */
    private function injectInspectorAttributes(string $html, BlockInterface $block, string $templateFile): string
    {
        $wrapperId = 'mageforge-' . $this->random->getRandomString(16);

        // Get block class name
        $blockClass = get_class($block);

        // Extract module name from class
        $moduleName = $this->extractModuleName($blockClass);

        // Make template path relative to Magento root
        $relativeTemplatePath = $this->getRelativePath($templateFile);

        // Get additional block data
        $viewModel = $this->getViewModel($block);
        $parentBlock = $this->getParentBlockName($block);
        $blockAlias = $this->getBlockAlias($block);
        $isOverride = $this->isTemplateOverride($templateFile, $moduleName) ? '1' : '0';

        // Build data attributes
        $dataAttributes = sprintf(
            'data-mageforge-template="%s" data-mageforge-block="%s" data-mageforge-module="%s" data-mageforge-id="%s" data-mageforge-viewmodel="%s" data-mageforge-parent="%s" data-mageforge-alias="%s" data-mageforge-override="%s"',
            htmlspecialchars($relativeTemplatePath, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($blockClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($wrapperId, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($viewModel, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($parentBlock, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($blockAlias, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($isOverride, ENT_QUOTES, 'UTF-8')
        );

        // Wrap content with data attributes using display:contents to avoid layout issues
        $wrappedHtml = sprintf(
            '<div id="%s" class="mageforge-inspectable" %s style="display:contents !important;">%s</div>',
            htmlspecialchars($wrapperId, ENT_QUOTES, 'UTF-8'),
            $dataAttributes,
            $html
        );

        return $wrappedHtml;
    }

    /**
     * Get relative path from Magento root
     *
     * @param string $absolutePath
     * @return string
     */
    private function getRelativePath(string $absolutePath): string
    {
        // If path starts with Magento root, make it relative
        if (strpos($absolutePath, $this->magentoRoot) === 0) {
            return ltrim(substr($absolutePath, strlen($this->magentoRoot)), '/');
        }

        return $absolutePath;
    }

    /**
     * Extract module name from block class name
     *
     * @param string $blockClass
     * @return string
     */
    private function extractModuleName(string $blockClass): string
    {
        // Extract vendor and module from class name
        // Example: Magento\Catalog\Block\Product\View -> Magento_Catalog
        $parts = explode('\\', $blockClass);

        if (count($parts) >= 2) {
            return $parts[0] . '_' . $parts[1];
        }

        return 'Unknown';
    }

    /**
     * Get ViewModel class name if exists
     *
     * @param BlockInterface $block
     * @return string
     */
    private function getViewModel(BlockInterface $block): string
    {
        if (method_exists($block, 'getViewModel')) {
            $viewModel = $block->getViewModel();
            if ($viewModel) {
                return get_class($viewModel);
            }
        }

        return '';
    }

    /**
     * Get parent block name
     *
     * @param BlockInterface $block
     * @return string
     */
    private function getParentBlockName(BlockInterface $block): string
    {
        if ($block instanceof AbstractBlock) {
            $parent = $block->getParentBlock();
            if ($parent instanceof AbstractBlock) {
                return $parent->getNameInLayout() ?: '';
            }
        }

        return '';
    }

    /**
     * Get block alias (name in layout)
     *
     * @param BlockInterface $block
     * @return string
     */
    private function getBlockAlias(BlockInterface $block): string
    {
        if ($block instanceof AbstractBlock) {
            return $block->getNameInLayout() ?: '';
        }

        return '';
    }

    /**
     * Check if template is overridden from original module
     *
     * @param string $templateFile
     * @param string $moduleName
     * @return bool
     */
    private function isTemplateOverride(string $templateFile, string $moduleName): bool
    {
        // Check if template is in app/design (custom theme) vs vendor module
        $relativePath = $this->getRelativePath($templateFile);

        // If path starts with app/design, it's an override
        if (strpos($relativePath, 'app/design/') === 0) {
            return true;
        }

        // If module name is in path but not in vendor/<vendor>/<module>, it's likely an override
        $modulePathPart = str_replace('_', '/', $moduleName);
        if (strpos($relativePath, 'vendor/') === false && strpos($relativePath, $modulePathPart) !== false) {
            return true;
        }

        return false;
    }
}
