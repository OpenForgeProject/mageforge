<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Inspector\Cache;

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
        $lifetime = $this->resolveCacheLifetime($block);
        $cacheable = $lifetime !== false;

        if ($cacheable && $this->isBlockScopePrivate($block)) {
            $cacheable = false;
            $lifetime = null;
        }

        $cacheKey = $this->resolveCacheKey($block);
        $cacheTags = $this->resolveCacheTags($block);

        // Check if page itself is cacheable
        $pageCacheable = $this->isPageCacheable();

        return [
            'cacheable' => $cacheable,
            'lifetime' => $lifetime === false ? null : $lifetime,
            'cacheKey' => $cacheKey,
            'cacheTags' => $cacheTags,
            'pageCacheable' => $pageCacheable,
        ];
    }

    /**
     * Resolve cache lifetime from block
     *
     * @param BlockInterface $block
     * @return int|null|false False if not cacheable, null for unlimited, int for specific lifetime
     */
    private function resolveCacheLifetime(BlockInterface $block): int|null|false
    {
        if (!method_exists($block, 'getCacheLifetime')) {
            return false;
        }

        $lifetimeRaw = $block->getCacheLifetime();

        // In Magento:
        // - false = not cacheable
        // - null = unlimited cache (cacheable!)
        // - int = specific cache lifetime in seconds (cacheable!)

        if ($lifetimeRaw === false) {
            return false;
        }

        if (is_int($lifetimeRaw)) {
            return $lifetimeRaw;
        }

        if ($lifetimeRaw === null) {
            return null; // Unlimited
        }

        if (is_numeric($lifetimeRaw) && (int)$lifetimeRaw === 0) {
            return null; // Unlimited
        }

        return false; // Default fallback
    }

    /**
     * Check if block is private (customer specific)
     *
     * @param BlockInterface $block
     * @return bool
     */
    private function isBlockScopePrivate(BlockInterface $block): bool
    {
        // Private blocks (like checkout, customer account) should not be cached
        if (method_exists($block, 'isScopePrivate')) {
            if ($block->isScopePrivate()) {
                return true;
            }
        }

        // Additional fallback: Check protected property via reflection if available
        if (property_exists($block, '_isScopePrivate')) {
            try {
                $reflection = new \ReflectionProperty($block, '_isScopePrivate');
                $reflection->setAccessible(true);
                $isScopePrivate = $reflection->getValue($block);
                if ($isScopePrivate === true) {
                    return true;
                }
            } catch (\ReflectionException $e) {
                // If reflection fails, assume not private
            }
        }

        return false;
    }

    /**
     * Resolve cache key from block
     *
     * @param BlockInterface $block
     * @return string
     */
    private function resolveCacheKey(BlockInterface $block): string
    {
        if (method_exists($block, 'getCacheKey')) {
            $keyRaw = $block->getCacheKey();
            return is_string($keyRaw) && $keyRaw !== '' ? $keyRaw : '';
        }
        return '';
    }

    /**
     * Resolve cache tags from block
     *
     * @param BlockInterface $block
     * @return array<int, string>
     */
    private function resolveCacheTags(BlockInterface $block): array
    {
        $cacheTags = [];
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
        return $cacheTags;
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
