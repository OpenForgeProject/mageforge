<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder\HyvaThemes;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\Hyva\HyvaThemeDetector;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\HyvaThemes\Builder;
use PHPUnit\Framework\MockObject\MockObject;
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
    private string $themePath = 'app/design/frontend/Vendor/hyva-theme';

    protected function setUp(): void
    {
        $this->shell = $this->createStub(Shell::class);
        $this->fileDriver = $this->createStub(File::class);
        $this->staticContentDeployer = $this->createStub(StaticContentDeployer::class);
        $this->staticContentCleaner = $this->createStub(StaticContentCleaner::class);
        $this->cacheCleaner = $this->createStub(CacheCleaner::class);
        $this->symlinkCleaner = $this->createStub(SymlinkCleaner::class);
        $this->nodePackageManager = $this->createStub(NodePackageManager::class);
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
    ): Builder {
        return new Builder(
            $shell ?? $this->shell,
            $fileDriver ?? $this->fileDriver,
            $staticContentDeployer ?? $this->staticContentDeployer,
            $staticContentCleaner ?? $this->staticContentCleaner,
            $cacheCleaner ?? $this->cacheCleaner,
            $symlinkCleaner ?? $this->symlinkCleaner,
            $nodePackageManager ?? $this->nodePackageManager,
            new HyvaThemeDetector($fileDriver ?? $this->fileDriver),
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
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
        ]);
        $fileDriver->expects($this->once())
            ->method('fileGetContents')
            ->with($this->themePath . '/theme.xml')
            ->willReturn('<theme><title>Hyva</title></theme>');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertTrue($builder->detect($this->themePath));
    }

    public function testDetectReturnsFalseForNonHyvaThemeXml(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
            [$this->themePath . '/composer.json', false],
        ]);
        $fileDriver->expects($this->once())
            ->method('fileGetContents')
            ->with($this->themePath . '/theme.xml')
            ->willReturn('<theme><title>Custom</title></theme>');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->detect($this->themePath));
    }

    public function testDetectReturnsTrueForHyvaComposerJson(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', false],
            [$this->themePath . '/composer.json', true],
        ]);
        $fileDriver->expects($this->once())
            ->method('fileGetContents')
            ->with($this->themePath . '/composer.json')
            ->willReturn(json_encode(['name' => 'hyva-themes/theme-default']));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertTrue($builder->detect($this->themePath));
    }

    public function testDetectReturnsFalseForNonHyvaComposerJson(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', false],
            [$this->themePath . '/composer.json', true],
        ]);
        $fileDriver->expects($this->once())
            ->method('fileGetContents')
            ->with($this->themePath . '/composer.json')
            ->willReturn(json_encode(['name' => 'vendor/custom-theme']));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->detect($this->themePath));
    }

    private function configureSuccessfulDetection(File&MockObject $fileDriver): void
    {
        $fileDriver->method('isExists')->willReturnMap([
            [$this->themePath . '/web/tailwind', true],
            [$this->themePath . '/theme.xml', true],
        ]);
        $fileDriver->expects($this->once())
            ->method('fileGetContents')
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
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenSymlinkCleanFails(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenHyvaConfigGenerationFails(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('generate failed'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to generate Hyv'));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $this->shell->method('execute')->willReturn('');
        $fileDriver->expects($this->once())
            ->method('isDirectory')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $this->themePath . '/web/tailwind');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testBuildHandlesThemeBuildException(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);

        $callCount = 0;
        $this->shell->method('execute')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 2) {
                throw new \RuntimeException('build failed');
            }
            return '';
        });
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to build Hyv'));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testBuildRunsFullPipelineSuccessfully(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->expects($this->once())
            ->method('isDirectory')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(true);
        $executedCommands = [];
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->exactly(2))
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
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
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
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(false);
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->never())->method('clean');
        $builder = $this->createBuilder(fileDriver: $fileDriver, cacheCleaner: $cacheCleaner);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsWhenOutOfSync(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath, $this->io, $this->output, true));
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
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenAutoRepairFails(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
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
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->givenPipelineUpToConfigGeneration();

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $fileDriver->method('isDirectory')->willReturn(true);
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
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, true));
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
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->givenPipelineUpToConfigGeneration();

        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('text');
        $io->expects($this->never())->method('success');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
        $this->assertSame('cd %s && npm run build --quiet', $executedCommands[1]);
    }

    public function testConfigGenerationFailureReportsExactError(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->givenPipelineUpToConfigGeneration();
        $this->shell->method('execute')->willThrowException(new \RuntimeException('config error'));
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to generate Hyvä configuration: config error');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testMissingTailwindDirectoryReportsExactError(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->givenPipelineUpToConfigGeneration();
        $fileDriver->method('isDirectory')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $this->themePath . '/web/tailwind');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testNpmBuildFailureReportsExactError(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->givenPipelineUpToConfigGeneration();
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->shell
            ->method('execute')
            ->willReturnCallback(static function (string $command): string {
                if (str_contains($command, 'npm run build')) {
                    throw new \RuntimeException('npm exploded');
                }
                return '';
            });
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')->with('Failed to build Hyvä theme: npm exploded');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $io, $this->output, false));
    }

    public function testAutoRepairUsesTailwindPathAndExactWarning(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager
            ->expects($this->once())
            ->method('isNodeModulesInSync')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(false);
        $nodePackageManager
            ->expects($this->once())
            ->method('installNodeModules')
            ->with($this->themePath . '/web/tailwind')
            ->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('warning')
            ->with('Node modules out of sync or missing. Installing dependencies...');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath . '/', $io, $this->output, true));
    }

    public function testAutoRepairChecksOutdatedPackagesOnlyInVerboseMode(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $nodePackageManager
            ->expects($this->once())
            ->method('checkOutdatedPackages')
            ->with($this->themePath . '/web/tailwind');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath, $this->io, $this->output, true));
    }

    public function testQuietAutoRepairSkipsOutdatedCheckAndWarning(): void
    {
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $nodePackageManager->method('installNodeModules')->willReturn(true);
        $nodePackageManager->expects($this->never())->method('checkOutdatedPackages');
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('warning');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($this->themePath, $io, $this->output, false));
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
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $builder = $this->createBuilder(
            staticContentCleaner: $staticContentCleaner,
            nodePackageManager: $nodePackageManager,
        );

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testMissingTailwindDirectorySkipsNpmBuild(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->givenPipelineUpToConfigGeneration();
        $fileDriver->method('isDirectory')->willReturn(false);
        $executedCommands = [];
        $this->shell
            ->method('execute')
            ->willReturnCallback(function (string $command) use (&$executedCommands): string {
                $executedCommands[] = $command;
                return '';
            });
        $staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $staticContentDeployer->expects($this->never())->method('deploy');
        $builder = $this->createBuilder(fileDriver: $fileDriver, staticContentDeployer: $staticContentDeployer);

        $this->assertFalse($builder->build('Vendor/theme', $this->themePath, $this->io, $this->output, false));
        $this->assertSame(['bin/magento hyva:config:generate'], $executedCommands, 'npm build must not run');
    }

    public function testWatchStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeAutoRepairWhenCleaningFails(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $builder = $this->createBuilder(fileDriver: $fileDriver, nodePackageManager: $nodePackageManager);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeDirectoryCheckWhenAutoRepairFails(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);
        $fileDriver->expects($this->never())->method('isDirectory');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $this->themePath, $this->io, $this->output, false));
    }

    public function testWatchReportsExactErrorForMissingTailwindDirectory(): void
    {
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $this->themePath . '/web/tailwind');
        $io->expects($this->never())->method('text');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $result = $builder->watch('Vendor/theme', $this->themePath . '/', $io, $this->output, false);
        $this->assertFalse($result);
    }
}
