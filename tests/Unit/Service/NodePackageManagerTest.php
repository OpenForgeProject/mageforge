<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service;

use Exception;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class NodePackageManagerTest extends TestCase
{
    private Shell $shell;
    private FileDriver $fileDriver;
    private NodePackageManager $manager;
    private SymfonyStyle $io;

    protected function setUp(): void
    {
        $this->shell = $this->createMock(Shell::class);
        $this->fileDriver = $this->createMock(FileDriver::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->manager = new NodePackageManager($this->shell, $this->fileDriver);
    }

    // ---- installNodeModules tests ----

    public function testInstallNodeModulesUsesCiWhenLockfileExists(): void
    {
        $this->fileDriver->method('isExists')
            ->with('/theme/web/tailwind/package-lock.json')
            ->willReturn(true);

        $this->shell->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm ci --quiet', ['/theme/web/tailwind'])
            ->willReturn('');

        $result = $this->manager->installNodeModules('/theme/web/tailwind', $this->io, false);

        $this->assertTrue($result);
    }

    public function testInstallNodeModulesFallsBackToInstallWhenCiFails(): void
    {
        $this->fileDriver->method('isExists')
            ->with('/theme/web/tailwind/package-lock.json')
            ->willReturn(true);

        $callCount = 0;
        $this->shell->method('execute')
            ->willReturnCallback(function (string $command) use (&$callCount): string {
                $callCount++;
                if ($callCount === 1) {
                    throw new Exception('npm ci failed');
                }
                return '';
            });

        $result = $this->manager->installNodeModules('/theme/web/tailwind', $this->io, false);

        $this->assertTrue($result);
        $this->assertSame(2, $callCount);
    }

    public function testInstallNodeModulesUsesNpmInstallWhenNoLockfile(): void
    {
        $this->fileDriver->method('isExists')
            ->with('/theme/web/tailwind/package-lock.json')
            ->willReturn(false);

        $this->shell->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm install --quiet', ['/theme/web/tailwind'])
            ->willReturn('');

        $result = $this->manager->installNodeModules('/theme/web/tailwind', $this->io, false);

        $this->assertTrue($result);
    }

    public function testInstallNodeModulesReturnsFalseWhenInstallFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->shell->method('execute')->willThrowException(new Exception('npm install failed'));

        $this->io->expects($this->once())->method('error');

        $result = $this->manager->installNodeModules('/theme/web/tailwind', $this->io, false);

        $this->assertFalse($result);
    }

    public function testInstallNodeModulesReturnsFalseWhenBothCiAndInstallFail(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);

        $this->shell->method('execute')->willThrowException(new Exception('npm failed'));

        $result = $this->manager->installNodeModules('/theme/web/tailwind', $this->io, false);

        $this->assertFalse($result);
    }

    public function testInstallNodeModulesShowsSuccessMessageInVerboseMode(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->shell->method('execute')->willReturn('');

        $this->io->expects($this->once())->method('success');

        $this->manager->installNodeModules('/theme/path', $this->io, true);
    }

    public function testInstallNodeModulesShowsWarningWhenNoLockfileInVerboseMode(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->shell->method('execute')->willReturn('');

        $this->io->expects($this->atLeastOnce())->method('warning');

        $this->manager->installNodeModules('/theme/path', $this->io, true);
    }

    // ---- isNodeModulesInSync tests ----

    public function testIsNodeModulesInSyncReturnsFalseWhenNodeModulesDirectoryMissing(): void
    {
        $this->fileDriver->method('isDirectory')
            ->with('/theme/web/tailwind/node_modules')
            ->willReturn(false);

        $result = $this->manager->isNodeModulesInSync('/theme/web/tailwind');

        $this->assertFalse($result);
    }

    public function testIsNodeModulesInSyncReturnsFalseWhenPackageLockJsonMissing(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);

        $this->fileDriver->method('isExists')
            ->with('/theme/web/tailwind/package-lock.json')
            ->willReturn(false);

        $result = $this->manager->isNodeModulesInSync('/theme/web/tailwind');

        $this->assertFalse($result);
    }

    public function testIsNodeModulesInSyncReturnsTrueWhenNpmLsSucceeds(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->shell->method('execute')->willReturn('');

        $result = $this->manager->isNodeModulesInSync('/theme/web/tailwind');

        $this->assertTrue($result);
    }

    public function testIsNodeModulesInSyncReturnsFalseWhenNpmLsFails(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new Exception('npm ls failed'));

        $result = $this->manager->isNodeModulesInSync('/theme/web/tailwind');

        $this->assertFalse($result);
    }

    // ---- checkOutdatedPackages tests ----

    public function testCheckOutdatedPackagesDoesNotOutputWhenNoOutdatedPackages(): void
    {
        $this->shell->method('execute')->willReturn('');

        $this->io->expects($this->never())->method('warning');

        $this->manager->checkOutdatedPackages('/theme/path', $this->io);
    }

    public function testCheckOutdatedPackagesOutputsWarningWhenPackagesFound(): void
    {
        $outdatedOutput = '{"lodash":{"current":"4.17.0","wanted":"4.17.21"}}';
        $this->shell->method('execute')->willReturn($outdatedOutput);

        $this->io->expects($this->once())->method('warning');

        $this->manager->checkOutdatedPackages('/theme/path', $this->io);
    }
}
