<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\TemplateEngine\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\TemplateEngineFactory;
use Magento\Framework\View\TemplateEngineInterface;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints as InspectorHintsDecorator;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHintsFactory;
use OpenForgeProject\MageForge\Model\TemplateEngine\Plugin\InspectorHints;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InspectorHintsTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private DeveloperAccessChecker&MockObject $developerAccessChecker;
    private InspectorHintsFactory&MockObject $inspectorHintsFactory;
    private State&MockObject $state;
    private TemplateEngineFactory&MockObject $subject;
    private TemplateEngineInterface&MockObject $invocationResult;
    private InspectorHints $plugin;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->developerAccessChecker = $this->createMock(DeveloperAccessChecker::class);
        $this->inspectorHintsFactory = $this->createMock(InspectorHintsFactory::class);
        $this->state = $this->createMock(State::class);
        $this->subject = $this->createMock(TemplateEngineFactory::class);
        $this->invocationResult = $this->createMock(TemplateEngineInterface::class);

        $this->plugin = new InspectorHints(
            $this->scopeConfig,
            $this->developerAccessChecker,
            $this->inspectorHintsFactory,
            $this->state,
        );
    }

    public function testReturnsOriginalResultWhenNotInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->scopeConfig->expects($this->never())->method('isSetFlag');

        $result = $this->plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($this->invocationResult, $result);
    }

    public function testReturnsOriginalResultWhenInspectorDisabled(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')
            ->with(InspectorConfig::XML_PATH_ENABLED, InspectorConfig::SCOPE_STORE)
            ->willReturn(false);
        $this->developerAccessChecker->expects($this->never())->method('isDevAllowed');

        $result = $this->plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($this->invocationResult, $result);
    }

    public function testReturnsOriginalResultWhenIpNotAllowed(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(false);
        $this->inspectorHintsFactory->expects($this->never())->method('create');

        $result = $this->plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($this->invocationResult, $result);
    }

    public function testWrapsResultWithInspectorDecoratorWhenAllChecksPass(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(true);

        $decorated = $this->createMock(InspectorHintsDecorator::class);
        $this->inspectorHintsFactory->expects($this->once())
            ->method('create')
            ->with([
                'subject' => $this->invocationResult,
                'showBlockHints' => true,
            ])
            ->willReturn($decorated);

        $result = $this->plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($decorated, $result);
    }
}
