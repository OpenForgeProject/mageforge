<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\DependencyUpdater;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class DependencyUpdaterTest extends TestCase
{
    private File&MockObject $fileDriver;
    private NodePackageManager&MockObject $nodePackageManager;
    private SymfonyStyle&MockObject $io;
    private DependencyUpdater $updater;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->nodePackageManager = $this->createMock(NodePackageManager::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->updater = new DependencyUpdater($this->fileDriver, $this->nodePackageManager);
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
        $this->assertTrue($this->updater->isVendorTheme('/var/www/vendor/hyva-themes/magento2-default-theme'));
        $this->assertFalse($this->updater->isVendorTheme('/var/www/app/design/frontend/Vendor/theme'));
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

        $this->assertFalse($this->updater->updateThemeDependencies(
            'Hyva/default',
            '/var/www/vendor/hyva-themes/magento2-default-theme',
            $this->io,
            false,
            false,
            false,
        ));
    }

    public function testWarnsWhenThemeHasNoOwnPackageJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);
        $this->io
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('has no own package.json'));

        $this->assertFalse($this->updater->updateThemeDependencies(
            'Magento/luma',
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

        $this->assertTrue($this->updater->updateThemeDependencies(
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
            ->with('/theme/web/tailwind')
            ->willReturn(true);
        $this->nodePackageManager->method('getOutdatedPackages')->willReturn([]);

        $this->assertTrue($this->updater->updateThemeDependencies(
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

        $this->assertFalse($this->updater->updateThemeDependencies(
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

        $this->assertTrue($this->updater->updateThemeDependencies(
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

        $this->assertTrue($this->updater->updateThemeDependencies(
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

        $this->assertTrue($this->updater->updateThemeDependencies(
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

        $this->assertTrue($this->updater->updateThemeDependencies(
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

        $this->assertTrue($this->updater->updateThemeDependencies(
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

        $this->assertFalse($this->updater->updateThemeDependencies(
            'Vendor/theme',
            '/theme',
            $this->io,
            false,
            false,
            false,
        ));
    }
}
