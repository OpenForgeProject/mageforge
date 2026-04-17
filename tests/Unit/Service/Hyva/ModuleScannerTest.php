<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\IncompatibilityDetector;
use OpenForgeProject\MageForge\Service\Hyva\ModuleScanner;
use PHPUnit\Framework\TestCase;

class ModuleScannerTest extends TestCase
{
    private File $fileDriver;
    private IncompatibilityDetector $incompatibilityDetector;
    private ModuleScanner $scanner;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->incompatibilityDetector = $this->createMock(IncompatibilityDetector::class);
        $this->scanner = new ModuleScanner($this->fileDriver, $this->incompatibilityDetector);
    }

    // ---- scanModule tests ----

    public function testScanModuleReturnsEmptyResultForNonExistentDirectory(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(false);

        $result = $this->scanner->scanModule('/nonexistent/module');

        $this->assertSame([], $result['files']);
        $this->assertSame(0, $result['totalIssues']);
        $this->assertSame(0, $result['criticalIssues']);
    }

    public function testScanModuleReturnsZeroIssuesWhenNoFilesFound(): void
    {
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('readDirectory')->willReturn([]);

        $result = $this->scanner->scanModule('/path/to/module');

        $this->assertSame(0, $result['totalIssues']);
        $this->assertSame(0, $result['criticalIssues']);
        $this->assertSame([], $result['files']);
    }

    public function testScanModuleCountsIssuesFromMultipleFiles(): void
    {
        $modulePath = '/path/to/module';
        $jsFile = $modulePath . '/view/frontend/web/js/widget.js';
        $xmlFile = $modulePath . '/view/frontend/layout/default.xml';

        $this->fileDriver->method('isDirectory')
            ->willReturnCallback(fn($path) => $path === $modulePath);

        $this->fileDriver->method('readDirectory')
            ->with($modulePath)
            ->willReturn([$jsFile, $xmlFile]);

        $this->fileDriver->method('isDirectory')
            ->willReturnCallback(fn($path) => $path === $modulePath);

        $this->incompatibilityDetector->method('detectInFile')
            ->willReturnCallback(function (string $path) use ($jsFile, $xmlFile): array {
                if ($path === $jsFile) {
                    return [
                        ['description' => 'RequireJS define() usage', 'severity' => 'critical', 'line' => 1, 'pattern' => '/define/'],
                        ['description' => 'Knockout.js usage', 'severity' => 'critical', 'line' => 2, 'pattern' => '/ko\./'],
                    ];
                }
                if ($path === $xmlFile) {
                    return [
                        ['description' => 'UI Component usage', 'severity' => 'critical', 'line' => 5, 'pattern' => '/<uiComponent/'],
                    ];
                }
                return [];
            });

        $result = $this->scanner->scanModule($modulePath);

        $this->assertSame(3, $result['totalIssues']);
        $this->assertSame(3, $result['criticalIssues']);
        $this->assertCount(2, $result['files']);
    }

    public function testScanModuleCountsOnlyCriticalIssuesSeparately(): void
    {
        $modulePath = '/path/to/module';
        $jsFile = $modulePath . '/script.js';

        $this->fileDriver->method('isDirectory')
            ->willReturnCallback(fn($path) => $path === $modulePath);

        $this->fileDriver->method('readDirectory')
            ->with($modulePath)
            ->willReturn([$jsFile]);

        $this->incompatibilityDetector->method('detectInFile')
            ->willReturn([
                ['description' => 'RequireJS define() usage', 'severity' => 'critical', 'line' => 1, 'pattern' => '/define/'],
                ['description' => 'jQuery AJAX direct usage', 'severity' => 'warning', 'line' => 2, 'pattern' => '/\$.ajax/'],
            ]);

        $result = $this->scanner->scanModule($modulePath);

        $this->assertSame(2, $result['totalIssues']);
        $this->assertSame(1, $result['criticalIssues']);
    }

    public function testScanModuleUsesRelativePathsInResultKeys(): void
    {
        $modulePath = '/app/code/Vendor/Module';
        $jsFile = $modulePath . '/view/frontend/web/js/widget.js';

        $this->fileDriver->method('isDirectory')
            ->willReturnCallback(fn($path) => $path === $modulePath);

        $this->fileDriver->method('readDirectory')
            ->with($modulePath)
            ->willReturn([$jsFile]);

        $this->incompatibilityDetector->method('detectInFile')
            ->willReturn([
                ['description' => 'RequireJS', 'severity' => 'critical', 'line' => 1, 'pattern' => '/.*/'],
            ]);

        $result = $this->scanner->scanModule($modulePath);

        $this->assertArrayHasKey('view/frontend/web/js/widget.js', $result['files']);
    }

    public function testScanModuleExcludesTestDirectories(): void
    {
        $modulePath = '/path/to/module';
        $testDir = $modulePath . '/Test';
        $jsFile = $testDir . '/Unit/SomeTest.js';

        $this->fileDriver->method('isDirectory')
            ->willReturnCallback(fn($path) => in_array($path, [$modulePath, $testDir]));

        $this->fileDriver->method('readDirectory')
            ->willReturnCallback(function (string $path) use ($modulePath, $testDir, $jsFile): array {
                if ($path === $modulePath) {
                    return [$testDir];
                }
                return [];
            });

        $this->incompatibilityDetector->expects($this->never())->method('detectInFile');

        $result = $this->scanner->scanModule($modulePath);

        $this->assertSame(0, $result['totalIssues']);
    }

    // ---- getModuleInfo tests ----

    public function testGetModuleInfoReturnsUnknownForMissingComposerJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->scanner->getModuleInfo('/some/module/path');

        $this->assertSame('Unknown', $result['name']);
        $this->assertSame('Unknown', $result['version']);
        $this->assertFalse($result['isHyvaAware']);
    }

    public function testGetModuleInfoParsesComposerJsonCorrectly(): void
    {
        $composerData = [
            'name' => 'vendor/my-module',
            'version' => '1.2.3',
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        $result = $this->scanner->getModuleInfo('/some/module/path');

        $this->assertSame('vendor/my-module', $result['name']);
        $this->assertSame('1.2.3', $result['version']);
        $this->assertFalse($result['isHyvaAware']);
    }

    public function testGetModuleInfoReturnsUnknownForInvalidJson(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('not valid json {{{');

        $result = $this->scanner->getModuleInfo('/some/module/path');

        $this->assertSame('Unknown', $result['name']);
        $this->assertSame('Unknown', $result['version']);
        $this->assertFalse($result['isHyvaAware']);
    }

    public function testGetModuleInfoDetectsHyvaAwareModuleByPackageName(): void
    {
        $composerData = [
            'name' => 'hyva-themes/magento2-default-compat',
            'version' => '1.0.0',
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        $result = $this->scanner->getModuleInfo('/some/module/path');

        $this->assertTrue($result['isHyvaAware']);
    }

    public function testGetModuleInfoDetectsHyvaAwareModuleByDependency(): void
    {
        $composerData = [
            'name' => 'vendor/my-module',
            'version' => '2.0.0',
            'require' => [
                'hyva-themes/magento2-theme' => '^1.0',
            ],
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        $result = $this->scanner->getModuleInfo('/some/module/path');

        $this->assertTrue($result['isHyvaAware']);
    }

    // ---- hasHyvaCompatibilityPackage tests ----

    public function testHasHyvaCompatibilityPackageReturnsFalseWhenComposerJsonMissing(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->scanner->hasHyvaCompatibilityPackage('/some/module');

        $this->assertFalse($result);
    }

    public function testHasHyvaCompatibilityPackageReturnsTrueForHyvaCompatPackage(): void
    {
        $composerData = [
            'name' => 'hyva-themes/magento2-cms-compat',
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        $result = $this->scanner->hasHyvaCompatibilityPackage('/some/module');

        $this->assertTrue($result);
    }

    public function testHasHyvaCompatibilityPackageReturnsFalseForNonHyvaPackage(): void
    {
        $composerData = [
            'name' => 'vendor/regular-module',
            'version' => '1.0.0',
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        $result = $this->scanner->hasHyvaCompatibilityPackage('/some/module');

        $this->assertFalse($result);
    }

    public function testHasHyvaCompatibilityPackageReturnsTrueWhenDependencyIsHyva(): void
    {
        $composerData = [
            'name' => 'vendor/some-module',
            'require' => [
                'hyva-themes/magento2-reset' => '^1.0',
            ],
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        $result = $this->scanner->hasHyvaCompatibilityPackage('/some/module');

        $this->assertTrue($result);
    }

    public function testHasHyvaCompatibilityPackageReturnsFalseForHyvaPackageWithoutCompatSuffix(): void
    {
        $composerData = [
            'name' => 'hyva-themes/magento2-theme',
        ];

        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')
            ->willReturn((string) json_encode($composerData));

        // "hyva-themes/magento2-theme" does not end in "-compat", so only the require check applies
        // Since there's no require array, it should return false
        $result = $this->scanner->hasHyvaCompatibilityPackage('/some/module');

        $this->assertFalse($result);
    }
}
