<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Cache;

use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\LayoutInterface;

/**
 * Collects performance metrics from Magento blocks for Inspector
 *
 * Measures render time and extracts cache configuration with strict type safety (PHPStan Level 8).
 *
 * @package OpenForgeProject\MageForge
 */
class BlockCacheCollector
{
    /**
     * @param LayoutInterface $layout
     */
    public function __construct(
        private readonly LayoutInterface $layout
    ) {
    }
    /**
     * Get cache information from block
     *
     * Safely extracts cache lifetime, key, and tags with explicit type checking
     * to satisfy PHPStan Level 8 requirements.
     *
     * @param BlockInterface $block
     * @return array{cacheable: bool, lifetime: int|null, cacheKey: string, cacheTags: array<int, string>, pageCacheable: bool}
     */
    public function getCacheInfo(BlockInterface $block): array
    {
        $lifetime = null;
        $cacheKey = '';
        $cacheTags = [];
        $cacheable = false;

        // Type guard: Check if method exists before calling
        if (method_exists($block, 'getCacheLifetime')) {
            $lifetimeRaw = $block->getCacheLifetime();

            // In Magento:
            // - false = not cacheable
            // - null = unlimited cache (cacheable!)
            // - int = specific cache lifetime in seconds (cacheable!)
            if ($lifetimeRaw !== false) {
                $cacheable = true;
                // Convert to int or null for type safety
                if (is_int($lifetimeRaw)) {
                    $lifetime = $lifetimeRaw;
                } elseif ($lifetimeRaw === null) {
                    // null = unlimited cache
                    $lifetime = null;
                } elseif (is_numeric($lifetimeRaw) && (int)$lifetimeRaw === 0) {
                    // 0 = unlimited cache
                    $lifetime = null;
                }
            }
        }

        // Check if block is private/customer-specific (not cacheable)
        // Private blocks (like checkout, customer account) should not be cached
        if ($cacheable && method_exists($block, 'isScopePrivate')) {
            if ($block->isScopePrivate()) {
                $cacheable = false;
                $lifetime = null;
            }
        }

        // Additional fallback: Check protected property via reflection if available
        if ($cacheable && property_exists($block, '_isScopePrivate')) {
            try {
                $reflection = new \ReflectionProperty($block, '_isScopePrivate');
                $reflection->setAccessible(true);
                $isScopePrivate = $reflection->getValue($block);
                if ($isScopePrivate === true) {
                    $cacheable = false;
                    $lifetime = null;
                }
            } catch (\ReflectionException $e) {
                // If reflection fails, keep current cacheable value
            }
        }

        if (method_exists($block, 'getCacheKey')) {
            $keyRaw = $block->getCacheKey();
            $cacheKey = is_string($keyRaw) && $keyRaw !== '' ? $keyRaw : '';
        }

        if (method_exists($block, 'getCacheTags')) {
            $tagsRaw = $block->getCacheTags();
            // Ensure string array (PHPStan strict)
            if (is_array($tagsRaw)) {
                foreach ($tagsRaw as $tag) {
                    if (is_string($tag)) {
                        $cacheTags[] = $tag;
                    }
                }
            }
        }

        // Check if page itself is cacheable
        $pageCacheable = $this->isPageCacheable();

        return [
            'cacheable' => $cacheable,
            'lifetime' => $lifetime,
            'cacheKey' => $cacheKey,
            'cacheTags' => $cacheTags,
            'pageCacheable' => $pageCacheable,
        ];
    }

    /**
     * Check if current page is cacheable
     *
     * Checks layout configuration to determine if page has cacheable="false" attribute.
     * If ANY block on the page is marked as non-cacheable in layout XML, the entire page is non-cacheable.
     *
     * @return bool True if page is cacheable, false otherwise
     */
    private function isPageCacheable(): bool
    {
        try {
            // Get all blocks from layout
            $allBlocks = $this->layout->getAllBlocks();

            foreach ($allBlocks as $block) {
                // Check if block has isCacheable method (added by layout processor)
                if (method_exists($block, 'isCacheable')) {
                    // @phpstan-ignore-next-line
                    if (!$block->isCacheable()) {
                        return false;
                    }
                }

                // Check data key 'cacheable' set by layout XML
                if (method_exists($block, 'getData')) {
                    // @phpstan-ignore-next-line
                    $cacheableData = $block->getData('cacheable');
                    if ($cacheableData === false || $cacheableData === 'false') {
                        return false;
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            // If we can't determine, assume cacheable to avoid false alarms
            return true;
        }
    }

    /**
     * Format metrics for JSON export to frontend
     *
     * @param array{renderTimeMs: float, startTime: int, endTime: int} $renderMetrics
     * @param array{cacheable: bool, lifetime: int|null, cacheKey: string, cacheTags: array<int, string>, pageCacheable: bool} $cacheMetrics
     * @return array{performance: array{renderTime: string, timestamp: int}, cache: array{cacheable: bool, lifetime: int|null, key: string, tags: array<int, string>, pageCacheable: bool}}
     */
    public function formatMetricsForJson(array $renderMetrics, array $cacheMetrics): array
    {
        return [
            'performance' => [
                'renderTime' => number_format($renderMetrics['renderTimeMs'], 2),
                'timestamp' => (int)($renderMetrics['startTime'] / 1_000_000_000), // Convert ns to seconds
            ],
            'cache' => [
                'cacheable' => $cacheMetrics['cacheable'],
                'lifetime' => $cacheMetrics['lifetime'],
                'key' => $cacheMetrics['cacheKey'],
                'tags' => $cacheMetrics['cacheTags'],
                'pageCacheable' => $cacheMetrics['pageCacheable'],
            ],
        ];
    }
}
