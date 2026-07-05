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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InspectorCommandTest extends TestCase
{
    private WriterInterface&MockObject $configWriter;
    private State&MockObject $state;
    private CacheManager&MockObject $cacheManager;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private InspectorCommand $command;

    protected function setUp(): void
    {
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->state = $this->createMock(State::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $this->command = new InspectorCommand(
            $this->configWriter,
            $this->state,
            $this->cacheManager,
            $this->scopeConfig,
        );
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
        $this->configWriter->expects($this->never())->method('save');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'enable']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('developer mode', $tester->getDisplay());
    }

    public function testEnableSavesConfigAndCleansCacheInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(InspectorConfig::XML_PATH_ENABLED, '1');
        $this->cacheManager->expects($this->once())
            ->method('clean')
            ->with(['config', 'layout', 'full_page', 'block_html']);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['action' => 'ENABLE']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('has been enabled', $tester->getDisplay());
    }

    public function testDisableSavesConfigAndCleansCache(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(InspectorConfig::XML_PATH_ENABLED, '0');

        $tester = new CommandTester($this->command);
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
}
