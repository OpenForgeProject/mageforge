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
        $this->shell->method('execute')->willReturn('');
        $this->fileDriver->expects($this->once())
            ->method('isDirectory')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(false);
        $this->io->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $this->themePath . '/web/tailwind');

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
        $this->fileDriver->expects($this->once())
            ->method('isDirectory')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(true);
        $executedCommands = [];
        $this->shell->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (string $command, array $args = []) use (&$executedCommands): string {
                $executedCommands[] = [$command, $args];
                return '';
            });
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $successMessages = [];
        $this->io->method('success')->willReturnCallback(function (string $message) use (&$successMessages): void {
            $successMessages[] = $message;
        });
        $textMessages = [];
        $this->io->method('text')->willReturnCallback(function (string $message) use (&$textMessages): void {
            $textMessages[] = $message;
        });

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
        $this->assertSame(
            [
                ['bin/magento hyva:config:generate', []],
                ['cd %s && npm run build', [$this->themePath . '/web/tailwind']],
            ],
            $executedCommands,
        );
        $this->assertSame(['Generating Hyvä configuration...', 'Running npm build...'], $textMessages);
        $this->assertSame(
            ['Hyvä configuration generated successfully.', 'Hyvä theme build completed successfully.'],
            $successMessages,
        );
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

    // -------------------------------------------------------------------------
    // Mutation hardening: exact messages, commands and branch boundaries
    // -------------------------------------------------------------------------

    public function testDetectMatchesHyvaCaseInsensitivelyAndNormalizesSlash(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')->willReturn('<theme><title>HYVA Default</title></theme>');

        $this->assertTrue($this->builder->detect($this->themePath . '/'));
    }

    public function testGeneratesHyvaConfigWithExactCommandAndVerboseMessages(): void
    {
        $this->configureSuccessfulDetection();
        $this->givenPipelineUpToConfigGeneration();

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);

        $texts = [];
        $this->io
            ->method('text')
            ->willReturnCallback(function (string $message) use (&$texts): void {
                $texts[] = $message;
            });
        $successes = [];
        $this->io
            ->method('success')
            ->willReturnCallback(function (string $message) use (&$successes): void {
                $successes[] = $message;
            });

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
        $this->assertSame('bin/magento hyva:config:generate', $executedCommands[0]);
        $this->assertSame('cd %s && npm run build', $executedCommands[1]);
        $this->assertSame(['Generating Hyvä configuration...', 'Running npm build...'], $texts);
        $this->assertSame(
            ['Hyvä configuration generated successfully.', 'Hyvä theme build completed successfully.'],
            $successes,
        );
    }

    public function testQuietBuildUsesQuietNpmCommandAndStaysSilent(): void
    {
        $this->configureSuccessfulDetection();
        $this->givenPipelineUpToConfigGeneration();

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $this->io->expects($this->never())->method('text');
        $this->io->expects($this->never())->method('success');

        $this->assertTrue($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
        $this->assertSame('cd %s && npm run build --quiet', $executedCommands[1]);
    }

    public function testConfigGenerationFailureReportsExactError(): void
    {
        $this->configureSuccessfulDetection();
        $this->givenPipelineUpToConfigGeneration();
        $this->shell->method('execute')->willThrowException(new \RuntimeException('config error'));
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to generate Hyvä configuration: config error');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testMissingTailwindDirectoryReportsExactError(): void
    {
        $this->configureSuccessfulDetection();
        $this->givenPipelineUpToConfigGeneration();
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $this->themePath . '/web/tailwind');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testNpmBuildFailureReportsExactError(): void
    {
        $this->configureSuccessfulDetection();
        $this->givenPipelineUpToConfigGeneration();
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell
            ->method('execute')
            ->willReturnCallback(static function (string $command): string {
                if (str_contains($command, 'npm run build')) {
                    throw new \RuntimeException('npm exploded');
                }
                return '';
            });
        $this->io->expects($this->once())->method('error')->with('Failed to build Hyvä theme: npm exploded');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairUsesTailwindPathAndExactWarning(): void
    {
        $this->nodePackageManager
            ->expects($this->once())
            ->method('isNodeModulesInSync')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(false);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('installNodeModules')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with('Node modules out of sync or missing. Installing dependencies...');

        $this->assertTrue($this->builder->autoRepair($this->themePath . '/', $this->io, $this->output, true));
    }

    public function testAutoRepairChecksOutdatedPackagesOnlyInVerboseMode(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('checkOutdatedPackages')
            ->with($this->themePath . '/web/tailwind');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, true));
    }

    public function testQuietAutoRepairSkipsOutdatedCheckAndWarning(): void
    {
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(true);
        $this->nodePackageManager->expects($this->never())->method('checkOutdatedPackages');
        $this->io->expects($this->never())->method('warning');

        $this->assertTrue($this->builder->autoRepair($this->themePath, $this->io, $this->output, false));
    }

    /**
     * Static content and symlink cleaning succeed so the build reaches config generation.
     */
    private function givenPipelineUpToConfigGeneration(): void
    {
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
    }

    public function testDetectRequiresTailwindDirectoryEvenForHyvaThemeXml(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', false],
            [$this->themePath . '/theme.xml', true],
        ]);
        $this->fileDriver->method('fileGetContents')->willReturn('<theme><title>Hyva</title></theme>');

        $this->assertFalse($this->builder->detect($this->themePath));
    }

    public function testBuildStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testMissingTailwindDirectorySkipsNpmBuild(): void
    {
        $this->configureSuccessfulDetection();
        $this->givenPipelineUpToConfigGeneration();
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $this->staticContentDeployer->expects($this->never())->method('deploy');

        $this->assertFalse($this->builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
        $this->assertSame(['bin/magento hyva:config:generate'], $executedCommands, 'npm build must not run');
    }

    public function testWatchStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->staticContentCleaner->expects($this->never())->method('cleanIfNeeded');

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeAutoRepairWhenCleaningFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $this->nodePackageManager->expects($this->never())->method('isNodeModulesInSync');

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeDirectoryCheckWhenAutoRepairFails(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);
        $this->fileDriver->expects($this->never())->method('isDirectory');

        $this->assertFalse($this->builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReportsExactErrorForMissingTailwindDirectory(): void
    {
        $this->configureSuccessfulDetection();
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $this->themePath . '/web/tailwind');
        $this->io->expects($this->never())->method('text');

        $result = $this->builder->watch('Vendor/theme', $this->themePath . '/', $this->io, $this->output, false);
        $this->assertFalse($result);
    }
}
