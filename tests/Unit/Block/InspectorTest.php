<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\Element\Template\Context;
use OpenForgeProject\MageForge\Block\Inspector;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class InspectorTest extends TestCase
{
    /**
     * @var Context&Stub
     */
    private $context;
    /**
     * @var State&Stub
     */
    private $state;
    /**
     * @var ScopeConfigInterface&Stub
     */
    private $scopeConfig;
    /**
     * @var DeveloperAccessChecker&Stub
     */
    private $developerAccessChecker;
    /**
     * @var Inspector
     */
    private Inspector $block;

    protected function setUp(): void
    {
        $this->context = $this->createStub(Context::class);
        $this->state = $this->createStub(State::class);
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $this->developerAccessChecker = $this->createStub(DeveloperAccessChecker::class);

        $this->block = $this->createBlock();
    }

    private function createBlock(?ScopeConfigInterface $scopeConfig = null): Inspector
    {
        return new Inspector(
            $this->context,
            $this->state,
            $scopeConfig ?? $this->scopeConfig,
            $this->developerAccessChecker,
        );
    }

    public function testShouldRenderReturnsFalseWhenNotInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        // Every later gate passes, so only the developer-mode check can make this return false
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(true);

        $this->assertFalse($this->block->shouldRender());
    }

    public function testShouldRenderReturnsFalseWhenInspectorDisabled(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        // The IP gate passes, so only the disabled config flag can make this return false
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(true);

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
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(InspectorConfig::XML_PATH_THEME, InspectorConfig::SCOPE_STORE)
            ->willReturn('light');

        $this->assertSame('light', $this->createBlock(scopeConfig: $scopeConfig)->getTheme());
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
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(InspectorConfig::XML_PATH_POSITION, InspectorConfig::SCOPE_STORE)
            ->willReturn('top-right');

        $this->assertSame('top-right', $this->createBlock(scopeConfig: $scopeConfig)->getPosition());
    }

    public function testGetPositionReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertSame(InspectorConfig::DEFAULT_POSITION, $this->block->getPosition());
    }

    public function testGetKeyboardShortcutsEnabledDefaultsToTrue(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertTrue($this->block->getKeyboardShortcutsEnabled());
    }

    public function testGetKeyboardShortcutsEnabledReturnsFalseWhenExplicitlyDisabled(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('0');

        $this->assertFalse($this->block->getKeyboardShortcutsEnabled());
    }

    public function testGetKeyboardShortcutsEnabledReturnsTrueForOtherValues(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('1');

        $this->assertTrue($this->block->getKeyboardShortcutsEnabled());
    }

    public function testGetToolbarShortcutReturnsConfiguredValue(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(InspectorConfig::XML_PATH_TOOLBAR_SHORTCUT, InspectorConfig::SCOPE_STORE)
            ->willReturn('Shift+F8');

        $this->assertSame('Shift+F8', $this->createBlock(scopeConfig: $scopeConfig)->getToolbarShortcut());
    }

    public function testGetToolbarShortcutReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertSame(InspectorConfig::DEFAULT_TOOLBAR_SHORTCUT, $this->block->getToolbarShortcut());
    }

    public function testGetInspectorShortcutReturnsConfiguredValue(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(InspectorConfig::XML_PATH_INSPECTOR_SHORTCUT, InspectorConfig::SCOPE_STORE)
            ->willReturn('F12');

        $this->assertSame('F12', $this->createBlock(scopeConfig: $scopeConfig)->getInspectorShortcut());
    }

    public function testGetInspectorShortcutReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame(InspectorConfig::DEFAULT_INSPECTOR_SHORTCUT, $this->block->getInspectorShortcut());
    }
}
