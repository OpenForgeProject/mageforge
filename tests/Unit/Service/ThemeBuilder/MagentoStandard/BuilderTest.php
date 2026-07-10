<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder\MagentoStandard;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard\Builder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuilderTest extends TestCase
{
    /**
     * @var Shell&MockObject
     */
    private $shell;
    /**
     * @var File&MockObject
     */
    private $fileDriver;
    /**
     * @var StaticContentDeployer&MockObject
     */
    private $staticContentDeployer;
    /**
     * @var StaticContentCleaner&MockObject
     */
    private $staticContentCleaner;
    /**
     * @var CacheCleaner&MockObject
     */
    private $cacheCleaner;
    /**
     * @var SymlinkCleaner&MockObject
     */
    private $symlinkCleaner;
    /**
     * @var NodePackageManager&MockObject
     */
    private $nodePackageManager;
    /**
     * @var GruntTaskRunner&MockObject
     */
    private $gruntTaskRunner;
    /**
     * @var SymfonyStyle&MockObject
     */
    private $io;
    /**
     * @var OutputInterface&MockObject
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
        $this->shell = $this->createMock(Shell::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $this->staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $this->cacheCleaner = $this->createMock(CacheCleaner::class);
        $this->symlinkCleaner = $this->createMock(SymlinkCleaner::class);
        $this->nodePackageManager = $this->createMock(NodePackageManager::class);
        $this->gruntTaskRunner = $this->createMock(GruntTaskRunner::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->builder = new Builder(
            $this->shell,
            $this->fileDriver,
            $this->staticContentDeployer,
            $this->staticContentCleaner,
            $this->cacheCleaner,
            $this->symlinkCleaner,
            $this->nodePackageManager,
            $this->gruntTaskRunner,
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

    private function configureSuccessfulDetection(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
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
        $this->configureSuccessfulDetection();
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
        $this->io->expects($this->once())->method('warning')
            ->with('Vendor theme detected. Skipping Grunt steps.');
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);

        $this->assertTrue(
            $this->builder->build('Vendor/theme', $vendorThemePath, $this->io, $this->output, false),
        );
    }

    public function testBuildSkipsGruntStepsWhenNoNodeSetupDetected(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['./package.json', false],
            ['./package-lock.json', false],
            ['./gruntfile.js', false],
            ['./grunt-config.json', false],
        ]);
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
    }

    public function testBuildRunsNodeSetupWhenDetected(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['./package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $executedCommands = [];
        $this->shell->method('execute')->willReturnCallback(function (string $command) use (&$executedCommands) {
            $executedCommands[] = $command;
            return '';
        });
        $this->symlinkCleaner->expects($this->once())
            ->method('cleanSymlinks')
            ->with($this->themePath, $this->io, false)
            ->willReturn(true);
        $this->gruntTaskRunner->expects($this->once())
            ->method('runTasks')
            ->with($this->io, $this->output, false)
            ->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
        $this->assertSame(['which grunt'], $executedCommands);
    }

    public function testBuildReturnsFalseWhenNodeSetupProcessingFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['./package.json', true],
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
        $this->cacheCleaner->expects($this->never())->method('clean');

        $this->assertFalse(
            $this->builder->build('Vendor/theme', $vendorThemePath, $this->io, $this->output, false),
        );
    }

    public function testAutoRepairInstallsWhenOutOfSync(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);
        $this->shell->method('execute')->willReturn('');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
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
        $this->io->expects($this->atLeastOnce())->method('success');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
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
            ['./package.json', false],
            ['./package-lock.json', false],
            ['./gruntfile.js', false],
            ['./grunt-config.json', false],
        ]);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenStaticContentCleanFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['./package.json', true],
        ]);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenAutoRepairFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/web/tailwind', false],
            ['./package.json', true],
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
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testVendorThemeSkipsGruntStepsWithExactWarning(): void
    {
        $vendorPath = '/app/vendor/vendor-name/theme';
        $this->givenFilesystem($vendorPath, nodeSetup: true);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->io->expects($this->once())->method('warning')->with('Vendor theme detected. Skipping Grunt steps.');
        $this->io->expects($this->once())->method('newLine')->with(2);
        $this->gruntTaskRunner->expects($this->never())->method('runTasks');
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');

        $this->assertTrue($this->builder->build('Vendor/theme', $vendorPath, $this->io, $this->output, false));
    }

    public function testMissingNodeSetupIsAnnouncedOnlyInVerboseMode(): void
    {
        $this->givenFilesystem($this->themePath, nodeSetup: false);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->gruntTaskRunner->expects($this->never())->method('runTasks');
        $noteMessage = 'No Node.js/Grunt setup detected. Skipping Grunt steps.';
        $this->io->expects($this->once())->method('note')->with($noteMessage);

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
    }

    public function testMissingNodeSetupStaysSilentWhenNotVerbose(): void
    {
        $this->givenFilesystem($this->themePath, nodeSetup: false);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->io->expects($this->never())->method('note');

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildFailsWhenSymlinkCleaningFails(): void
    {
        $this->givenFilesystem($this->themePath, nodeSetup: true);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);
        $this->gruntTaskRunner->expects($this->never())->method('runTasks');
        $this->staticContentDeployer->expects($this->never())->method('deploy');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsWithExactWarningWhenOutOfSync(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->with('.')->willReturn(false);
        $this->nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with('Node modules out of sync, missing, or no lock file found. Installing...');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
    }

    public function testAutoRepairSkipsInstallAndOutdatedCheckWhenQuietAndInSync(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->nodePackageManager->expects($this->never())->method('installNodeModules');

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, false));
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
        $this->io->expects($this->once())->method('warning')->with('Grunt not found globally. Installing grunt...');
        $this->io->expects($this->once())->method('success')->with('Grunt installed successfully.');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
    }

    public function testGruntInstallFailureReportsExactError(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('registry down'));
        $this->io->expects($this->once())->method('error')->with('Failed to install grunt: registry down');

        $this->assertFalse($this->builder->autoRepair($this->themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchRejectsVendorThemesWithExactError(): void
    {
        $vendorPath = '/app/vendor/vendor-name/theme';
        $this->givenFilesystem($vendorPath, nodeSetup: true);
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with(
                'Watch mode is not supported for vendor themes. Vendor themes are read-only and '
                . 'should have pre-built assets.',
            );
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $this->assertFalse($this->builder->watch('Vendor/theme', $vendorPath, $this->io, $this->output, false));
    }

    public function testWatchRequiresNodeSetupWithExactError(): void
    {
        $this->givenFilesystem($this->themePath, nodeSetup: false);
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with(
                'Watch mode requires Node.js/Grunt setup. No package.json, package-lock.json, '
                . 'node_modules, or grunt-config.json found.',
            );
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    /**
     * Configure the file driver: valid standard theme, node setup toggled.
     */
    private function givenFilesystem(string $themePath, bool $nodeSetup): void
    {
        $this->fileDriver
            ->method('isExists')
            ->willReturnCallback(static function (string $path) use ($themePath, $nodeSetup): bool {
                if ($path === $themePath . '/theme.xml') {
                    return true;
                }
                if ($path === $themePath . '/web/tailwind') {
                    return false;
                }
                return $nodeSetup && $path === './package.json';
            });
    }
}
