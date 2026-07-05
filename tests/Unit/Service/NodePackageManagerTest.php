<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class NodePackageManagerTest extends TestCase
{
    private Shell&MockObject $shell;
    private FileDriver&MockObject $fileDriver;
    private SymfonyStyle&MockObject $io;
    private NodePackageManager $packageManager;

    protected function setUp(): void
    {
        $this->shell = $this->createMock(Shell::class);
        $this->fileDriver = $this->createMock(FileDriver::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->packageManager = new NodePackageManager($this->shell, $this->fileDriver);
    }

    // -------------------------------------------------------------------------
    // installNodeModules
    // -------------------------------------------------------------------------

    public function testUsesNpmCiWhenLockFileExists(): void
    {
        $this->fileDriver->method('isExists')->with('/theme/package-lock.json')->willReturn(true);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm ci --quiet', ['/theme']);

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $this->io, false));
    }

    public function testFallsBackToNpmInstallWhenNpmCiFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command, array $args) use (&$executedCommands): string {
                $executedCommands[] = $command;
                if (str_contains($command, 'npm ci')) {
                    throw new \RuntimeException('lock file out of sync');
                }
                return '';
            });

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $this->io, false));
        $this->assertSame(['cd %s && npm ci --quiet', 'cd %s && npm install --quiet'], $executedCommands);
    }

    public function testUsesNpmInstallWhenLockFileIsMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm install --quiet', ['/theme']);

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $this->io, false));
    }

    public function testReturnsFalseAndPrintsErrorWhenInstallationFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm not found'));
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to install node modules: npm not found');

        $this->assertFalse($this->packageManager->installNodeModules('/theme', $this->io, false));
    }

    public function testWarnsAndReportsSuccessInVerboseModeWhenNpmCiFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command): string {
                if (str_contains($command, 'npm ci')) {
                    throw new \RuntimeException('lock file out of sync');
                }
                return '';
            });
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with('npm ci failed, falling back to npm install...');
        $this->io
            ->expects($this->once())
            ->method('success')
            ->with('Node modules installed successfully.');

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $this->io, true));
    }

    public function testWarnsInVerboseModeWhenLockFileIsMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with('No package-lock.json found, running npm install...');
        $this->io
            ->expects($this->once())
            ->method('success')
            ->with('Node modules installed successfully.');

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $this->io, true));
    }

    // -------------------------------------------------------------------------
    // isNodeModulesInSync
    // -------------------------------------------------------------------------

    public function testNotInSyncWhenNodeModulesIsMissing(): void
    {
        $this->fileDriver->method('isDirectory')->with('/theme/node_modules')->willReturn(false);

        $this->assertFalse($this->packageManager->isNodeModulesInSync('/theme'));
    }

    public function testNotInSyncWhenLockFileIsMissing(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('isExists')->with('/theme/package-lock.json')->willReturn(false);

        $this->assertFalse($this->packageManager->isNodeModulesInSync('/theme'));
    }

    public function testInSyncWhenNpmLsSucceeds(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm ls --depth=0 --json > /dev/null 2>&1', ['/theme']);

        $this->assertTrue($this->packageManager->isNodeModulesInSync('/theme'));
    }

    public function testNotInSyncWhenNpmLsFails(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('missing packages'));

        $this->assertFalse($this->packageManager->isNodeModulesInSync('/theme'));
    }

    // -------------------------------------------------------------------------
    // checkOutdatedPackages
    // -------------------------------------------------------------------------

    public function testWarnsAboutOutdatedPackages(): void
    {
        $this->shell
            ->method('execute')
            ->with('cd %s && npm outdated --json', ['/theme'])
            ->willReturn('{"tailwindcss": {"current": "3.0.0", "latest": "4.0.0"}}');
        $this->io->expects($this->once())->method('warning')->with('Outdated packages found:');
        $this->io
            ->expects($this->once())
            ->method('writeln')
            ->with('{"tailwindcss": {"current": "3.0.0", "latest": "4.0.0"}}');

        $this->packageManager->checkOutdatedPackages('/theme', $this->io);
    }

    public function testStaysSilentWhenEverythingIsUpToDate(): void
    {
        $this->shell->method('execute')->willReturn('');
        $this->io->expects($this->never())->method('warning');

        $this->packageManager->checkOutdatedPackages('/theme', $this->io);
    }

    public function testReportsCheckFailureOnlyInVerboseMode(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('registry unreachable'));
        $this->io->method('isVerbose')->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with('Failed to check outdated packages: registry unreachable');

        $this->packageManager->checkOutdatedPackages('/theme', $this->io);
    }
}
