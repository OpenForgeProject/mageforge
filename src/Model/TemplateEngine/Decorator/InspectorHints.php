<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\Escaper;
use Magento\Framework\Filesystem\Driver\File;
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
     * @var string
     */
    private string $magentoRoot;

    /**
     * @param TemplateEngineInterface $subject
     * @param bool $showBlockHints
     * @param Random $random
     * @param BlockCacheCollector $cacheCollector
     * @param File $fileDriver
     * @param Escaper $escaper
     * @param string[] $excludedClassPrefixes Block class prefixes to skip inspector wrapping for
     * @param string[] $excludedTemplatePaths Template path substrings to skip inspector wrapping for
     */
    public function __construct(
        private readonly TemplateEngineInterface $subject,
        private readonly bool $showBlockHints,
        private readonly Random $random,
        private readonly BlockCacheCollector $cacheCollector,
        private readonly File $fileDriver,
        private readonly Escaper $escaper,
        private readonly array $excludedClassPrefixes = [],
        private readonly array $excludedTemplatePaths = [],
    ) {
        $this->magentoRoot = $this->resolveMagentoRoot();
    }

    /**
     * Resolve Magento root directory.
     */
    private function resolveMagentoRoot(): string
    {
        if (defined('BP')) {
            return (string) BP;
        }

        // vendor/openforgeproject/mageforge/src/Model/TemplateEngine/Decorator/InspectorHints.php
        $path = __DIR__;
        for ($i = 0; $i < 7; $i++) {
            $path = $this->fileDriver->getParentDirectory($path);
        }

        return $path;
    }

    /**
     * Check if a block class should be excluded from inspector wrapping
     *
     * @param string $blockClass
     * @return bool
     */
    private function isExcluded(string $blockClass): bool
    {
        foreach ($this->excludedClassPrefixes as $prefix) {
            if (str_starts_with($blockClass, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a template path should be excluded from inspector wrapping
     *
     * @param string $templateFile
     * @return bool
     */
    private function isExcludedTemplate(string $templateFile): bool
    {
        $normalized = str_replace('\\', '/', strtolower($templateFile));
        foreach ($this->excludedTemplatePaths as $path) {
            if (str_contains($normalized, str_replace('\\', '/', strtolower(trim($path))))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Insert inspector data attributes into the rendered block contents
     *
     * @param BlockInterface $block
     * @param string $templateFile
     * @param array $dictionary
     * @phpstan-param array<array-key, mixed> $dictionary
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

        // Skip inspector wrapping for excluded block classes (e.g. Magewire components)
        if ($this->isExcluded(get_class($block))) {
            return $result;
        }

        // Skip inspector wrapping for templates in excluded paths (e.g. /magewire/ directories).
        if ($this->isExcludedTemplate($templateFile)) {
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
            'startTime' => (int) $startTime,
            'endTime' => (int) $endTime,
        ];

        return $this->injectInspectorAttributes($result, $block, $templateFile, $renderMetrics);
    }

    /**
     * Inject MageForge inspector data attributes into the first root HTML element
     *
     * Injects data-mageforge-id and data-mageforge-block on the opening tag of the
     * first HTML element in the output. If the content does not start with an HTML
     * element (e.g. a plain URL or text fragment used inside an href attribute by a
     * parent PageBuilder template), injection is skipped entirely to avoid corrupting
     * the surrounding markup.
     *
     * @param string $html
     * @param BlockInterface $block
     * @param string $templateFile
     * @param array $renderMetrics
     * @phpstan-param array{renderTimeMs: float, startTime: int, endTime: int} $renderMetrics
     * @return string
     */
    private function injectInspectorAttributes(
        string $html,
        BlockInterface $block,
        string $templateFile,
        array $renderMetrics,
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

        // Detect CMS block identifier (e.g. for PageBuilder blocks rendered via Magento\Cms\Block\Block)
        $cmsBlockId = method_exists($block, 'getBlockId') ? (string) $block->getBlockId() : '';

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
            'cmsBlockId' => $cmsBlockId,
            'performance' => $formattedMetrics['performance'],
            'cache' => $formattedMetrics['cache'],
        ];

        $jsonMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($jsonMetadata === false) {
            return $html;
        }

        // Escape all characters that need HTML-encoding so the JSON can be safely
        // embedded in an HTML attribute. escapeHtml handles &, <, > and quotes.
        // The browser automatically decodes HTML entities when getAttribute() is called,
        // so JSON.parse() on the JS side will receive the correct string.
        $safeJson = $this->escaper->escapeHtml($jsonMetadata);

        // Inject data-mageforge-* attributes on the first root HTML element.
        // This avoids HTML comment nodes which corrupt markup when block output is
        // embedded inside HTML attribute values (e.g. PageBuilder URL blocks in href="...").
        $replaced = false;
        $result = preg_replace_callback(
            '/^(\s*<[a-zA-Z][a-zA-Z0-9-]*)/s',
            static function (array $matches) use ($wrapperId, $safeJson, &$replaced): string {
                $replaced = true;
                return (
                    $matches[0]
                    . ' data-mageforge-id="'
                    . $wrapperId
                    . '"'
                    . ' data-mageforge-block="'
                    . $safeJson
                    . '"'
                );
            },
            $html,
            1,
        );

        // If content doesn't start with an HTML element (e.g. plain text, URLs),
        // skip injection to avoid corrupting attribute values in parent templates.
        if (!$replaced || $result === null) {
            return $html;
        }

        return $result;
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
        if (str_starts_with($absolutePath, $this->magentoRoot)) {
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
        if (str_starts_with($relativePath, 'app/design/')) {
            return true;
        }

        // If module name is in path but not in vendor/<vendor>/<module>, it's likely an override
        $modulePathPart = str_replace('_', '/', $moduleName);
        if (!str_contains($relativePath, 'vendor/') && str_contains($relativePath, $modulePathPart)) {
            return true;
        }

        return false;
    }
}
