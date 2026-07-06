<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\DependencyUpdater;
use OpenForgeProject\MageForge\Service\DependencyUpdateResult;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard\Builder as MagentoStandardBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class DependencyUpdaterTest extends TestCase
{
    private File&MockObject $fileDriver;
    private NodePackageManager&MockObject $nodePackageManager;
    private BuilderPool&MockObject $builderPool;
    private DirectoryList&MockObject $directoryList;
    private SymfonyStyle&MockObject $io;
    private DependencyUpdater $updater;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->nodePackageManager = $this->createMock(NodePackageManager::class);
        $this->builderPool = $this->createMock(BuilderPool::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->directoryList->method('getRoot')->willReturn('/magento-root');
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->updater = new DependencyUpdater(
            $this->fileDriver,
            $this->nodePackageManager,
            $this->builderPool,
            $this->directoryList,
        );
    }

    private function actAsMagentoStandardTheme(): void
    {
        $this->builderPool->method('getBuilder')->willReturn($this->createMock(MagentoStandardBuilder::class));
    }

    /**
     * @return array{name: string, current: string, wanted: string, latest: string, type: string}
     */
    private function outdatedPackage(
        string $name = 'tailwindcss',
        string $current = '3.4.1',
        string $wanted = '3.4.17',
        string $latest = '4.1.5',
        string $type = 'devDependencies',
    ): array {
        return [
            'name' => $name,
            'current' => $current,
            'wanted' => $wanted,
            'latest' => $latest,
            'type' => $type,
        ];
    }

    // -------------------------------------------------------------------------
    // getPackageDirectories / isVendorTheme
    // -------------------------------------------------------------------------

    public function testFindsPackageJsonInTailwindAndThemeRoot(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', true],
        ]);

        $this->assertSame(
            ['/theme/web/tailwind', '/theme'],
            $this->updater->getPackageDirectories('/theme/'),
        );
    }

    public function testReturnsEmptyListWhenThemeHasNoPackageJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertSame([], $this->updater->getPackageDirectories('/theme'));
    }

    public function testDetectsVendorThemes(): void
    {
        $this->assertTrue($this->updater->isVendorTheme('/magento-root/vendor/hyva-themes/magento2-default-theme'));
        $this->assertFalse($this->updater->isVendorTheme('/magento-root/app/design/frontend/Vendor/theme'));
        $this->assertFalse($this->updater->isVendorTheme('/magento-root/app/design/frontend/vendor/theme'));
    }

    // -------------------------------------------------------------------------
    // updateThemeDependencies
    // -------------------------------------------------------------------------

    public function testSkipsVendorThemesWithWarning(): void
    {
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('managed by Composer'));
        $this->nodePackageManager->expects($this->never())->method('getOutdatedPackages');

        $this->assertSame(DependencyUpdateResult::Skipped, $this->updater->updateThemeDependencies(
            'Hyva/default',
            '/magento-root/vendor/hyva-themes/magento2-default-theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testSkipsNonStandardThemesWithoutOwnPackageJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->builderPool->method('getBuilder')->willReturn(null);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('has no own package.json'));
        $this->nodePackageManager->expects($this->never())->method('getOutdatedPackages');

        $this->assertSame(DependencyUpdateResult::Skipped, $this->updater->updateThemeDependencies(
            'Vendor/csstheme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testReportsUpToDatePackagesWithoutUpdating(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager->method('getOutdatedPackages')->willReturn([]);
        $this->nodePackageManager->expects($this->never())->method('updatePackages');
        $this->io
            ->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('are up to date'));

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testInstallsNodeModulesFirstWhenMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->with('/theme/web/tailwind/node_modules')->willReturn(false);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('installNodeModules')
            ->with('/theme/web/tailwind', $this->io, false)
            ->willReturn(true);
        $this->nodePackageManager->method('getOutdatedPackages')->willReturn([]);

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testFailsWhenInitialInstallFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(false);
        $this->nodePackageManager->method('installNodeModules')->willReturn(false);
        $this->nodePackageManager->expects($this->never())->method('getOutdatedPackages');

        $this->assertSame(DependencyUpdateResult::Failed, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testUpdatesWithinSemverRangesByDefault(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->method('getOutdatedPackages')
            ->willReturnOnConsecutiveCalls([$this->outdatedPackage()], []);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('updatePackages')
            ->with('/theme/web/tailwind')
            ->willReturn(true);
        $this->nodePackageManager->expects($this->never())->method('installPackageVersions');

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testHintsAtLatestOptionWhenMajorUpdatesRemain(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->method('getOutdatedPackages')
            ->willReturnOnConsecutiveCalls([$this->outdatedPackage()], [$this->outdatedPackage(current: '3.4.17')]);
        $this->nodePackageManager->method('updatePackages')->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('note')
            ->with($this->stringContains('--latest'));

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testDryRunOnlyReportsWithoutChangingAnything(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->nodePackageManager->method('getOutdatedPackages')->willReturn([$this->outdatedPackage()]);
        $this->nodePackageManager->expects($this->never())->method('installNodeModules');
        $this->nodePackageManager->expects($this->never())->method('updatePackages');
        $this->nodePackageManager->expects($this->never())->method('installPackageVersions');
        $this->io
            ->expects($this->once())
            ->method('note')
            ->with($this->stringContains('Dry run: would update 1 package(s)'));

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            true,
        ));
    }

    public function testLatestModeInstallsPinnedVersionsGroupedByType(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->method('getOutdatedPackages')
            ->willReturnOnConsecutiveCalls(
                [
                    $this->outdatedPackage(),
                    $this->outdatedPackage(name: 'postcss', current: '8.4.0', wanted: '8.5.0', latest: '8.5.0'),
                    $this->outdatedPackage(name: 'alpinejs', current: '3.13.0', latest: '3.14.9', type: 'dependencies'),
                ],
                [],
            );
        $installedGroups = [];
        $this->nodePackageManager
            ->method('installPackageVersions')
            ->willReturnCallback(
                function (string $path, string $type, array $packages) use (&$installedGroups): bool {
                    $installedGroups[$type] = $packages;
                    $this->assertSame('/theme/web/tailwind', $path);
                    return true;
                },
            );
        $this->nodePackageManager->expects($this->never())->method('updatePackages');

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            true,
            false,
        ));
        $this->assertSame([
            'devDependencies' => ['tailwindcss' => '4.1.5', 'postcss' => '8.5.0'],
            'dependencies' => ['alpinejs' => '3.14.9'],
        ], $installedGroups);
    }

    public function testLatestModeSkipsUnsafePackageSpecsWithWarning(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->method('getOutdatedPackages')
            ->willReturnOnConsecutiveCalls(
                [
                    $this->outdatedPackage(name: 'evil; rm -rf /', latest: '1.0.0'),
                    $this->outdatedPackage(name: 'nolatest', latest: '-'),
                ],
                [],
            );
        $this->nodePackageManager->expects($this->never())->method('installPackageVersions');
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('unexpected name or version'));

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            true,
            false,
        ));
    }

    public function testFailsWhenNpmUpdateFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager->method('getOutdatedPackages')->willReturn([$this->outdatedPackage()]);
        $this->nodePackageManager->method('updatePackages')->willReturn(false);

        $this->assertSame(DependencyUpdateResult::Failed, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testFailsWhenOutdatedCheckFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager->method('getOutdatedPackages')->willReturn(null);
        $this->nodePackageManager->expects($this->never())->method('updatePackages');
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Could not check for outdated packages'));

        $this->assertSame(DependencyUpdateResult::Failed, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testWarnsWhenPostUpdateCheckFails(): void
    {
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/theme/web/tailwind/package.json', true],
            ['/theme/package.json', false],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->method('getOutdatedPackages')
            ->willReturnOnConsecutiveCalls([$this->outdatedPackage()], null);
        $this->nodePackageManager->method('updatePackages')->willReturn(true);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Could not verify the update result'));

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    // -------------------------------------------------------------------------
    // Magento root fallback for standard themes (Magento/luma, Magento/blank)
    // -------------------------------------------------------------------------

    public function testUpdatesMagentoRootForStandardThemes(): void
    {
        $this->actAsMagentoStandardTheme();
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/luma/web/tailwind/package.json', false],
            ['/luma/package.json', false],
            ['/magento-root/package.json', true],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('getOutdatedPackages')
            ->with('/magento-root')
            ->willReturn([]);

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Magento/luma',
            '/luma',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testSkipsStandardThemeWhenMagentoRootHasNoPackageJson(): void
    {
        $this->actAsMagentoStandardTheme();
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->nodePackageManager->expects($this->never())->method('getOutdatedPackages');
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('package.json.sample'));

        $this->assertSame(DependencyUpdateResult::Skipped, $this->updater->updateThemeDependencies(
            'Magento/luma',
            '/luma',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testUpdatesMagentoRootOnlyOncePerRun(): void
    {
        $this->actAsMagentoStandardTheme();
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/luma/web/tailwind/package.json', false],
            ['/luma/package.json', false],
            ['/blank/web/tailwind/package.json', false],
            ['/blank/package.json', false],
            ['/magento-root/package.json', true],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('getOutdatedPackages')
            ->with('/magento-root')
            ->willReturn([]);

        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Magento/luma',
            '/luma',
            $this->io,
            false,
            false,
            false,
        ));
        $this->assertSame(DependencyUpdateResult::Updated, $this->updater->updateThemeDependencies(
            'Magento/blank',
            '/blank',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testReplaysMagentoRootFailureForSubsequentStandardThemes(): void
    {
        $this->actAsMagentoStandardTheme();
        $this->fileDriver->method('isExists')->willReturnMap([
            ['/luma/web/tailwind/package.json', false],
            ['/luma/package.json', false],
            ['/blank/web/tailwind/package.json', false],
            ['/blank/package.json', false],
            ['/magento-root/package.json', true],
        ]);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->nodePackageManager
            ->expects($this->once())
            ->method('getOutdatedPackages')
            ->with('/magento-root')
            ->willReturn(null);

        $this->assertSame(DependencyUpdateResult::Failed, $this->updater->updateThemeDependencies(
            'Magento/luma',
            '/luma',
            $this->io,
            false,
            false,
            false,
        ));
        $this->assertSame(DependencyUpdateResult::Failed, $this->updater->updateThemeDependencies(
            'Magento/blank',
            '/blank',
            $this->io,
            false,
            false,
            false,
        ));
    }
}
