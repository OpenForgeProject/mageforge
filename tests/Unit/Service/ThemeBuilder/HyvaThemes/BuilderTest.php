<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder\HyvaThemes;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\HyvaThemes\Builder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuilderTest extends TestCase
{
    private Shell&MockObject $shell;
    private File&MockObject $fileDriver;
    private StaticContentDeployer&MockObject $staticContentDeployer;
    private StaticContentCleaner&MockObject $staticContentCleaner;
    private CacheCleaner&MockObject $cacheCleaner;
    private SymlinkCleaner&MockObject $symlinkCleaner;
    private NodePackageManager&MockObject $nodePackageManager;
    private SymfonyStyle&MockObject $io;
    private OutputInterface&MockObject $output;
    private Builder $builder;
    private string $themePath = 'app/design/frontend/Vendor/hyva-theme';

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
        $this->assertSame('HyvaThemes', $this->builder->getName());
    }

    public function testDetectReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertFalse($this->builder->detect($this->themePath));
    }

    public function testDetectReturnsTrueForHyvaThemeXml(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($this->themePath . '/theme.xml')
            ->willReturn('<theme><title>Hyva</title></theme>');

        $this->assertTrue($this->builder->detect($this->themePath));
    }

    public function testDetectReturnsFalseForNonHyvaThemeXml(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/composer.json', false],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($this->themePath . '/theme.xml')
            ->willReturn('<theme><title>Custom</title></theme>');

        $this->assertFalse($this->builder->detect($this->themePath));
    }

    public function testDetectReturnsTrueForHyvaComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', false],
            [$this->themePath . '/composer.json', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($this->themePath . '/composer.json')
            ->willReturn(json_encode(['name' => 'hyva-themes/theme-default']));

        $this->assertTrue($this->builder->detect($this->themePath));
    }

    public function testDetectReturnsFalseForNonHyvaComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', false],
            [$this->themePath . '/composer.json', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($this->themePath . '/composer.json')
            ->willReturn(json_encode(['name' => 'vendor/custom-theme']));

        $this->assertFalse($this->builder->detect($this->themePath));
    }

    private function configureSuccessfulDetection(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')
            ->with($this->themePath . '/theme.xml')
            ->willReturn('<theme><title>Hyva</title></theme>');
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

    public function testBuildReturnsFalseWhenSymlinkCleanFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);
        $this->shell->expects($this->never())->method('execute');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenHyvaConfigGenerationFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('generate failed'));
        $this->io->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to generate Hyv'));

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildHandlesThemeBuildException(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);

        $callCount = 0;
        $this->shell->method('execute')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 2) {
                throw new \RuntimeException('build failed');
            }
            return '';
        });
        $this->io->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to build Hyv'));

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildRunsFullPipelineSuccessfully(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->expects($this->exactly(2))->method('execute');
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
    }

    public function testBuildReturnsFalseWhenDeployFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(false);
        $this->cacheCleaner->expects($this->never())->method('clean');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsWhenOutOfSync(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
    }

    public function testAutoRepairReturnsFalseWhenInstallFails(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->builder->autoRepair($this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenStaticContentCleanFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenAutoRepairFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }
}
