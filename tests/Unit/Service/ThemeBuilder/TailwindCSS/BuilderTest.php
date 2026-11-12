<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder\TailwindCSS;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\Hyva\HyvaThemeDetector;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\TailwindCSS\Builder;
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
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', true],
        ]);
        $fileDriver->expects($this->once())->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/theme.xml')
            ->willReturn('<theme><title>Custom</title></theme>');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertTrue($builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsFalseForHyvaThemeXml(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', true],
            ['app/design/frontend/Vendor/theme/composer.json', false],
        ]);
        $fileDriver->expects($this->once())->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/theme.xml')
            ->willReturn('<theme><title>Hyva</title></theme>');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsTrueForNonHyvaComposerJson(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', false],
            ['app/design/frontend/Vendor/theme/composer.json', true],
        ]);
        $fileDriver->expects($this->once())->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/composer.json')
            ->willReturn(json_encode(['name' => 'vendor/custom-theme']));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertTrue($builder->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsFalseForHyvaComposerJson(): void
    {
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            ['app/design/frontend/Vendor/theme/web/tailwind', true],
            ['app/design/frontend/Vendor/theme/theme.xml', false],
            ['app/design/frontend/Vendor/theme/composer.json', true],
        ]);
        $fileDriver->expects($this->once())->method('fileGetContents')
            ->with('app/design/frontend/Vendor/theme/composer.json')
            ->willReturn(json_encode(['name' => 'hyva-themes/theme-default']));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->detect('app/design/frontend/Vendor/theme'));
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

    public function testDetectReturnsFalseForHyvaComposerNameWithNeutralThemeXml(): void
    {
        $fileDriver = $this->createStub(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturnMap([
            ['app/design/frontend/Vendor/theme/theme.xml', null, null, '<theme><title>Custom</title></theme>'],
            [
                'app/design/frontend/Vendor/theme/composer.json',
                null,
                null,
                json_encode(['name' => 'hyva-themes/theme-default']),
            ],
        ]);

        $this->assertFalse($this->createBuilder(fileDriver: $fileDriver)->detect('app/design/frontend/Vendor/theme'));
    }

    public function testDetectReturnsFalseForHyvaThemeXmlWithNeutralComposerName(): void
    {
        $fileDriver = $this->createStub(File::class);
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturnMap([
            ['app/design/frontend/Vendor/theme/theme.xml', null, null, '<theme><parent>Hyva/default</parent></theme>'],
            [
                'app/design/frontend/Vendor/theme/composer.json',
                null,
                null,
                json_encode(['name' => 'vendor/custom-theme']),
            ],
        ]);

        $this->assertFalse($this->createBuilder(fileDriver: $fileDriver)->detect('app/design/frontend/Vendor/theme'));
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

    private function configureSuccessfulDetection(File&MockObject $fileDriver, string $themePath): void
    {
        $fileDriver->method('isExists')->willReturnMap([
            [$themePath . '/web/tailwind', true],
            [$themePath . '/theme.xml', true],
        ]);
        $fileDriver->expects($this->once())->method('fileGetContents')
            ->with($themePath . '/theme.xml')
            ->willReturn('<theme><title>Custom</title></theme>');
    }

    public function testBuildReturnsFalseWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $successList = [];
        $this->assertFalse(
            $builder->build('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testBuildReturnsFalseWhenStaticContentCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $builder = $this->createBuilder(fileDriver: $fileDriver, nodePackageManager: $nodePackageManager);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenSymlinkCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(false);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenTailwindDirectoryNotFound(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildRunsNpmBuildAndDeploysSuccessfully(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->once())->method('execute')
            ->with('cd %s && npm run build --quiet', [$themePath . '/web/tailwind']);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildUsesVerboseNpmCommandAndReportsSuccess(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->once())->method('execute')
            ->with('cd %s && npm run build', [$themePath . '/web/tailwind']);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->atLeastOnce())->method('success');
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $themePath, $io, $this->output, true));
    }

    public function testBuildHandlesShellExecuteException(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm failed'));
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error')
            ->with($this->stringContains('Failed to build custom TailwindCSS theme'));
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenDeployFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(false);
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->never())->method('clean');
        $builder = $this->createBuilder(fileDriver: $fileDriver, cacheCleaner: $cacheCleaner);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testBuildReturnsFalseWhenCacheCleanFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairInstallsWhenOutOfSync(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(false);
        $nodePackageManager->expects($this->once())->method('installNodeModules')->willReturn(true);
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($themePath, $this->io, $this->output, false));
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
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $nodePackageManager->expects($this->never())->method('installNodeModules');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($themePath, $this->io, $this->output, false));
    }

    public function testAutoRepairChecksOutdatedInVerboseMode(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $nodePackageManager->expects($this->once())->method('checkOutdatedPackages');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($themePath, $this->io, $this->output, true));
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
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testWatchReturnsFalseWhenTailwindDirectoryMissing(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    // -------------------------------------------------------------------------
    // Mutation hardening: exact messages, commands and branch boundaries
    // -------------------------------------------------------------------------

    public function testBuildUsesVerboseNpmCommandWithExactMessages(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm run build', [$themePath . '/web/tailwind']);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('text')->with('Running npm build...');
        $io
            ->expects($this->once())
            ->method('success')
            ->with('Custom TailwindCSS theme build completed successfully.');
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $themePath, $io, $this->output, true));
    }

    public function testQuietBuildUsesQuietNpmCommandAndStaysSilent(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->staticContentDeployer->method('deploy')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npm run build --quiet', [$themePath . '/web/tailwind']);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('text');
        $io->expects($this->never())->method('success');
        $builder = $this->createBuilder(shell: $shell, fileDriver: $fileDriver);

        $this->assertTrue($builder->build('Vendor/theme', $themePath, $io, $this->output, false));
    }

    public function testMissingTailwindBuildDirectoryReportsExactError(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $themePath . '/web/tailwind');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $io, $this->output, false));
    }

    public function testNpmBuildFailureReportsExactError(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npm exploded'));
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Failed to build custom TailwindCSS theme: npm exploded');
        $staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $staticContentDeployer->expects($this->never())->method('deploy');
        $builder = $this->createBuilder(fileDriver: $fileDriver, staticContentDeployer: $staticContentDeployer);

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $io, $this->output, false));
    }

    public function testAutoRepairUsesTailwindPathAndExactWarning(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager
            ->expects($this->once())
            ->method('isNodeModulesInSync')
            ->with($themePath . '/web/tailwind')
            ->willReturn(false);
        $nodePackageManager
            ->expects($this->once())
            ->method('installNodeModules')
            ->with($themePath . '/web/tailwind')
            ->willReturn(true);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('warning')
            ->with('Node modules out of sync or missing. Installing npm dependencies...');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($themePath . '/', $io, $this->output, true));
    }

    public function testAutoRepairChecksOutdatedPackagesOnlyInVerboseMode(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $nodePackageManager
            ->expects($this->once())
            ->method('checkOutdatedPackages')
            ->with($themePath . '/web/tailwind');
        $builder = $this->createBuilder(nodePackageManager: $nodePackageManager);

        $this->assertTrue($builder->autoRepair($themePath, $this->io, $this->output, true));
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

        $this->assertTrue($builder->autoRepair('app/design/frontend/Vendor/theme', $io, $this->output, false));
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
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $builder = $this->createBuilder(
            staticContentCleaner: $staticContentCleaner,
            nodePackageManager: $nodePackageManager,
        );

        $this->assertFalse(
            $builder->build('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testMissingTailwindDirectorySkipsNpmBuild(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $this->nodePackageManager->method('isNodeModulesInSync')->willReturn(true);
        $this->symlinkCleaner->method('cleanSymlinks')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $staticContentDeployer = $this->createMock(StaticContentDeployer::class);
        $staticContentDeployer->expects($this->never())->method('deploy');
        $builder = $this->createBuilder(
            shell: $shell,
            fileDriver: $fileDriver,
            staticContentDeployer: $staticContentDeployer,
        );

        $this->assertFalse($builder->build('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testWatchStopsBeforeCleaningWhenDetectFails(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $staticContentCleaner = $this->createMock(StaticContentCleaner::class);
        $staticContentCleaner->expects($this->never())->method('cleanIfNeeded');
        $builder = $this->createBuilder(staticContentCleaner: $staticContentCleaner);

        $this->assertFalse(
            $builder->watch('Vendor/theme', 'app/design/frontend/Vendor/theme', $this->io, $this->output, false),
        );
    }

    public function testWatchStopsBeforeDirectoryCheckWhenCleaningFails(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(false);
        $fileDriver->expects($this->never())->method('isDirectory');
        $builder = $this->createBuilder(fileDriver: $fileDriver);

        $this->assertFalse($builder->watch('Vendor/theme', $themePath, $this->io, $this->output, false));
    }

    public function testWatchReportsExactErrorForMissingTailwindDirectory(): void
    {
        $themePath = 'app/design/frontend/Vendor/theme';
        $fileDriver = $this->createMock(File::class);
        $this->configureSuccessfulDetection($fileDriver, $themePath);
        $this->staticContentCleaner->method('cleanIfNeeded')->willReturn(true);
        $fileDriver->method('isDirectory')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $io
            ->expects($this->once())
            ->method('error')
            ->with('Tailwind directory not found in: ' . $themePath . '/web/tailwind');
        $nodePackageManager = $this->createMock(NodePackageManager::class);
        $nodePackageManager->expects($this->never())->method('isNodeModulesInSync');
        $io->expects($this->never())->method('text');
        $builder = $this->createBuilder(fileDriver: $fileDriver, nodePackageManager: $nodePackageManager);

        $this->assertFalse($builder->watch('Vendor/theme', $themePath . '/', $io, $this->output, false));
    }
}
