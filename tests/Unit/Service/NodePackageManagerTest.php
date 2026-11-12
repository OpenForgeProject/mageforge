<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class NodePackageManagerTest extends TestCase
{
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var FileDriver&Stub
     */
    private $fileDriver;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var NodePackageManager
     */
    private NodePackageManager $packageManager;

    protected function setUp(): void
    {
        $this->shell = $this->createStub(Shell::class);
        $this->fileDriver = $this->createStub(FileDriver::class);
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->packageManager = $this->createPackageManager();
    }

    private function createPackageManager(?Shell $shell = null, ?FileDriver $fileDriver = null): NodePackageManager
    {
        return new NodePackageManager($shell ?? $this->shell, $fileDriver ?? $this->fileDriver);
    }

    // -------------------------------------------------------------------------
    // installNodeModules
    // -------------------------------------------------------------------------

    public function testUsesNpmCiWhenLockFileExists(): void
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->expects($this->once())->method('isExists')->with('/theme/package-lock.json')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm ci --quiet', ['/theme']);

        $packageManager = $this->createPackageManager(shell: $shell, fileDriver: $fileDriver);

        $this->assertTrue($packageManager->installNodeModules('/theme', $this->io, false));
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
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm install --quiet', ['/theme']);

        $packageManager = $this->createPackageManager(shell: $shell);

        $this->assertTrue($packageManager->installNodeModules('/theme', $this->io, false));
    }

    public function testReturnsFalseAndPrintsErrorWhenInstallationFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm not found'));
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to install node modules: npm not found');

        $this->assertFalse($this->packageManager->installNodeModules('/theme', $io, false));
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
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('warning')
            ->with('npm ci failed, falling back to npm install...');
        $io
            ->expects($this->once())
            ->method('success')
            ->with('Node modules installed successfully.');

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $io, true));
    }

    public function testWarnsInVerboseModeWhenLockFileIsMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('warning')
            ->with('No package-lock.json found, running npm install...');
        $io
            ->expects($this->once())
            ->method('success')
            ->with('Node modules installed successfully.');

        $this->assertTrue($this->packageManager->installNodeModules('/theme', $io, true));
    }

    // -------------------------------------------------------------------------
    // isNodeModulesInSync
    // -------------------------------------------------------------------------

    public function testNotInSyncWhenNodeModulesIsMissing(): void
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->expects($this->once())->method('isDirectory')->with('/theme/node_modules')->willReturn(false);

        $packageManager = $this->createPackageManager(fileDriver: $fileDriver);

        $this->assertFalse($packageManager->isNodeModulesInSync('/theme'));
    }

    public function testNotInSyncWhenLockFileIsMissing(): void
    {
        $fileDriver = $this->createMock(FileDriver::class);
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->expects($this->once())->method('isExists')->with('/theme/package-lock.json')->willReturn(false);

        $packageManager = $this->createPackageManager(fileDriver: $fileDriver);

        $this->assertFalse($packageManager->isNodeModulesInSync('/theme'));
    }

    public function testInSyncWhenNpmLsSucceeds(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm ls --depth=0 --json > /dev/null 2>&1', ['/theme']);

        $packageManager = $this->createPackageManager(shell: $shell);

        $this->assertTrue($packageManager->isNodeModulesInSync('/theme'));
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
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm outdated --json', ['/theme'])
            ->willReturn('{"tailwindcss": {"current": "3.0.0", "latest": "4.0.0"}}');
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with('Outdated packages found:');
        $io
            ->expects($this->once())
            ->method('writeln')
            ->with('{"tailwindcss": {"current": "3.0.0", "latest": "4.0.0"}}');

        $packageManager = $this->createPackageManager(shell: $shell);

        $packageManager->checkOutdatedPackages('/theme', $io);
    }

    public function testStaysSilentWhenEverythingIsUpToDate(): void
    {
        $this->shell->method('execute')->willReturn('');
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('warning');

        $this->packageManager->checkOutdatedPackages('/theme', $io);
    }

    public function testReportsCheckFailureOnlyInVerboseMode(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('registry unreachable'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('isVerbose')->willReturn(true);
        $io
            ->expects($this->once())
            ->method('warning')
            ->with('Failed to check outdated packages: registry unreachable');

        $this->packageManager->checkOutdatedPackages('/theme', $io);
    }

    // -------------------------------------------------------------------------
    // getOutdatedPackages
    // -------------------------------------------------------------------------

    public function testParsesOutdatedPackagesFromNpmJsonOutput(): void
    {
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm outdated --json --long 2>/dev/null; true', ['/theme'])
            ->willReturn(json_encode([
                'tailwindcss' => [
                    'current' => '3.4.1',
                    'wanted' => '3.4.17',
                    'latest' => '4.1.5',
                    'type' => 'devDependencies',
                ],
                'alpinejs' => [
                    'current' => '3.13.0',
                    'wanted' => '3.14.9',
                    'latest' => '3.14.9',
                    'type' => 'dependencies',
                ],
            ], JSON_THROW_ON_ERROR));

        $packageManager = $this->createPackageManager(shell: $shell);

        $packages = $packageManager->getOutdatedPackages('/theme');

        $this->assertSame([
            [
                'name' => 'tailwindcss',
                'current' => '3.4.1',
                'wanted' => '3.4.17',
                'latest' => '4.1.5',
                'type' => 'devDependencies',
            ],
            [
                'name' => 'alpinejs',
                'current' => '3.13.0',
                'wanted' => '3.14.9',
                'latest' => '3.14.9',
                'type' => 'dependencies',
            ],
        ], $packages);
    }

    public function testNormalizesListEntriesAndMissingFields(): void
    {
        $this->shell->method('execute')->willReturn(json_encode([
            'postcss' => [
                ['wanted' => '8.5.0', 'latest' => '8.5.0'],
                ['wanted' => '8.4.0', 'latest' => '8.5.0'],
            ],
        ], JSON_THROW_ON_ERROR));

        $packages = $this->packageManager->getOutdatedPackages('/theme');

        $this->assertSame([
            [
                'name' => 'postcss',
                'current' => '-',
                'wanted' => '8.5.0',
                'latest' => '8.5.0',
                'type' => 'dependencies',
            ],
        ], $packages);
    }

    public function testReturnsEmptyListWhenEverythingIsUpToDate(): void
    {
        $this->shell->method('execute')->willReturn('{}');

        $this->assertSame([], $this->packageManager->getOutdatedPackages('/theme'));
    }

    public function testReturnsNullOnEmptyOutput(): void
    {
        $this->shell->method('execute')->willReturn('');

        $this->assertNull($this->packageManager->getOutdatedPackages('/theme'));
    }

    public function testReturnsNullOnInvalidJsonOutput(): void
    {
        $this->shell->method('execute')->willReturn('npm ERR! something went wrong');

        $this->assertNull($this->packageManager->getOutdatedPackages('/theme'));
    }

    public function testReturnsNullOnNpmErrorJsonOutput(): void
    {
        $this->shell->method('execute')->willReturn(json_encode([
            'error' => ['code' => 'ENOTFOUND', 'summary' => 'registry unreachable'],
        ], JSON_THROW_ON_ERROR));

        $this->assertNull($this->packageManager->getOutdatedPackages('/theme'));
    }

    public function testReturnsNullWhenShellExecutionFails(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm not found'));

        $this->assertNull($this->packageManager->getOutdatedPackages('/theme'));
    }

    // -------------------------------------------------------------------------
    // updatePackages
    // -------------------------------------------------------------------------

    public function testRunsNpmUpdate(): void
    {
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm update --quiet', ['/theme']);

        $packageManager = $this->createPackageManager(shell: $shell);

        $this->assertTrue($packageManager->updatePackages('/theme', $this->io, false));
    }

    public function testReportsSuccessInVerboseModeAfterUpdate(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('text')->with('Running npm update...');
        $io->expects($this->once())->method('success')->with('Packages updated within their semver ranges.');

        $this->assertTrue($this->packageManager->updatePackages('/theme', $io, true));
    }

    public function testReturnsFalseAndPrintsErrorWhenUpdateFails(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('registry unreachable'));
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to update packages: registry unreachable');

        $this->assertFalse($this->packageManager->updatePackages('/theme', $io, false));
    }

    // -------------------------------------------------------------------------
    // installPackageVersions
    // -------------------------------------------------------------------------

    public function testInstallsPackagesWithSaveDevFlagForDevDependencies(): void
    {
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with(
                'cd %s && npm install --save-dev --quiet %s %s',
                ['/theme', 'tailwindcss@4.1.5', 'postcss@8.5.0'],
            );

        $packageManager = $this->createPackageManager(shell: $shell);

        $this->assertTrue($packageManager->installPackageVersions(
            '/theme',
            'devDependencies',
            ['tailwindcss' => '4.1.5', 'postcss' => '8.5.0'],
            $this->io,
            false,
        ));
    }

    public function testInstallsPackagesWithSaveFlagForUnknownDependencyType(): void
    {
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm install --save --quiet %s', ['/theme', 'alpinejs@3.14.9']);

        $packageManager = $this->createPackageManager(shell: $shell);

        $this->assertTrue($packageManager->installPackageVersions(
            '/theme',
            'somethingUnknown',
            ['alpinejs' => '3.14.9'],
            $this->io,
            false,
        ));
    }

    public function testSkipsShellExecutionForEmptyPackageList(): void
    {
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');

        $packageManager = $this->createPackageManager(shell: $shell);

        $this->assertTrue($packageManager->installPackageVersions(
            '/theme',
            'dependencies',
            [],
            $this->io,
            false,
        ));
    }

    public function testReturnsFalseAndPrintsErrorWhenInstallOfVersionsFails(): void
    {
        $this->shell->method('execute')->willThrowException(new \RuntimeException('E404 not found'));
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to install packages: E404 not found');

        $this->assertFalse($this->packageManager->installPackageVersions(
            '/theme',
            'dependencies',
            ['alpinejs' => '3.14.9'],
            $io,
            false,
        ));
    }
}
