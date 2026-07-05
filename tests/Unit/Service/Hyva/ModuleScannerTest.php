<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\IncompatibilityDetector;
use OpenForgeProject\MageForge\Service\Hyva\ModuleScanner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ModuleScannerTest extends TestCase
{
    private File&MockObject $fileDriver;
    private IncompatibilityDetector&MockObject $detector;
    private ModuleScanner $scanner;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->detector = $this->createMock(IncompatibilityDetector::class);
        $this->scanner = new ModuleScanner($this->fileDriver, $this->detector);
    }

    // -------------------------------------------------------------------------
    // scanModule
    // -------------------------------------------------------------------------

    public function testReturnsEmptyResultForMissingModuleDirectory(): void
    {
        $this->fileDriver->method('isDirectory')->with('/module')->willReturn(false);

        $this->assertSame(
            ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0],
            $this->scanner->scanModule('/module'),
        );
    }

    public function testCollectsIssuesAndCountsCriticalOnes(): void
    {
        $this->givenDirectories(['/module']);
        $this->fileDriver->method('readDirectory')->with('/module')->willReturn([
            '/module/view.js',
            '/module/layout.xml',
        ]);
        $this->detector->method('getExtensionFromPath')->willReturnMap([
            ['/module/view.js', 'js'],
            ['/module/layout.xml', 'xml'],
        ]);
        $this->detector->method('detectInFile')->willReturnMap([
            ['/module/view.js', [
                ['description' => 'RequireJS define() usage', 'severity' => 'critical', 'line' => 1],
                ['description' => 'jQuery AJAX direct usage', 'severity' => 'warning', 'line' => 5],
            ]],
            ['/module/layout.xml', []],
        ]);

        $result = $this->scanner->scanModule('/module');

        $this->assertSame(2, $result['totalIssues']);
        $this->assertSame(1, $result['criticalIssues']);
        $this->assertArrayHasKey('view.js', $result['files']);
        $this->assertCount(1, $result['files']);
    }

    public function testSkipsExcludedDirectoriesAndIrrelevantExtensions(): void
    {
        $this->givenDirectories(['/module', '/module/Test', '/module/node_modules', '/module/src']);
        $this->fileDriver->method('readDirectory')->willReturnMap([
            ['/module', ['/module/Test', '/module/node_modules', '/module/src', '/module/readme.md']],
            ['/module/src', ['/module/src/widget.js']],
        ]);
        $this->detector->method('getExtensionFromPath')->willReturnMap([
            ['/module/readme.md', 'md'],
            ['/module/src/widget.js', 'js'],
        ]);

        $scannedFiles = [];
        $this->detector
            ->method('detectInFile')
            ->willReturnCallback(function (string $file) use (&$scannedFiles): array {
                $scannedFiles[] = $file;
                return [];
            });

        $this->scanner->scanModule('/module');

        $this->assertSame(['/module/src/widget.js'], $scannedFiles);
    }

    public function testSkipsUnreadableDirectories(): void
    {
        $this->givenDirectories(['/module']);
        $this->fileDriver->method('readDirectory')->willThrowException(new \RuntimeException('permission denied'));

        $this->assertSame(
            ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0],
            $this->scanner->scanModule('/module'),
        );
    }

    public function testHandlesDirectoryEntryWithoutPathSeparator(): void
    {
        $this->givenDirectories(['/module']);
        $this->fileDriver->method('readDirectory')->willReturnMap([
            ['/module', ['widget.js']],
        ]);
        $this->detector->method('getExtensionFromPath')->with('widget.js')->willReturn('js');
        $this->detector->method('detectInFile')->willReturn([]);

        $result = $this->scanner->scanModule('/module');

        $this->assertSame(['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0], $result);
    }

    // -------------------------------------------------------------------------
    // getModuleInfo
    // -------------------------------------------------------------------------

    public function testModuleInfoIsUnknownWithoutComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $this->assertSame(
            ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false],
            $this->scanner->getModuleInfo('/module'),
        );
    }

    public function testModuleInfoIsUnknownForInvalidComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('not json');

        $this->assertSame(
            ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false],
            $this->scanner->getModuleInfo('/module'),
        );
    }

    public function testModuleInfoIsUnknownWhenComposerJsonCannotBeRead(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willThrowException(new \RuntimeException('read error'));

        $this->assertSame(
            ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false],
            $this->scanner->getModuleInfo('/module'),
        );
    }

    public function testModuleInfoReadsNameAndVersion(): void
    {
        $this->givenComposerJson(['name' => 'vendor/module', 'version' => '1.2.3']);

        $this->assertSame(
            ['name' => 'vendor/module', 'version' => '1.2.3', 'isHyvaAware' => false],
            $this->scanner->getModuleInfo('/module'),
        );
    }

    public function testHyvaCompatPackageIsHyvaAware(): void
    {
        $this->givenComposerJson(['name' => 'hyva-themes/magento2-vendor-module-compat']);

        $this->assertTrue($this->scanner->getModuleInfo('/module')['isHyvaAware']);
    }

    public function testModuleRequiringHyvaPackageIsHyvaAware(): void
    {
        $this->givenComposerJson([
            'name' => 'vendor/module',
            'require' => ['php' => '^8.3', 'hyva-themes/magento2-default-theme' => '^1.3'],
        ]);

        $this->assertTrue($this->scanner->getModuleInfo('/module')['isHyvaAware']);
    }

    public function testPlainHyvaThemesPackageWithoutCompatSuffixIsNotHyvaAware(): void
    {
        $this->givenComposerJson(['name' => 'hyva-themes/magento2-theme-module']);

        $this->assertFalse($this->scanner->getModuleInfo('/module')['isHyvaAware']);
    }

    public function testNonArrayRequireIsNotHyvaAware(): void
    {
        $this->givenComposerJson(['name' => 'vendor/module', 'require' => 'not-an-array']);

        $this->assertFalse($this->scanner->getModuleInfo('/module')['isHyvaAware']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<int, string> $directories
     */
    private function givenDirectories(array $directories): void
    {
        $this->fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => in_array($path, $directories, true));
    }

    /**
     * @param array<string, mixed> $composerData
     */
    private function givenComposerJson(array $composerData): void
    {
        $this->fileDriver->method('isExists')->with('/module/composer.json')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn(json_encode($composerData));
    }
}
