<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\State;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StaticContentDeployerTest extends TestCase
{
    private Shell&MockObject $shell;
    private State&MockObject $state;
    private SymfonyStyle&MockObject $io;
    private OutputInterface&MockObject $output;
    private StaticContentDeployer $deployer;

    protected function setUp(): void
    {
        $this->shell = $this->createMock(Shell::class);
        $this->state = $this->createMock(State::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->deployer = new StaticContentDeployer($this->shell, $this->state);
    }

    public function testSkipsDeploymentInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->shell->expects($this->never())->method('execute');

        $this->assertTrue($this->deployer->deploy('Vendor/theme', $this->io, $this->output, false));
    }

    public function testDeploysThemeInProductionMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('php bin/magento setup:static-content:deploy -t %s -f --quiet', ['Vendor/theme']);

        $this->assertTrue($this->deployer->deploy('Vendor/theme', $this->io, $this->output, false));
    }

    public function testForwardsShellOutputAndReportsSuccessWhenVerbose(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEFAULT);
        $this->shell->method('execute')->willReturn('deployed 42 files');
        $this->output->expects($this->once())->method('writeln')->with('deployed 42 files');
        $this->io
            ->expects($this->once())
            ->method('success')
            ->with("Static content deployed for theme 'Vendor/theme'.");

        $this->assertTrue($this->deployer->deploy('Vendor/theme', $this->io, $this->output, true));
    }

    public function testReturnsFalseAndPrintsErrorWhenDeploymentFails(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('deploy failed'));
        $this->io->expects($this->once())->method('error')->with('deploy failed');

        $this->assertFalse($this->deployer->deploy('Vendor/theme', $this->io, $this->output, false));
    }
}
