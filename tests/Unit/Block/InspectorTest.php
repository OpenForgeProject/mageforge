<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\Element\Template\Context;
use OpenForgeProject\MageForge\Block\Inspector;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InspectorTest extends TestCase
{
    private Context&MockObject $context;
    private State&MockObject $state;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private DeveloperAccessChecker&MockObject $developerAccessChecker;
    private Inspector $block;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->state = $this->createMock(State::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->developerAccessChecker = $this->createMock(DeveloperAccessChecker::class);

        $this->block = new Inspector(
            $this->context,
            $this->state,
            $this->scopeConfig,
            $this->developerAccessChecker,
        );
    }

    public function testShouldRenderReturnsFalseWhenNotInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);

        $this->assertFalse($this->block->shouldRender());
    }

    public function testShouldRenderReturnsFalseWhenInspectorDisabled(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse($this->block->shouldRender());
    }

    public function testShouldRenderReturnsFalseWhenIpNotAllowed(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(false);

        $this->assertFalse($this->block->shouldRender());
    }

    public function testShouldRenderReturnsTrueWhenAllChecksPass(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(true);

        $this->assertTrue($this->block->shouldRender());
    }

    public function testGetShowButtonLabelsDefaultsToTrue(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertTrue($this->block->getShowButtonLabels());
    }

    public function testGetShowButtonLabelsReturnsFalseWhenExplicitlyDisabled(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('0');

        $this->assertFalse($this->block->getShowButtonLabels());
    }

    public function testGetShowButtonLabelsReturnsTrueForOtherValues(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('1');

        $this->assertTrue($this->block->getShowButtonLabels());
    }

    public function testGetThemeReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(InspectorConfig::XML_PATH_THEME, InspectorConfig::SCOPE_STORE)
            ->willReturn('light');

        $this->assertSame('light', $this->block->getTheme());
    }

    public function testGetThemeReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertSame(InspectorConfig::DEFAULT_THEME, $this->block->getTheme());
    }

    public function testGetThemeReturnsDefaultWhenNotAString(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(InspectorConfig::DEFAULT_THEME, $this->block->getTheme());
    }

    public function testGetPositionReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(InspectorConfig::XML_PATH_POSITION, InspectorConfig::SCOPE_STORE)
            ->willReturn('top-right');

        $this->assertSame('top-right', $this->block->getPosition());
    }

    public function testGetPositionReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertSame(InspectorConfig::DEFAULT_POSITION, $this->block->getPosition());
    }
}
