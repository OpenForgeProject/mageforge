<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\PackageInfo;
use OpenForgeProject\MageForge\Model\ObsoleteReason;
use OpenForgeProject\MageForge\Service\TemplateOverride\ObsoleteOverrideScanner;
use OpenForgeProject\MageForge\Service\TemplateOverride\OverrideContentAnalyzer;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateFallbackResolver;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateUsageIndex;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ObsoleteOverrideScannerTest extends TestCase
{
    /**
     * @var string
     */
    private string $root;

    /**
     * @var TemplateFallbackResolver&MockObject
     */
    private MockObject $resolver;

    /**
     * @var TemplateUsageIndex&MockObject
     */
    private MockObject $usageIndex;

    /**
     * @var PackageInfo&MockObject
     */
    private MockObject $packageInfo;

    /**
     * @var ObsoleteOverrideScanner
     */
    private ObsoleteOverrideScanner $scanner;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mageforge_obsolete_' . uniqid();
        mkdir($this->root . '/app/design/frontend/Vendor/theme/Magento_Catalog/templates', 0777, true);
        mkdir($this->root . '/original', 0777, true);

        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturnCallback(fn(string $type, string $name): ?string => match (true) {
            $type === ComponentRegistrar::THEME => $this->root . '/app/design/frontend/Vendor/theme',
            $name === 'Magento_Catalog' => $this->root . '/module',
            default => null,
        });

        $this->resolver = $this->createMock(TemplateFallbackResolver::class);
        $this->resolver->method('getFallbackDirs')->willReturn([
            $this->root . '/app/design/frontend/Vendor/theme/Magento_Catalog/templates',
            $this->root . '/original',
        ]);
        $this->usageIndex = $this->createMock(TemplateUsageIndex::class);
        $this->packageInfo = $this->createMock(PackageInfo::class);
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->root);

        $this->scanner = new ObsoleteOverrideScanner(
            $registrar,
            $this->resolver,
            new OverrideContentAnalyzer(),
            $this->usageIndex,
            $this->packageInfo,
            new File(),
            $directoryList,
        );
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testReportsIdenticalOverrideAsSafeToDelete(): void
    {
        $this->write('original/a.phtml', '<p>same</p>');
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/a.phtml', '<p>same</p>');
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/b.phtml', '<p>custom</p>');
        $this->write('original/b.phtml', '<p>original</p>');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::IDENTICAL, $findings[0]->getReason());
        $this->assertSame('Magento_Catalog::a.phtml', $findings[0]->getTemplateId());
        $this->assertSame('Vendor/theme', $findings[0]->getThemeCode());
        $this->assertStringEndsWith('Magento_Catalog/templates/a.phtml', $findings[0]->getFile());
        $this->assertSame('Identical to original/a.phtml', $findings[0]->getDetails());
        $this->assertTrue($findings[0]->isSafeToDelete());
    }

    public function testReportsOverrideOfMissingModuleAsOrphaned(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Gone_Module/templates/x.phtml', 'x');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::ORPHANED, $findings[0]->getReason());
        $this->assertSame('Module Gone_Module is not installed or enabled', $findings[0]->getDetails());
        $this->assertTrue($findings[0]->isSafeToDelete());
    }

    public function testReportsMissingRecordedSourceAsOrphanedButNotSafe(): void
    {
        $header = "<?php\n/**\n * @mageforge-template-override\n * @source vendor/gone/a.phtml\n */\n?>\n\n";
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/c.phtml', $header . 'c');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::ORPHANED, $findings[0]->getReason());
        $this->assertSame('Source file no longer exists: vendor/gone/a.phtml', $findings[0]->getDetails());
        $this->assertFalse($findings[0]->isSafeToDelete());
    }

    public function testReportsUnreferencedTemplateOnlyInDeepMode(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/d.phtml', 'd');
        $this->usageIndex->method('collect')->willReturn(['Magento_Catalog::other.phtml' => true]);

        $this->assertSame([], $this->scanner->scan($this->createTheme()));

        $findings = $this->scanner->scan($this->createTheme(), true);
        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::POSSIBLY_UNUSED, $findings[0]->getReason());
        $this->assertSame(
            'No reference found. It may still be used (widget, DI, database, dynamic path) - verify before deleting',
            $findings[0]->getDetails(),
        );
        $this->assertFalse($findings[0]->isSafeToDelete());
    }

    public function testTemplateReferencedByLiteralPathIsNotUnused(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/w.phtml', 'w');
        $this->usageIndex->method('collect')->willReturn(['*::w.phtml' => true]);

        $this->assertSame([], $this->scanner->scan($this->createTheme(), true));
    }

    public function testBuildsTheReferenceIndexOnlyOnce(): void
    {
        $this->usageIndex->expects($this->once())->method('collect')->willReturn([]);

        $this->scanner->scan($this->createTheme(), true);
        $this->scanner->scan($this->createTheme(), true);
    }

    public function testReportsOverrideCopiedFromOlderModuleVersionAsOutdated(): void
    {
        $header = "<?php\n/**\n * @mageforge-template-override\n * @module Magento_Catalog\n"
            . " * @module-version 1.0.0\n */\n?>\n\n";
        $this->write('original/e.phtml', 'new');
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/e.phtml', $header . 'old');
        $this->packageInfo->method('getVersion')->willReturn('2.0.0');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::OUTDATED, $findings[0]->getReason());
        $this->assertSame('Copied from Magento_Catalog 1.0.0, installed is 2.0.0', $findings[0]->getDetails());
        $this->assertFalse($findings[0]->isSafeToDelete());
    }

    public function testIgnoresOverrideWhoseRecordedSourceStillExists(): void
    {
        $this->write('src/a.phtml', 'a');
        $header = "<?php\n/**\n * @mageforge-template-override\n * @source src/a.phtml\n */\n?>\n\n";
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/c.phtml', $header . 'c');

        $this->assertSame([], $this->scanner->scan($this->createTheme()));

        $absolute = str_replace('src/a.phtml', $this->root . '/src/a.phtml', $header);
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/c.phtml', $absolute . 'c');

        $this->assertSame([], $this->scanner->scan($this->createTheme()));
    }

    public function testSkipsLayoutFilesAndTranslationsOfExistingModules(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/layout/catalog_product_view.xml', '<x/>');
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/web/i18n/de_DE/a.js', 'a');

        $this->assertSame([], $this->scanner->scan($this->createTheme()));
    }

    public function testIgnoresFilesOutsideModuleDirectoriesAndUnknownSubdirectories(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/other/a.phtml', 'a');
        $this->write('app/design/frontend/Vendor/theme/not_a_module_dir/templates/a.phtml', 'a');
        $this->write('app/design/frontend/Vendor/theme/NoUnderscore/templates/a.phtml', 'a');

        $this->assertSame([], $this->scanner->scan($this->createTheme()));
    }

    public function testDoesNotTreatOwnFileAsOriginal(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/solo.phtml', 'solo');

        $this->assertSame([], $this->scanner->scan($this->createTheme()));
    }

    public function testMeasuresFallbackFromTheOverridesOwnDirectory(): void
    {
        $this->resolver = $this->createMock(TemplateFallbackResolver::class);
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/x.phtml', 'x');
        $own = $this->root . '/app/design/frontend/Vendor/theme/Magento_Catalog/templates';
        $this->resolver->method('getFallbackDirs')->willReturn([$own . '/', $own . '/sub']);
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/sub/x.phtml', 'x');

        $scanner = $this->buildScanner();

        $findings = $scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(
            'Identical to app/design/frontend/Vendor/theme/Magento_Catalog/templates/sub/x.phtml',
            $findings[0]->getDetails(),
        );
    }

    public function testReturnsAllFindingsOfATheme(): void
    {
        foreach (['a', 'b'] as $name) {
            $this->write('original/' . $name . '.phtml', 'same');
            $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/' . $name . '.phtml', 'same');
        }

        $this->assertCount(2, $this->scanner->scan($this->createTheme()));
    }

    public function testAnalysesStaticFilesAndTemplatesInsideI18nDirectories(): void
    {
        $this->write('original/logo.svg', '<svg/>');
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/web/logo.svg', '<svg/>');
        $this->write('original/i18n/x.phtml', 'x');
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/i18n/x.phtml', 'x');

        $this->assertCount(2, $this->scanner->scan($this->createTheme()));
    }

    public function testDoesNotTreatDirectoriesStartingWithVendorAsVendorThemes(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturn($this->root . '/vendor-custom/theme');
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->root);
        $scanner = new ObsoleteOverrideScanner(
            $registrar,
            $this->resolver,
            new OverrideContentAnalyzer(),
            $this->usageIndex,
            $this->packageInfo,
            new File(),
            $directoryList,
        );

        $this->assertFalse($scanner->isVendorTheme($this->createTheme()));
    }

    public function testDetectsVendorThemes(): void
    {
        $this->assertFalse($this->scanner->isVendorTheme($this->createTheme()));
        $this->assertFalse($this->scanner->isVendorTheme(new FakeTheme('Missing/theme')));

        $vendorRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $vendorRegistrar->method('getPath')->willReturn($this->root . '/vendor/acme/theme');
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->root);
        $scanner = new ObsoleteOverrideScanner(
            $vendorRegistrar,
            $this->resolver,
            new OverrideContentAnalyzer(),
            $this->usageIndex,
            $this->packageInfo,
            new File(),
            $directoryList,
        );

        $this->assertTrue($scanner->isVendorTheme($this->createTheme()));
    }

    private function buildScanner(): ObsoleteOverrideScanner
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturnCallback(fn(string $type, string $name): ?string => match (true) {
            $type === ComponentRegistrar::THEME => $this->root . '/app/design/frontend/Vendor/theme',
            $name === 'Magento_Catalog' => $this->root . '/module',
            default => null,
        });
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn($this->root);

        return new ObsoleteOverrideScanner(
            $registrar,
            $this->resolver,
            new OverrideContentAnalyzer(),
            $this->usageIndex,
            $this->packageInfo,
            new File(),
            $directoryList,
        );
    }

    private function write(string $relativePath, string $content): void
    {
        $path = $this->root . '/' . $relativePath;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    private function createTheme(): FakeTheme
    {
        return new FakeTheme('Vendor/theme');
    }
}
