<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Inspector\Cache;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use OpenForgeProject\MageForge\Service\Inspector\Cache\BlockCacheCollector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockCacheCollectorTest extends TestCase
{
    private LayoutInterface&MockObject $layout;
    private BlockCacheCollector $collector;

    protected function setUp(): void
    {
        $this->layout = $this->createMock(LayoutInterface::class);
        $this->collector = new BlockCacheCollector($this->layout);
    }

    /**
     * getCacheLifetime()/getCacheTags() are protected on AbstractBlock, so calling them from
     * outside the class (as the collector does) is routed through DataObject::__call(), which
     * reads the raw value from the block's data bag. Configuring the block via setData() lets
     * us control exactly what the collector sees without fighting mock visibility rules.
     */
    private function createBlock(): AbstractBlock&MockObject
    {
        return $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isScopePrivate', 'getCacheKey'])
            ->getMock();
    }

    public function testGetCacheInfoReturnsNotCacheableWhenLifetimeIsFalse(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', false);
        $this->layout->method('getAllBlocks')->willReturn([]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertFalse($info['cacheable']);
        $this->assertNull($info['lifetime']);
    }

    public function testGetCacheInfoReturnsUnlimitedLifetimeWhenNull(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', null);
        $this->layout->method('getAllBlocks')->willReturn([]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertTrue($info['cacheable']);
        $this->assertNull($info['lifetime']);
    }

    public function testGetCacheInfoReturnsSpecificLifetime(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', 3600);
        $this->layout->method('getAllBlocks')->willReturn([]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertTrue($info['cacheable']);
        $this->assertSame(3600, $info['lifetime']);
    }

    public function testGetCacheInfoTreatsPrivateScopeAsNotCacheable(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', 3600);
        $block->method('isScopePrivate')->willReturn(true);
        $this->layout->method('getAllBlocks')->willReturn([]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertFalse($info['cacheable']);
        $this->assertNull($info['lifetime']);
    }

    public function testGetCacheInfoResolvesCacheKeyAndTags(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', false);
        $block->setData('cache_tags', ['tag_one', 'tag_two', 42]);
        $block->method('getCacheKey')->willReturn('some_cache_key');
        $this->layout->method('getAllBlocks')->willReturn([]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertSame('some_cache_key', $info['cacheKey']);
        $this->assertSame(['tag_one', 'tag_two'], $info['cacheTags']);
    }

    public function testGetCacheInfoDefaultsCacheKeyWhenEmpty(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', false);
        $block->method('getCacheKey')->willReturn('');
        $this->layout->method('getAllBlocks')->willReturn([]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertSame('', $info['cacheKey']);
    }

    public function testGetCacheInfoDetectsNonCacheablePageFromLayoutData(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', false);

        $dataBlock = $this->createBlock();
        $dataBlock->setData('cacheable', false);

        $this->layout->method('getAllBlocks')->willReturn([$dataBlock]);

        $info = $this->collector->getCacheInfo($block);

        $this->assertFalse($info['pageCacheable']);
    }

    public function testGetCacheInfoTreatsPageAsCacheableWhenLayoutThrows(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', false);
        $this->layout->method('getAllBlocks')->willThrowException(new \Exception('boom'));

        $info = $this->collector->getCacheInfo($block);

        $this->assertTrue($info['pageCacheable']);
    }

    public function testGetCacheInfoIgnoresNonObjectBlocksFromLayout(): void
    {
        $block = $this->createBlock();
        $block->setData('cache_lifetime', false);
        $this->layout->method('getAllBlocks')->willReturn(['not-an-object']);

        $info = $this->collector->getCacheInfo($block);

        $this->assertTrue($info['pageCacheable']);
    }

    public function testFormatMetricsForJsonBuildsExpectedStructure(): void
    {
        $renderMetrics = [
            'renderTimeMs' => 12.345,
            'startTime' => 1_700_000_000_000_000_000,
            'endTime' => 1_700_000_000_012_000_000,
        ];
        $cacheMetrics = [
            'cacheable' => true,
            'lifetime' => 3600,
            'cacheKey' => 'key',
            'cacheTags' => ['tag'],
            'pageCacheable' => true,
        ];

        $formatted = $this->collector->formatMetricsForJson($renderMetrics, $cacheMetrics);

        $this->assertSame('12.35', $formatted['performance']['renderTime']);
        $this->assertSame(1_700_000_000, $formatted['performance']['timestamp']);
        $this->assertSame(true, $formatted['cache']['cacheable']);
        $this->assertSame(3600, $formatted['cache']['lifetime']);
        $this->assertSame('key', $formatted['cache']['key']);
        $this->assertSame(['tag'], $formatted['cache']['tags']);
        $this->assertTrue($formatted['cache']['pageCacheable']);
    }
}
