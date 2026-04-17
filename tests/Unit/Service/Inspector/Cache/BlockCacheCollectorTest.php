<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service\Inspector\Cache;

use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\LayoutInterface;
use OpenForgeProject\MageForge\Service\Inspector\Cache\BlockCacheCollector;
use PHPUnit\Framework\TestCase;

class BlockCacheCollectorTest extends TestCase
{
    private LayoutInterface $layout;
    private BlockCacheCollector $collector;

    protected function setUp(): void
    {
        $this->layout = $this->createMock(LayoutInterface::class);
        $this->layout->method('getAllBlocks')->willReturn([]);
        $this->collector = new BlockCacheCollector($this->layout);
    }

    // ---- getCacheInfo tests ----

    public function testGetCacheInfoReturnsFalseWhenBlockHasNoCacheLifetimeMethod(): void
    {
        // A plain block with only toHtml() has no getCacheLifetime() method
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertFalse($result['cacheable']);
        $this->assertNull($result['lifetime']);
    }

    public function testGetCacheInfoReturnsCacheableWithIntLifetime(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): int
            {
                return 3600;
            }

            public function getCacheKey(): string
            {
                return 'my_block_key';
            }

            /** @return array<string> */
            public function getCacheTags(): array
            {
                return ['catalog_product', 'catalog_category'];
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertTrue($result['cacheable']);
        $this->assertSame(3600, $result['lifetime']);
        $this->assertSame('my_block_key', $result['cacheKey']);
        $this->assertSame(['catalog_product', 'catalog_category'], $result['cacheTags']);
    }

    public function testGetCacheInfoReturnsCacheableWithNullLifetime(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): ?int
            {
                return null;
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertTrue($result['cacheable']);
        $this->assertNull($result['lifetime']);
    }

    public function testGetCacheInfoReturnsNotCacheableWhenLifetimeIsFalse(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): bool
            {
                return false;
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertFalse($result['cacheable']);
    }

    public function testGetCacheInfoReturnsCacheableWithNumericZeroLifetime(): void
    {
        // Numeric "0" string should be treated as unlimited cache
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): string
            {
                return '0';
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertTrue($result['cacheable']);
        $this->assertNull($result['lifetime']);
    }

    public function testGetCacheInfoReturnsFalseForPrivateScopeBlock(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): int
            {
                return 3600;
            }

            public function isScopePrivate(): bool
            {
                return true;
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertFalse($result['cacheable']);
        $this->assertNull($result['lifetime']);
    }

    public function testGetCacheInfoReturnsCacheableForNonPrivateScopeBlock(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): int
            {
                return 600;
            }

            public function isScopePrivate(): bool
            {
                return false;
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertTrue($result['cacheable']);
        $this->assertSame(600, $result['lifetime']);
    }

    public function testGetCacheInfoIncludesPageCacheableStatusFromLayout(): void
    {
        $nonCacheableBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function isCacheable(): bool
            {
                return false;
            }
        };

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getAllBlocks')->willReturn([$nonCacheableBlock]);

        $collector = new BlockCacheCollector($layout);

        $mainBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }
        };

        $result = $collector->getCacheInfo($mainBlock);

        $this->assertFalse($result['pageCacheable']);
    }

    public function testGetCacheInfoPageIsCacheableWhenAllBlocksAreCacheable(): void
    {
        $cacheableBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function isCacheable(): bool
            {
                return true;
            }
        };

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getAllBlocks')->willReturn([$cacheableBlock]);

        $collector = new BlockCacheCollector($layout);

        $mainBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }
        };
        $result = $collector->getCacheInfo($mainBlock);

        $this->assertTrue($result['pageCacheable']);
    }

    public function testGetCacheInfoPageNotCacheableWhenBlockHasCacheableDataFalse(): void
    {
        $layoutBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getData(string $key): mixed
            {
                return $key === 'cacheable' ? false : null;
            }
        };

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getAllBlocks')->willReturn([$layoutBlock]);

        $collector = new BlockCacheCollector($layout);

        $mainBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }
        };
        $result = $collector->getCacheInfo($mainBlock);

        $this->assertFalse($result['pageCacheable']);
    }

    public function testGetCacheInfoPageNotCacheableWhenBlockHasCacheableDataStringFalse(): void
    {
        $layoutBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getData(string $key): mixed
            {
                return $key === 'cacheable' ? 'false' : null;
            }
        };

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getAllBlocks')->willReturn([$layoutBlock]);

        $collector = new BlockCacheCollector($layout);

        $mainBlock = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }
        };
        $result = $collector->getCacheInfo($mainBlock);

        $this->assertFalse($result['pageCacheable']);
    }

    public function testGetCacheInfoCacheTagsFilterOutNonStringValues(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): int
            {
                return 3600;
            }

            /** @return array<mixed> */
            public function getCacheTags(): array
            {
                return ['valid_tag', 123, 'another_tag', null];
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertSame(['valid_tag', 'another_tag'], $result['cacheTags']);
    }

    public function testGetCacheInfoReturnsEmptyStringForBlockWithNoCacheKeyMethod(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): int
            {
                return 3600;
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertSame('', $result['cacheKey']);
    }

    public function testGetCacheInfoReturnsEmptyStringForEmptyCacheKey(): void
    {
        $block = new class implements BlockInterface {
            public function toHtml(): string
            {
                return '';
            }

            public function getCacheLifetime(): int
            {
                return 3600;
            }

            public function getCacheKey(): string
            {
                return '';
            }
        };

        $result = $this->collector->getCacheInfo($block);

        $this->assertSame('', $result['cacheKey']);
    }

    // ---- formatMetricsForJson tests ----

    public function testFormatMetricsForJsonProducesCorrectStructure(): void
    {
        $renderMetrics = [
            'renderTimeMs' => 12.5,
            'startTime' => 1_700_000_000_000_000_000,
            'endTime' => 1_700_000_000_012_500_000,
        ];
        $cacheMetrics = [
            'cacheable' => true,
            'lifetime' => 3600,
            'cacheKey' => 'product_list',
            'cacheTags' => ['catalog_product'],
            'pageCacheable' => true,
        ];

        $result = $this->collector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        $this->assertArrayHasKey('performance', $result);
        $this->assertArrayHasKey('cache', $result);
    }

    public function testFormatMetricsForJsonFormatsRenderTimeAsString(): void
    {
        $renderMetrics = [
            'renderTimeMs' => 12.5,
            'startTime' => 1_700_000_000_000_000_000,
            'endTime' => 1_700_000_000_012_500_000,
        ];
        $cacheMetrics = [
            'cacheable' => true,
            'lifetime' => null,
            'cacheKey' => '',
            'cacheTags' => [],
            'pageCacheable' => true,
        ];

        $result = $this->collector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        $this->assertIsString($result['performance']['renderTime']);
        $this->assertSame('12.50', $result['performance']['renderTime']);
    }

    public function testFormatMetricsForJsonConvertsTimestampFromNanoseconds(): void
    {
        $startTimeNs = 1_700_000_000_000_000_000;
        $expectedTimestamp = (int) ($startTimeNs / 1_000_000_000);

        $renderMetrics = [
            'renderTimeMs' => 5.0,
            'startTime' => $startTimeNs,
            'endTime' => $startTimeNs + 5_000_000,
        ];
        $cacheMetrics = [
            'cacheable' => false,
            'lifetime' => null,
            'cacheKey' => '',
            'cacheTags' => [],
            'pageCacheable' => false,
        ];

        $result = $this->collector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        $this->assertSame($expectedTimestamp, $result['performance']['timestamp']);
    }

    public function testFormatMetricsForJsonIncludesCacheData(): void
    {
        $renderMetrics = [
            'renderTimeMs' => 1.0,
            'startTime' => 1_000_000_000_000_000_000,
            'endTime' => 1_000_000_000_001_000_000,
        ];
        $cacheMetrics = [
            'cacheable' => true,
            'lifetime' => 7200,
            'cacheKey' => 'my_key',
            'cacheTags' => ['tag1', 'tag2'],
            'pageCacheable' => false,
        ];

        $result = $this->collector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        $this->assertTrue($result['cache']['cacheable']);
        $this->assertSame(7200, $result['cache']['lifetime']);
        $this->assertSame('my_key', $result['cache']['key']);
        $this->assertSame(['tag1', 'tag2'], $result['cache']['tags']);
        $this->assertFalse($result['cache']['pageCacheable']);
    }

    public function testFormatMetricsForJsonFormatsRenderTimeWithTwoDecimalPlaces(): void
    {
        $renderMetrics = [
            'renderTimeMs' => 1.123456,
            'startTime' => 1_000_000_000_000_000_000,
            'endTime' => 1_000_000_000_001_000_000,
        ];
        $cacheMetrics = [
            'cacheable' => false,
            'lifetime' => null,
            'cacheKey' => '',
            'cacheTags' => [],
            'pageCacheable' => true,
        ];

        $result = $this->collector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        $this->assertSame('1.12', $result['performance']['renderTime']);
    }
}
