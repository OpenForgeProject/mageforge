<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\State;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StaticContentDeployerTest extends TestCase
{
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var State&Stub
     */
    private $state;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var OutputInterface&Stub
     */
    private $output;
    /**
     * @var StaticContentDeployer
     */
    private StaticContentDeployer $deployer;

    protected function setUp(): void
    {
        $this->shell = $this->createStub(Shell::class);
        $this->state = $this->createStub(State::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->output = $this->createStub(OutputInterface::class);
        $this->deployer = $this->createDeployer();
    }

    public function testSkipsDeploymentInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');

        $deployer = $this->createDeployer(shell: $shell);

        $this->assertTrue($deployer->deploy('Vendor/theme', $this->io, $this->output, false));
    }

    public function testReportsSkipInVerboseModeWhenInDeveloperMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('info')
            ->with('Skipping static content deployment in developer mode.');

        $this->assertTrue($this->deployer->deploy('Vendor/theme', $io, $this->output, true));
    }

    public function testDeploysThemeInProductionMode(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('php bin/magento setup:static-content:deploy -t %s -f --quiet', ['Vendor/theme']);

        $deployer = $this->createDeployer(shell: $shell);

        $this->assertTrue($deployer->deploy('Vendor/theme', $this->io, $this->output, false));
    }

    public function testForwardsShellOutputAndReportsSuccessWhenVerbose(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_DEFAULT);
        $this->shell->method('execute')->willReturn('deployed 42 files');
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())->method('writeln')->with('deployed 42 files');
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('success')
            ->with("Static content deployed for theme 'Vendor/theme'.");

        $this->assertTrue($this->deployer->deploy('Vendor/theme', $io, $output, true));
    }

    public function testReturnsFalseAndPrintsErrorWhenDeploymentFails(): void
    {
        $this->state->method('getMode')->willReturn(State::MODE_PRODUCTION);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('deploy failed'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('deploy failed');

        $this->assertFalse($this->deployer->deploy('Vendor/theme', $io, $this->output, false));
    }

    private function createDeployer(?Shell $shell = null, ?State $state = null): StaticContentDeployer
    {
        return new StaticContentDeployer($shell ?? $this->shell, $state ?? $this->state);
    }
}
