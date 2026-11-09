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
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/a.phtml', "<p>same</p>\n");
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/b.phtml', '<p>custom</p>');
        $this->write('original/b.phtml', '<p>original</p>');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::IDENTICAL, $findings[0]->getReason());
        $this->assertSame('Magento_Catalog::a.phtml', $findings[0]->getTemplateId());
        $this->assertTrue($findings[0]->isSafeToDelete());
    }

    public function testReportsOverrideOfMissingModuleAsOrphaned(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Gone_Module/templates/x.phtml', 'x');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::ORPHANED, $findings[0]->getReason());
        $this->assertTrue($findings[0]->isSafeToDelete());
    }

    public function testReportsMissingRecordedSourceAsOrphanedButNotSafe(): void
    {
        $header = "<?php\n/**\n * @mageforge-template-override\n * @source vendor/gone/a.phtml\n */\n?>\n\n";
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/c.phtml', $header . 'c');

        $findings = $this->scanner->scan($this->createTheme());

        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::ORPHANED, $findings[0]->getReason());
        $this->assertFalse($findings[0]->isSafeToDelete());
    }

    public function testReportsUnreferencedTemplateOnlyInDeepMode(): void
    {
        $this->write('app/design/frontend/Vendor/theme/Magento_Catalog/templates/d.phtml', 'd');
        $this->usageIndex->method('collect')->willReturn(['Magento_Catalog::other.phtml' => true]);

        $this->assertSame([], $this->scanner->scan($this->createTheme()));

        $findings = $this->scanner->scan($this->createTheme(), true);
        $this->assertCount(1, $findings);
        $this->assertSame(ObsoleteReason::UNUSED, $findings[0]->getReason());
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
        $this->assertFalse($findings[0]->isSafeToDelete());
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
