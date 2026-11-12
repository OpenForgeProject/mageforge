<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Dev;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Dev\InspectorCommand;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class InspectorCommandTest extends TestCase
{
    /**
     * @var WriterInterface&Stub
     */
    private $configWriter;
    /**
     * @var State&Stub
     */
    private $state;
    /**
     * @var CacheManager&Stub
     */
    private $cacheManager;
    /**
     * @var ScopeConfigInterface&Stub
     */
    private $scopeConfig;
    /**
     * @var InspectorCommand
     */
    private InspectorCommand $command;

    protected function setUp(): void
    {
        $this->configWriter = $this->createStub(WriterInterface::class);
        $this->state = $this->createStub(State::class);
        $this->cacheManager = $this->createStub(CacheManager::class);
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);

        $this->command = $this->createCommand();
    }

    public function testRejectsInvalidAction(): void
    {
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'bogus']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid action', $tester->getDisplay());
    }

    public function testEnableFailsOutsideDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->never())->method('save');

        $tester = new CommandTester($this->createCommand(configWriter: $configWriter));
        $exitCode = $tester->execute(['action' => 'enable']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('developer mode', $tester->getDisplay());
    }

    public function testEnableSavesConfigAndCleansCacheInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(InspectorConfig::XML_PATH_ENABLED, '1');
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->once())
            ->method('clean')
            ->with(['config', 'layout', 'full_page', 'block_html']);

        $tester = new CommandTester($this->createCommand(configWriter: $configWriter, cacheManager: $cacheManager));
        $exitCode = $tester->execute(['action' => 'ENABLE']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('has been enabled', $tester->getDisplay());
    }

    public function testDisableSavesConfigAndCleansCache(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(InspectorConfig::XML_PATH_ENABLED, '0');

        $tester = new CommandTester($this->createCommand(configWriter: $configWriter));
        $exitCode = $tester->execute(['action' => 'disable']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('has been disabled', $tester->getDisplay());
    }

    public function testStatusWarnsWhenNotInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'status']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('requires developer mode', $tester->getDisplay());
    }

    public function testStatusNotesWhenDisabledInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'status']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Inspector is disabled', $tester->getDisplay());
    }

    public function testStatusReportsActiveWhenEnabledInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'status']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('active and ready to use', $tester->getDisplay());
    }

    public function testCommandNameIsConfigured(): void
    {
        $this->assertSame('mageforge:theme:inspector', $this->command->getName());
    }

    // -------------------------------------------------------------------------
    // Mutation hardening: exact messages and cache interactions
    // -------------------------------------------------------------------------

    public function testEnableFailureListsCurrentModeAndRemedy(): void
    {
        $this->state->method('getMode')->willReturn('production');

        $tester = new CommandTester($this->command);
        $tester->execute(['action' => 'enable']);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Inspector can only be enabled/disabled in developer mode.', $display);
        $this->assertStringContainsString('Current mode: production', $display);
        $this->assertStringContainsString('bin/magento deploy:mode:set developer', $display);
    }

    public function testEnableAnnouncesUsageAndCleansExactCacheTypes(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager
            ->expects($this->once())
            ->method('clean')
            ->with(['config', 'layout', 'full_page', 'block_html']);

        $tester = new CommandTester($this->createCommand(cacheManager: $cacheManager));
        $tester->execute(['action' => 'enable']);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('MageForge Inspector has been enabled!', $display);
        $activeHint = 'The inspector will now be active on the frontend for allowed IPs.';
        $this->assertStringContainsString($activeHint, $display);
        $this->assertStringContainsString('Press Ctrl+Shift+I (or Cmd+Option+I on macOS)', $display);
        $this->assertStringContainsString('Hover over elements to see their template information', $display);
        $this->assertStringContainsString('Click to pin the inspector panel', $display);
        $this->assertStringContainsString('Inspector only works in developer mode and for allowed IPs', $display);
        $this->assertStringContainsString('Config, layout, full page & block HTML caches cleaned', $display);
    }

    public function testStatusShowsModeAndEnabledState(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);

        $tester = new CommandTester($this->command);
        $tester->execute(['action' => 'status']);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('MageForge Inspector Status', $display);
        $this->assertStringContainsString('Mode: developer', $display);
        $this->assertStringContainsString('Inspector: Enabled', $display);
        $this->assertStringContainsString('Inspector is active and ready to use!', $display);
        $this->assertStringNotContainsString('Inspector is disabled.', $display);
    }

    public function testDisabledStatusShowsDisabledState(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $tester = new CommandTester($this->command);
        $tester->execute(['action' => 'status']);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Inspector: Disabled', $display);
        $this->assertStringContainsString(
            'Inspector is disabled. Run "bin/magento mageforge:theme:inspector enable" to activate it.',
            $display,
        );
        $this->assertStringNotContainsString('ready to use', $display);
    }

    public function testStatusUppercasesActionArgument(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'STATUS']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('MageForge Inspector Status', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // No-argument execution path (interactive menu / non-interactive fallback)
    // -------------------------------------------------------------------------

    public function testNoActionFallsBackToStatusInNonInteractiveMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->never())->method('save');

        $tester = new CommandTester($this->createCommand(configWriter: $configWriter));
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('MageForge Inspector Status', $tester->getDisplay());
    }

    public function testNoActionUsesSelectedActionFromInteractiveMenu(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->once())
            ->method('save')
            ->with(InspectorConfig::XML_PATH_ENABLED, '0');

        $tester = new CommandTester($this->createInteractiveCommand('disable', configWriter: $configWriter));
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('has been disabled', $tester->getDisplay());
    }

    public function testNoActionFailsWhenInteractiveMenuIsCancelled(): void
    {
        $configWriter = $this->createMock(WriterInterface::class);
        $configWriter->expects($this->never())->method('save');

        $tester = new CommandTester($this->createInteractiveCommand(null, configWriter: $configWriter));
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    private function createCommand(
        ?WriterInterface $configWriter = null,
        ?State $state = null,
        ?CacheManager $cacheManager = null,
        ?ScopeConfigInterface $scopeConfig = null,
    ): InspectorCommand {
        return new InspectorCommand(
            $configWriter ?? $this->configWriter,
            $state ?? $this->state,
            $cacheManager ?? $this->cacheManager,
            $scopeConfig ?? $this->scopeConfig,
        );
    }

    /**
     * Create a command double that always takes the interactive path and returns
     * the given selection from the menu instead of rendering a real prompt
     *
     * @param string|null $selection
     * @param WriterInterface|null $configWriter
     * @return InspectorCommand
     */
    private function createInteractiveCommand(
        ?string $selection,
        ?WriterInterface $configWriter = null,
    ): InspectorCommand {
        return new class(
            $configWriter ?? $this->configWriter,
            $this->state,
            $this->cacheManager,
            $this->scopeConfig,
            $selection,
        ) extends InspectorCommand {
            public function __construct(
                WriterInterface $configWriter,
                State $state,
                CacheManager $cacheManager,
                ScopeConfigInterface $scopeConfig,
                private readonly ?string $selection,
            ) {
                parent::__construct($configWriter, $state, $cacheManager, $scopeConfig);
            }

            protected function isInteractiveTerminal(OutputInterface $output): bool
            {
                return true;
            }

            protected function promptAction(): ?string
            {
                return $this->selection;
            }
        };
    }
}
