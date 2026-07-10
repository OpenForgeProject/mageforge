<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder\TailwindCSS;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\TailwindCSS\Builder;
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

    protected function setUp(): void
    {
        $this->shell = $this->createMock(Shell::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $this->staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $this->cacheCleaner = $this->createMock(CacheCleaner::class);
        $this->symlinkCleaner = $this->createMock(SymlinkCleaner::class);
        $this->nodePackageManager = $this->createMock(NodePackageManager::class);
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
        );
    }

    public function testGetNameReturnsThemeName(): void
    {
        $this->assertSame('TailwindCSS', $this->builder->getName());
    }

    public function testDetectReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertFalse($this->builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsTrueForNonHyvaThemeXml(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/theme.xml')
            ->willReturn('<theme><title>Custom</title></theme>');

        $this->assertTrue($this->builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsFalseForHyvaThemeXml(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', true],
            ['app/design/frontend/Vendor/theme/composer.json', false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/theme.xml')
            ->willReturn('<theme><title>Hyva</title></theme>');

        $this->assertFalse($this->builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsTrueForNonHyvaComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', false],
            ['app/design/frontend/Vendor/theme/composer.json', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/composer.json')
            ->willReturn(json_encode(['name' => 'vendor/custom-theme']));

        $this->assertTrue($this->builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsFalseForHyvaComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', false],
            ['app/design/frontend/Vendor/theme/composer.json', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/composer.json')
            ->willReturn(json_encode(['name' => 'hyva-themes/theme-default']));

        $this->assertFalse($this->builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsFalseWhenNeitherFilePresent(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', false],
            ['app/design/frontend/Vendor/theme/composer.json', false],
        ]);

        $this->assertFalse($this->builder->detect('app/design/frontend/Vendor/theme'));
    }

    private function configureSuccessfulDetection(string $themePath): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$themePath . '/web/tailwind', true],
            [$themePath . '/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($themePath . '/theme.xml')
            ->willReturn('<theme><title>Custom</title></theme>');
    }

    public function testBuildReturnsFalseWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $successList = [];
        $this->assertFalse(
            $this->builder->build('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testBuildReturnsFalseWhenStaticContentCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenSymlinkCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);
        $this->shell->expects($this->never())->method('execute');

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenTailwindDirectoryNotFound(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildRunsNpmBuildAndDeploysSuccessfully(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->expects($this->once())->method('execute')
            ->with('cd %s && npm run build --quiet', [$themePath . '/web/tailwind']);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);

        $this->assertTrue($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildUsesVerboseNpmCommandAndReportsSuccess(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->expects($this->once())->method('execute')
            ->with('cd %s && npm run build', [$themePath . '/web/tailwind']);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->io->expects($this->atLeastOnce())->method('success');

        $this->assertTrue($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, true));
    }

    public function testBuildHandlesShellExecuteException(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm failed'));
        $this->io->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to build custom TailwindCSS theme'));

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenDeployFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(false);
        $this->cacheCleaner->expects($this->never())->method('clean');

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenCacheCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(false);

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsWhenOutOfSync(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);

        $this->assertTrue($this->builder->autoRepair($themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairReturnsFalseWhenInstallFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->builder->autoRepair($themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairSkipsInstallWhenInSync(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->nodePackageManager->expects($this->never())->method('installNodeModules');

        $this->assertTrue($this->builder->autoRepair($themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairChecksOutdatedInVerboseMode(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->nodePackageManager->expects($this->once())->method('checkOutdatedPackages');

        $this->assertTrue($this->builder->autoRepair($themePath, $this->io, $this->output, true));
    }

    public function testWatchReturnsFalseWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertFalse(
            $this->builder->watch('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testWatchReturnsFalseWhenStaticContentCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    // -------------------------------------------------------------------------
    // Mutation hardening: exact messages, commands and branch boundaries
    // -------------------------------------------------------------------------

    public function testBuildUsesVerboseNpmCommandWithExactMessages(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm run build', [$themePath . '/web/tailwind']);
        $this->io->expects($this->once())->method('text')->with('Running npm build...');
        $this->io
            ->expects($this->once())
            ->method('success')
            ->with('Custom TailwindCSS theme build completed successfully.');

        $this->assertTrue($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, true));
    }

    public function testQuietBuildUsesQuietNpmCommandAndStaysSilent(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm run build --quiet', [$themePath . '/web/tailwind']);
        $this->io->expects($this->never())->method('text');
        $this->io->expects($this->never())->method('success');

        $this->assertTrue($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testMissingTailwindBuildDirectoryReportsExactError(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $themePath . '/web/tailwind');

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testNpmBuildFailureReportsExactError(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm exploded'));
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to build custom TailwindCSS theme: npm exploded');
        $this->staticContentDeployer->expects($this->never())->method('deploy');

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairUsesTailwindPathAndExactWarning(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->nodePackageManager
            ->expects($this->once())
            ->method('isNodeModulesInSync')
            ->with($themePath . '/web/tailwind')
            ->willReturn(false);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('installNodeModules')
            ->with($themePath . '/web/tailwind')
            ->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with('Node modules out of sync or missing. Installing npm dependencies...');

        $this->assertTrue($this->builder->autoRepair($themePath . '/', $this->io, $this->output, true));
    }

    public function testAutoRepairChecksOutdatedPackagesOnlyInVerboseMode(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('checkOutdatedPackages')
            ->with($themePath . '/web/tailwind');

        $this->assertTrue($this->builder->autoRepair($themePath, $this->io, $this->output, true));
    }

    public function testQuietAutoRepairSkipsOutdatedCheckAndWarning(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(true);
        $this->nodePackageManager->expects($this->never())->method('checkOutdatedPackages');
        $this->io->expects($this->never())->method('warning');

        $this->assertTrue(
            $this->builder->autoRepair('app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testDetectNormalizesTrailingSlashAndRequiresTailwindDirectory(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->fileDriver->method('isExists')->willReturnMap([
            [$themePath . '/web/tailwind', false],
            [$themePath . '/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')->willReturn('<theme><title>Custom</title></theme>');

        $this->assertFalse($this->builder->detect($themePath . '/'));
    }

    public function testBuildStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');

        $this->assertFalse(
            $this->builder->build('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testMissingTailwindDirectorySkipsNpmBuild(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->shell->expects($this->never())->method('execute');
        $this->staticContentDeployer->expects($this->never())->method('deploy');

        $this->assertFalse($this->builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $this->assertFalse(
            $this->builder->watch('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testWatchStopsBeforeDirectoryCheckWhenCleaningFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $this->fileDriver->expects($this->never())->method('isDirectory');

        $this->assertFalse($this->builder->watch('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testWatchReportsExactErrorForMissingTailwindDirectory(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $this->configureSuccessfulDetection($themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $themePath . '/web/tailwind');
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $this->io->expects($this->never())->method('text');

        $this->assertFalse($this->builder->watch('Vendor/theme', $themePath . '/', $this->io, $this->output, false));
    }
}
