<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\TemplateEngineInterface;
use OpenForgeProject\MageForge\Service\Inspector\Cache\BlockCacheCollector;

/**
 * Decorates block with inspector data attributes for frontend debugging
 *
 * Injects data-mageforge-* attributes into rendered HTML for the MageForge Inspector
 */
class InspectorHints implements TemplateEngineInterface
{
    /**
     * Magento root path resolved at runtime.
     */
    private string $magentoRoot;

    /**
     * @param TemplateEngineInterface $subject
     * @param bool $showBlockHints
     * @param Random $random
     * @param BlockCacheCollector $cacheCollector
     */
    public function __construct(
        private readonly TemplateEngineInterface $subject,
        private readonly bool $showBlockHints,
        private readonly Random $random,
        private readonly BlockCacheCollector $cacheCollector
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
     * @param array<string, mixed> $dictionary
     * @return string
     */
    public function render(BlockInterface $block, $templateFile, array $dictionary = []): string
    {
        // Measure render time
        $startTime = hrtime(true);
        $result = $this->subject->render($block, $templateFile, $dictionary);
        $endTime = hrtime(true);

        if (!$this->showBlockHints) {
            return $result;
        }

        // Only inject attributes if there's actual HTML content
        if (empty(trim($result))) {
            return $result;
        }

        // Calculate render time in milliseconds
        $renderTimeNs = $endTime - $startTime;
        $renderTimeMs = $renderTimeNs / 1_000_000;

        $renderMetrics = [
            'renderTimeMs' => round($renderTimeMs, 2),
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];

        return $this->injectInspectorAttributes($result, $block, $templateFile, $renderMetrics);
    }

    /**
     * Inject MageForge inspector comment markers into HTML
     *
     * @param string $html
     * @param BlockInterface $block
     * @param string $templateFile
     * @param array{renderTimeMs: float, startTime: int, endTime: int} $renderMetrics
     * @return string
     */
    private function injectInspectorAttributes(
        string $html,
        BlockInterface $block,
        string $templateFile,
        array $renderMetrics
    ): string {
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

        // Collect performance and cache metrics
        $cacheMetrics = $this->cacheCollector->getCacheInfo($block);
        $formattedMetrics = $this->cacheCollector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        // Build metadata as JSON
        $metadata = [
            'id' => $wrapperId,
            'template' => $relativeTemplatePath,
            'block' => $blockClass,
            'module' => $moduleName,
            'viewModel' => $viewModel,
            'parent' => $parentBlock,
            'alias' => $blockAlias,
            'override' => $isOverride,
            'performance' => $formattedMetrics['performance'],
            'cache' => $formattedMetrics['cache'],
        ];

        // JSON encode with proper escaping for HTML comments
        $jsonMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($jsonMetadata === false) {
            return $html;
        }

        // Escape any comment terminators in JSON to prevent breaking out of comment
        $jsonMetadata = str_replace('-->', '--&gt;', $jsonMetadata);

        // Wrap content with comment markers
        $wrappedHtml = sprintf(
            "<!-- MAGEFORGE_START %s -->\n%s\n<!-- MAGEFORGE_END %s -->",
            $jsonMetadata,
            $html,
            $wrapperId
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
            if (is_object($viewModel)) {
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
