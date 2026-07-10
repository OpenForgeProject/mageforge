<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\TemplateEngine\Decorator;

use Magento\Framework\Escaper;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\TemplateEngineInterface;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints;
use OpenForgeProject\MageForge\Service\Inspector\Cache\BlockCacheCollector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InspectorHintsTest extends TestCase
{
    /**
     * @var TemplateEngineInterface&MockObject
     */
    private $subject;
    /**
     * @var Random&MockObject
     */
    private $random;
    /**
     * @var BlockCacheCollector&MockObject
     */
    private $cacheCollector;
    /**
     * @var File&MockObject
     */
    private $fileDriver;
    /**
     * @var Escaper&MockObject
     */
    private $escaper;

    protected function setUp(): void
    {
        $this->subject = $this->createMock(TemplateEngineInterface::class);
        $this->random = $this->createMock(Random::class);
        $this->cacheCollector = $this->createMock(BlockCacheCollector::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->escaper = $this->createMock(Escaper::class);

        // Fix the resolved Magento root so relative-path assertions are deterministic.
        $this->fileDriver->method('getParentDirectory')->willReturn('/var/www/html');

        $this->cacheCollector->method('getCacheInfo')->willReturn([
            'cacheable' => true,
            'lifetime' => null,
            'cacheKey' => '',
            'cacheTags' => [],
            'pageCacheable' => true,
        ]);
        $this->cacheCollector->method('formatMetricsForJson')->willReturn([
            'performance' => ['renderTime' => '0.00', 'timestamp' => 0],
            'cache' => ['cacheable' => true, 'lifetime' => null, 'key' => '', 'tags' => [], 'pageCacheable' => true],
        ]);
        $this->escaper->method('escapeHtml')->willReturnCallback(
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES),
        );
    }

    /**
     * @param string[] $excludedClassPrefixes
     * @param string[] $excludedTemplatePaths
     */
    private function createInspectorHints(
        array $excludedClassPrefixes = [],
        array $excludedTemplatePaths = [],
    ): InspectorHints {
        return new InspectorHints(
            $this->subject,
            true,
            $this->random,
            $this->cacheCollector,
            $this->fileDriver,
            $this->escaper,
            $excludedClassPrefixes,
            $excludedTemplatePaths,
        );
    }

    private function createBlock(): BlockInterface&MockObject
    {
        return $this->createMock(BlockInterface::class);
    }

    /**
     * Extracts and JSON-decodes the metadata embedded in the
     * data-mageforge-block attribute of rendered inspector output.
     *
     * @return array<array-key, mixed>
     */
    private function decodeBlockMetadata(string $result): array
    {
        // Guarding on the preg_match return value narrows $matches to the
        // matched shape for every PHPStan version: capture group 1 is then
        // known to exist, so no null-coalesce fallback is needed (which newer
        // PHPStan/bleedingEdge flags as redundant via nullCoalesce.offset).
        if (preg_match('/data-mageforge-block="([^"]*)"/', $result, $matches) !== 1) {
            $this->fail('render output should contain a data-mageforge-block attribute');
        }

        $metadata = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($metadata);

        return $metadata;
    }

    public function testRenderReturnsSubjectResultWhenShowBlockHintsDisabled(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('<div>Hello</div>');

        $inspector = new InspectorHints(
            $this->subject,
            false,
            $this->random,
            $this->cacheCollector,
            $this->fileDriver,
            $this->escaper,
        );

        $result = $inspector->render($block, 'template.phtml');

        $this->assertSame('<div>Hello</div>', $result);
    }

    public function testRenderSkipsInjectionForExcludedBlockClass(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('<div>Hello</div>');

        $inspector = $this->createInspectorHints([get_class($block)]);
        $result = $inspector->render($block, 'template.phtml');

        $this->assertSame('<div>Hello</div>', $result);
    }

    public function testRenderSkipsInjectionForExcludedTemplatePath(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('<div>Hello</div>');

        $inspector = $this->createInspectorHints([], ['magewire']);
        $result = $inspector->render($block, '/path/to/magewire/component.phtml');

        $this->assertSame('<div>Hello</div>', $result);
    }

    public function testRenderSkipsInjectionForEmptyContent(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('   ');

        $inspector = $this->createInspectorHints();
        $result = $inspector->render($block, 'template.phtml');

        $this->assertSame('   ', $result);
    }

    public function testRenderSkipsInjectionWhenContentDoesNotStartWithHtmlTag(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('https://example.com/some-url');

        $inspector = $this->createInspectorHints();
        $result = $inspector->render($block, 'template.phtml');

        $this->assertSame('https://example.com/some-url', $result);
    }

    public function testRenderInjectsInspectorAttributesIntoRootElement(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('<div class="foo">Hello</div>');
        $this->random->method('getRandomString')->willReturn('abcdef1234567890');

        $inspector = $this->createInspectorHints();
        $result = $inspector->render($block, '/var/www/html/template.phtml');

        $this->assertStringStartsWith('<div data-mageforge-id="mageforge-abcdef1234567890"', $result);
        $this->assertStringContainsString('data-mageforge-block=', $result);
        $this->assertStringContainsString('Hello</div>', $result);
    }

    public function testRenderEmbedsMetadataAsEscapedJson(): void
    {
        $block = $this->createBlock();
        $this->subject->method('render')->willReturn('<div>Hello</div>');
        $this->random->method('getRandomString')->willReturn('xyz');

        $inspector = $this->createInspectorHints();
        $result = $inspector->render($block, '/var/www/html/some/template.phtml');

        $metadata = $this->decodeBlockMetadata($result);

        $this->assertSame('mageforge-xyz', $metadata['id']);
        $this->assertSame('some/template.phtml', $metadata['template']);
        $this->assertArrayHasKey('block', $metadata);
        $this->assertArrayHasKey('module', $metadata);
        $this->assertArrayHasKey('performance', $metadata);
        $this->assertArrayHasKey('cache', $metadata);
    }

    public function testRenderExtractsModuleNameFromRealBlockClassName(): void
    {
        $block = new FakeVendorModuleBlock2();
        $this->subject->method('render')->willReturn('<div>Hello</div>');
        $this->random->method('getRandomString')->willReturn('xyz');

        $inspector = $this->createInspectorHints();
        $result = $inspector->render($block, '/var/www/html/template.phtml');

        $metadata = $this->decodeBlockMetadata($result);

        $this->assertSame('OpenForgeProject_MageForge', $metadata['module']);
    }

    public function testRenderDetectsParentBlockAndAlias(): void
    {
        $parentBlock = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getNameInLayout'])
            ->getMock();
        $parentBlock->method('getNameInLayout')->willReturn('parent.block');

        $block = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParentBlock', 'getNameInLayout'])
            ->getMock();
        $block->method('getParentBlock')->willReturn($parentBlock);
        $block->method('getNameInLayout')->willReturn('child.block');

        $this->subject->method('render')->willReturn('<div>Hello</div>');
        $this->random->method('getRandomString')->willReturn('xyz');

        $inspector = $this->createInspectorHints();
        $result = $inspector->render($block, '/var/www/html/template.phtml');

        $metadata = $this->decodeBlockMetadata($result);

        $this->assertSame('parent.block', $metadata['parent']);
        $this->assertSame('child.block', $metadata['alias']);
    }
}
