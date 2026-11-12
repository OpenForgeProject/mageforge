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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class InspectorHintsTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&Stub
     */
    private $scopeConfig;
    /**
     * @var DeveloperAccessChecker&Stub
     */
    private $developerAccessChecker;
    /**
     * @var InspectorHintsFactory&Stub
     */
    private $inspectorHintsFactory;
    /**
     * @var State&Stub
     */
    private $state;
    /**
     * @var TemplateEngineFactory&Stub
     */
    private $subject;
    /**
     * @var TemplateEngineInterface&Stub
     */
    private $invocationResult;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $this->developerAccessChecker = $this->createStub(DeveloperAccessChecker::class);
        $this->inspectorHintsFactory = $this->createStub(InspectorHintsFactory::class);
        $this->state = $this->createStub(State::class);
        $this->subject = $this->createStub(TemplateEngineFactory::class);
        $this->invocationResult = $this->createStub(TemplateEngineInterface::class);
    }

    private function createPlugin(
        ?ScopeConfigInterface $scopeConfig = null,
        ?DeveloperAccessChecker $developerAccessChecker = null,
        ?InspectorHintsFactory $inspectorHintsFactory = null,
    ): InspectorHints {
        return new InspectorHints(
            $scopeConfig ?? $this->scopeConfig,
            $developerAccessChecker ?? $this->developerAccessChecker,
            $inspectorHintsFactory ?? $this->inspectorHintsFactory,
            $this->state,
        );
    }

    public function testReturnsOriginalResultWhenNotInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->never())->method('isSetFlag');

        $plugin = $this->createPlugin(scopeConfig: $scopeConfig);
        $result = $plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($this->invocationResult, $result);
    }

    public function testReturnsOriginalResultWhenInspectorDisabled(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(InspectorConfig::XML_PATH_ENABLED, InspectorConfig::SCOPE_STORE)
            ->willReturn(false);
        $developerAccessChecker = $this->createMock(DeveloperAccessChecker::class);
        $developerAccessChecker->expects($this->never())->method('isDevAllowed');

        $plugin = $this->createPlugin(
            scopeConfig: $scopeConfig,
            developerAccessChecker: $developerAccessChecker,
        );
        $result = $plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($this->invocationResult, $result);
    }

    public function testReturnsOriginalResultWhenIpNotAllowed(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(false);
        $inspectorHintsFactory = $this->createMock(InspectorHintsFactory::class);
        $inspectorHintsFactory->expects($this->never())->method('create');

        $plugin = $this->createPlugin(inspectorHintsFactory: $inspectorHintsFactory);
        $result = $plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($this->invocationResult, $result);
    }

    public function testWrapsResultWithInspectorDecoratorWhenAllChecksPass(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->developerAccessChecker->method('isDevAllowed')->willReturn(true);

        $decorated = $this->createStub(InspectorHintsDecorator::class);
        $inspectorHintsFactory = $this->createMock(InspectorHintsFactory::class);
        $inspectorHintsFactory->expects($this->once())
            ->method('create')
            ->with([
                'subject' => $this->invocationResult,
                'showBlockHints' => true,
            ])
            ->willReturn($decorated);

        $plugin = $this->createPlugin(inspectorHintsFactory: $inspectorHintsFactory);
        $result = $plugin->afterCreate($this->subject, $this->invocationResult);

        $this->assertSame($decorated, $result);
    }
}
