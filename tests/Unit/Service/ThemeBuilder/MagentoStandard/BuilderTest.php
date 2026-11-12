<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder\MagentoStandard;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuilderTest extends TestCase
{
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var File&Stub
     */
    private $fileDriver;
    /**
     * @var StaticContentDeployer&Stub
     */
    private $staticContentDeployer;
    /**
     * @var StaticContentCleaner&Stub
     */
    private $staticContentCleaner;
    /**
     * @var CacheCleaner&Stub
     */
    private $cacheCleaner;
    /**
     * @var SymlinkCleaner&Stub
     */
    private $symlinkCleaner;
    /**
     * @var NodePackageManager&Stub
     */
    private $nodePackageManager;
    /**
     * @var GruntTaskRunner&Stub
     */
    private $gruntTaskRunner;
    /**
     * @var DirectoryList&Stub
     */
    private $directoryList;
    /**
     * @var SymfonyStyle&Stub
     */
    private $io;
    /**
     * @var OutputInterface&Stub
     */
    private $output;
    /**
     * @var Builder
     */
    private Builder $builder;
    /**
     * @var string
     */
    private string $themePath = 'app/design/frontend/Vendor/theme';

    protected function setUp(): void
    {
        $this->shell = $this->createStub(Shell::class);
        $this->fileDriver = $this->createStub(File::class);
        $this->staticContentDeployer = $this->createStub(StaticContentDeployer::class);
        $this->staticContentCleaner = $this->createStub(StaticContentCleaner::class);
        $this->cacheCleaner = $this->createStub(CacheCleaner::class);
        $this->symlinkCleaner = $this->createStub(SymlinkCleaner::class);
        $this->nodePackageManager = $this->createStub(NodePackageManager::class);
        $this->gruntTaskRunner = $this->createStub(GruntTaskRunner::class);
        $this->directoryList = $this->createStub(DirectoryList::class);
        $this->directoryList->method('getRoot')->willReturn('/magento-root');
        $this->io = $this->createStub(SymfonyStyle::class);
        $this->output = $this->createStub(OutputInterface::class);

        $this->builder = $this->createBuilder();
    }

    private function createBuilder(
        ?Shell $shell = null,
        ?File $fileDriver = null,
        ?StaticContentDeployer $staticContentDeployer = null,
        ?StaticContentCleaner $staticContentCleaner = null,
        ?CacheCleaner $cacheCleaner = null,
        ?SymlinkCleaner $symlinkCleaner = null,
        ?NodePackageManager $nodePackageManager = null,
        ?GruntTaskRunner $gruntTaskRunner = null,
        ?DirectoryList $directoryList = null,
    ): Builder {
        return new Builder(
            $shell ?? $this->shell,
            $fileDriver ?? $this->fileDriver,
            $staticContentDeployer ?? $this->staticContentDeployer,
            $staticContentCleaner ?? $this->staticContentCleaner,
            $cacheCleaner ?? $this->cacheCleaner,
            $symlinkCleaner ?? $this->symlinkCleaner,
            $nodePackageManager ?? $this->nodePackageManager,
            $gruntTaskRunner ?? $this->gruntTaskRunner,
            $directoryList ?? $this->directoryList,
        );
    }

    public function testGetNameReturnsThemeName(): void
    {
        $this->assertSame('MagentoStandard', $this->builder->getName());
    }

    public function testDetectReturnsTrueForStandardTheme(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
        ]);

        $this->assertTrue($this->builder->detect($this->themePath));
    }

    public function testDetectReturnsFalseWhenThemeXmlMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', false],
            [$this->themePath . '/web/tailwind', false],
        ]);

        $this->assertFalse($this->builder->detect($this->themePath));
    }

    public function testDetectReturnsFalseWhenTailwindDirectoryPresent(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', true],
        ]);

        $this->assertFalse($this->builder->detect($this->themePath));
    }

    private function configureSuccessfulDetection(File&Stub $fileDriver): void
    {
        $fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
        ]);
    }

    public function testBuildReturnsFalseWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenStaticContentCleanFails(): void
    {
        $this->configureSuccessfulDetection($this->fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildSkipsGruntStepsForVendorTheme(): void
    {
        $vendorThemePath = 'root/vendor/some-vendor/theme';
        $this->fileDriver->method('isExists')->willReturnMap([
            [$vendorThemePath . '/theme.xml', true],
            [$vendorThemePath . '/web/tailwind', false],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')
            ->with('Vendor theme detected. Skipping Grunt steps.');
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue(
            $builder->build('Vendor/theme', $vendorThemePath, $io, $this->output, false),
        );
    }

    public function testBuildSkipsGruntStepsWhenNoNodeSetupDetected(): void
    {
        $this->configureSuccessfulDetection($this->fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/package.json', false],
            ['/magento-root/package-lock.json', false],
            ['/magento-root/gruntfile.js', false],
            ['/magento-root/grunt-config.json', false],
        ]);
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
    }

    public function testBuildDetectsCapitalisedGruntfileInMagentoRoot(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/Gruntfile.js', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->gruntTaskRunner->method('runTasks')->willReturn(true);
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->once())
            ->method('isNodeModulesInSync')
            ->with('/magento-root')
            ->willReturn(true);
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildRunsNodeSetupWhenDetected(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $executedCommands = [];
        $this->shell->method('execute')->willReturnCallback(function (string $command) use (&$executedCommands) {
            $executedCommands[] = $command;
            return '';
        });
        $symlinkCleaner = $this->createMock(SymlinkCleaner::class);
        $symlinkCleaner->expects($this->once())
            ->method('cleanSymlinks')
            ->with($this->themePath, $this->io, false)
            ->willReturn(true);
        $gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $gruntTaskRunner->expects($this->once())
            ->method('runTasks')
            ->with($this->io, $this->output, false)
            ->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $builder = $this->createBuilder(symlinkCleaner: $symlinkCleaner, gruntTaskRunner: $gruntTaskRunner);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
        $this->assertSame(['which grunt'], $executedCommands);
    }

    public function testBuildReturnsFalseWhenNodeSetupProcessingFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->shell->method('execute')->willReturn('');
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenDeployFails(): void
    {
        $vendorThemePath = 'root/vendor/some-vendor/theme';
        $this->fileDriver->method('isExists')->willReturnMap([
            [$vendorThemePath . '/theme.xml', true],
            [$vendorThemePath . '/web/tailwind', false],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(false);
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->never())->method('clean');
        $builder = $this->createBuilder(cacheCleaner: $cacheCleaner);

        $this->assertFalse(
            $builder->build('Vendor/theme', $vendorThemePath, $this->io, $this->output, false),
        );
    }

    public function testAutoRepairInstallsWhenOutOfSync(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);
        $this->shell->method('execute')->willReturn('');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath, $this->io, $this->output, true));
    }

    public function testAutoRepairReturnsFalseWhenInstallFails(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->builder->autoRepair($this->themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsGruntWhenMissing(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $callCount = 0;
        $this->shell->method('execute')->willReturnCallback(function (string $command) use (&$callCount) {
            $callCount++;
            if ($command === 'which grunt') {
                throw new \RuntimeException('not found');
            }
            return '';
        });
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())->method('success');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $io, $this->output, true));
    }

    public function testAutoRepairReturnsFalseWhenGruntInstallFails(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('failed'));

        $this->assertFalse($this->builder->autoRepair($this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseForVendorTheme(): void
    {
        $vendorThemePath = 'root/vendor/some-vendor/theme';
        $this->fileDriver->method('isExists')->willReturnMap([
            [$vendorThemePath . '/theme.xml', true],
            [$vendorThemePath . '/web/tailwind', false],
        ]);

        $this->assertFalse($this->builder->watch('Vendor/theme', $vendorThemePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenNoNodeSetupDetected(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/package.json', false],
            ['/magento-root/package-lock.json', false],
            ['/magento-root/gruntfile.js', false],
            ['/magento-root/grunt-config.json', false],
        ]);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenStaticContentCleanFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function watchExitCodeProvider(): array
    {
        return [
            'successful exit' => [0, true],
            'non-zero exit' => [3, false],
        ];
    }

    #[DataProvider('watchExitCodeProvider')]
    public function testWatchRunsGruntFromMagentoRootWithQuotedPath(int $exitCode, bool $expected): void
    {
        $root = sys_get_temp_dir() . "/mage forge's root " . uniqid();
        $binDir = $root . '/node_modules/.bin';
        mkdir($binDir, 0o777, true);
        $cwdFile = $root . '/cwd.txt';
        file_put_contents(
            $binDir . '/grunt',
            sprintf("#!/bin/sh\npwd > \"%s\"\necho \"$@\" >> \"%s\"\nexit %d\n", $cwdFile, $cwdFile, $exitCode),
        );
        chmod($binDir . '/grunt', 0o755);

        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($root);
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            [$root . '/package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->shell->method('execute')->willReturn('');
        $builder = $this->createBuilder(directoryList: $directoryList);

        $originalCwd = getcwd();
        try {
            ob_start();
            $result = $builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false);
            ob_end_clean();
            $recorded = (string) file_get_contents($cwdFile);
        } finally {
            unlink($binDir . '/grunt');
            if (is_file($cwdFile)) {
                unlink($cwdFile);
            }
            rmdir($binDir);
            rmdir($root . '/node_modules');
            rmdir($root);
        }

        $this->assertNotSame($root, $originalCwd);
        $this->assertSame($expected, $result);
        $this->assertSame([$root, 'watch'], array_map('trim', explode("\n", trim($recorded))));
    }

    public function testWatchReturnsFalseWhenAutoRepairFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['/magento-root/package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    // -------------------------------------------------------------------------
    // Mutation hardening: exact messages, call counts and branch boundaries
    // -------------------------------------------------------------------------

    public function testDetectNormalizesTrailingSlash(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
        ]);

        $this->assertTrue($this->builder->detect($this->themePath . '/'));
    }

    public function testBuildStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testVendorThemeSkipsGruntStepsWithExactWarning(): void
    {
        $vendorPath = '/app/vendor/vendor-name/theme';
        $this->givenFilesystem($this->fileDriver, $vendorPath, nodeSetup: true);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with('Vendor theme detected. Skipping Grunt steps.');
        $io->expects($this->once())->method('newLine')->with(2);
        $gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $gruntTaskRunner->expects($this->never())->method('runTasks');
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager, gruntTaskRunner: $gruntTaskRunner);

        $this->assertTrue($builder->build('Vendor/theme', $vendorPath, $io, $this->output, false));
    }

    public function testMissingNodeSetupIsAnnouncedOnlyInVerboseMode(): void
    {
        $this->givenFilesystem($this->fileDriver, $this->themePath, nodeSetup: false);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $gruntTaskRunner->expects($this->never())->method('runTasks');
        $noteMessage = 'No Node.js/Grunt setup detected. Skipping Grunt steps.';
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('note')->with($noteMessage);
        $builder = $this->createBuilder(gruntTaskRunner: $gruntTaskRunner);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $io, $this->output, true));
    }

    public function testMissingNodeSetupStaysSilentWhenNotVerbose(): void
    {
        $this->givenFilesystem($this->fileDriver, $this->themePath, nodeSetup: false);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('note');

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testBuildFailsWhenSymlinkCleaningFails(): void
    {
        $this->givenFilesystem($this->fileDriver, $this->themePath, nodeSetup: true);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);
        $gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $gruntTaskRunner->expects($this->never())->method('runTasks');
        $staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $staticContentDeployer->expects($this->never())->method('deploy');
        $builder = $this->createBuilder(
            staticContentDeployer: $staticContentDeployer,
            gruntTaskRunner: $gruntTaskRunner,
        );

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsWithExactWarningWhenOutOfSync(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->once())->method('isNodeModulesInSync')->with('/magento-root')->willReturn(false);
        $nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('warning')
            ->with('Node modules out of sync, missing, or no lock file found. Installing...');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath, $io, $this->output, true));
    }

    public function testAutoRepairSkipsInstallAndOutdatedCheckWhenQuietAndInSync(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $nodePackageManager->expects($this->never())->method('installNodeModules');

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath, $this->io, $this->output, false));
        $this->assertSame(['which grunt'], $executedCommands, 'Outdated check must not run in quiet mode');
    }

    public function testAutoRepairChecksOutdatedPackagesInVerboseMode(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
        $this->assertSame(['which grunt', 'npm outdated --json'], $executedCommands);
    }

    public function testInstallsGruntWithExactMessagesWhenMissing(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->shell
            ->method('execute')
            ->willReturnCallback(static function (string $command): string {
                if ($command === 'which grunt') {
                    throw new \RuntimeException('not found');
                }
                return '';
            });
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning')->with('Grunt not found globally. Installing grunt...');
        $io->expects($this->once())->method('success')->with('Grunt installed successfully.');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $io, $this->output, true));
    }

    public function testGruntInstallFailureReportsExactError(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('registry down'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('Failed to install grunt: registry down');

        $this->assertFalse($this->builder->autoRepair($this->themePath, $io, $this->output, false));
    }

    public function testWatchStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchRejectsVendorThemesWithExactError(): void
    {
        $vendorPath = '/app/vendor/vendor-name/theme';
        $this->givenFilesystem($this->fileDriver, $vendorPath, nodeSetup: true);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with(
                'Watch mode is not supported for vendor themes. Vendor themes are read-only and '
                . 'should have pre-built assets.',
            );
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $this->assertFalse($builder->watch('Vendor/theme', $vendorPath, $io, $this->output, false));
    }

    public function testWatchRequiresNodeSetupWithExactError(): void
    {
        $this->givenFilesystem($this->fileDriver, $this->themePath, nodeSetup: false);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with(
                'Watch mode requires Node.js/Grunt setup. No package.json, package-lock.json, '
                . 'node_modules, or grunt-config.json found.',
            );
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    /**
     * Configure the file driver: valid standard theme, node setup toggled.
     */
    private function givenFilesystem(File&Stub $fileDriver, string $themePath, bool $nodeSetup): void
    {
        $fileDriver
            ->method('isExists')
            ->willReturnCallback(static function (string $path) use ($themePath, $nodeSetup): bool {
                if ($path === $themePath . '/theme.xml') {
                    return true;
                }
                if ($path === $themePath . '/web/tailwind') {
                    return false;
                }
                return $nodeSetup && $path === '/magento-root/package.json';
            });
    }
}
