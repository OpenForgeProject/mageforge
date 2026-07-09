<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use OpenForgeProject\MageForge\Service\Hyva\CompatibilityChecker;
use OpenForgeProject\MageForge\Service\Hyva\ModuleScanner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-import-type ModuleEntry from CompatibilityChecker
 * @phpstan-import-type CheckResults from CompatibilityChecker
 * @phpstan-import-type ScanResult from ModuleScanner
 */
class CompatibilityCheckerTest extends TestCase
{
    private ComponentRegistrarInterface&MockObject $componentRegistrar;
    private ModuleScanner&MockObject $moduleScanner;
    private SymfonyStyle&MockObject $io;
    private CompatibilityChecker $checker;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->moduleScanner = $this->createMock(ModuleScanner::class);
        $this->io = $this->createMock(SymfonyStyle::class);
        $this->checker = new CompatibilityChecker($this->componentRegistrar, $this->moduleScanner);
    }

    // -------------------------------------------------------------------------
    // check
    // -------------------------------------------------------------------------

    public function testAggregatesSummaryAcrossModules(): void
    {
        $this->givenModules([
            'Vendor_Clean' => '/app/code/Vendor/Clean',
            'Vendor_Broken' => '/app/code/Vendor/Broken',
            'Magento_Catalog' => '/app/code/Magento/Catalog',
        ]);
        $this->givenScanResults([
            '/app/code/Vendor/Clean' => $this->scanResult(critical: 0, total: 1),
            '/app/code/Vendor/Broken' => $this->scanResult(critical: 2, total: 3),
            '/app/code/Magento/Catalog' => $this->scanResult(critical: 1, total: 1),
        ]);
        $this->givenModuleInfo(hyvaAware: false);
        $this->io
            ->expects($this->once())
            ->method('text')
            ->with('Scanning 3 modules for Hyvä compatibility...');
        $this->io->expects($this->once())->method('newLine');

        $results = $this->checker->check($this->io);

        $this->assertSame(
            [
                'total' => 3,
                'compatible' => 1,
                'incompatible' => 2,
                'hyvaAware' => 0,
                'criticalIssues' => 3,
                'warningIssues' => 2,
            ],
            $results['summary'],
        );
        $this->assertTrue($results['hasIncompatibilities']);
        $this->assertTrue($results['modules']['Vendor_Clean']['compatible']);
        $this->assertFalse($results['modules']['Vendor_Broken']['compatible']);
    }

    public function testCriticalIssuesAloneMarkResultsAsIncompatible(): void
    {
        $this->givenModules(['Vendor_Broken' => '/app/code/Vendor/Broken']);
        $this->givenScanResults(['/app/code/Vendor/Broken' => $this->scanResult(critical: 1, total: 1)]);
        $this->givenModuleInfo(hyvaAware: false);

        $results = $this->checker->check($this->io);

        $this->assertTrue($results['hasIncompatibilities']);
        $this->assertFalse($results['modules']['Vendor_Broken']['hasWarnings']);
    }

    public function testFullyCompatibleModulesProduceNoIncompatibilities(): void
    {
        $this->givenModules(['Vendor_Clean' => '/app/code/Vendor/Clean']);
        $this->givenScanResults(['/app/code/Vendor/Clean' => $this->scanResult(critical: 0, total: 0)]);
        $this->givenModuleInfo(hyvaAware: false);

        $results = $this->checker->check($this->io);

        $this->assertFalse($results['hasIncompatibilities']);
        $this->assertSame(0, $results['summary']['incompatible']);
    }

    public function testWarningsAloneMarkResultsAsIncompatible(): void
    {
        $this->givenModules(['Vendor_Warned' => '/app/code/Vendor/Warned']);
        $this->givenScanResults(['/app/code/Vendor/Warned' => $this->scanResult(critical: 0, total: 2)]);
        $this->givenModuleInfo(hyvaAware: false);

        $results = $this->checker->check($this->io);

        $this->assertTrue($results['hasIncompatibilities']);
        $this->assertTrue($results['modules']['Vendor_Warned']['compatible']);
        $this->assertTrue($results['modules']['Vendor_Warned']['hasWarnings']);
        $this->assertSame(2, $results['summary']['warningIssues']);
    }

    public function testExcludesVendorModulesByDefault(): void
    {
        $this->givenModules([
            'Vendor_Local' => '/app/code/Vendor/Local',
            'Thirdparty_Module' => '/app/vendor/thirdparty/module',
        ]);
        $this->givenScanResults(['/app/code/Vendor/Local' => $this->scanResult(critical: 0, total: 0)]);
        $this->givenModuleInfo(hyvaAware: false);

        $results = $this->checker->check($this->io);

        $this->assertSame(['Vendor_Local'], array_keys($results['modules']));
    }

    public function testIncludesVendorModulesOnRequest(): void
    {
        $this->givenModules(['Thirdparty_Module' => '/app/vendor/thirdparty/module']);
        $this->givenScanResults(['/app/vendor/thirdparty/module' => $this->scanResult(critical: 0, total: 0)]);
        $this->givenModuleInfo(hyvaAware: false);

        $results = $this->checker->check($this->io, excludeVendor: false);

        $this->assertSame(['Thirdparty_Module'], array_keys($results['modules']));
    }

    public function testSkipsMagentoModulesInThirdPartyOnlyMode(): void
    {
        $this->givenModules([
            'Magento_Catalog' => '/app/code/Magento/Catalog',
            'Vendor_Module' => '/app/code/Vendor/Module',
        ]);
        $this->givenScanResults(['/app/code/Vendor/Module' => $this->scanResult(critical: 0, total: 0)]);
        $this->givenModuleInfo(hyvaAware: false);

        $results = $this->checker->check($this->io, thirdPartyOnly: true);

        $this->assertSame(['Vendor_Module'], array_keys($results['modules']));
    }

    public function testCountsHyvaAwareModules(): void
    {
        $this->givenModules(['Vendor_Module' => '/app/code/Vendor/Module']);
        $this->givenScanResults(['/app/code/Vendor/Module' => $this->scanResult(critical: 0, total: 0)]);
        $this->givenModuleInfo(hyvaAware: true);

        $results = $this->checker->check($this->io);

        $this->assertSame(1, $results['summary']['hyvaAware']);
    }

    public function testShowAllPrintsScanningLineForEachModule(): void
    {
        $this->givenModules(['Vendor_Module' => '/app/code/Vendor/Module']);
        $this->givenScanResults(['/app/code/Vendor/Module' => $this->scanResult(critical: 0, total: 0)]);
        $this->givenModuleInfo(hyvaAware: false);
        $textCalls = [];
        $this->io->method('text')->willReturnCallback(function (string $message) use (&$textCalls): void {
            $textCalls[] = $message;
        });

        $this->checker->check($this->io, showAll: true);

        $this->assertSame(
            ['Scanning 1 modules for Hyvä compatibility...', '  Scanning: <fg=cyan>Vendor_Module</>'],
            $textCalls,
        );
    }

    // -------------------------------------------------------------------------
    // formatResultsForDisplay
    // -------------------------------------------------------------------------

    public function testDisplaysOnlyProblematicModulesByDefault(): void
    {
        $results = $this->checkResults([
            'Vendor_Clean' => $this->moduleEntry(compatible: true, hasWarnings: false, critical: 0, total: 0),
            'Vendor_Broken' => $this->moduleEntry(compatible: false, hasWarnings: false, critical: 2, total: 2),
            'Vendor_Warned' => $this->moduleEntry(compatible: true, hasWarnings: true, critical: 0, total: 1),
        ], hasIncompatibilities: true);

        $tableData = $this->checker->formatResultsForDisplay($results);

        $this->assertSame(['Vendor_Broken', 'Vendor_Warned'], array_column($tableData, 0));
        $this->assertSame('<fg=yellow>⚠ Warnings</>', $tableData[1][1]);
        $this->assertSame('<fg=yellow>1 warning(s)</>', $tableData[1][2]);
    }

    public function testDisplaysAllModulesWhenRequested(): void
    {
        $results = $this->checkResults([
            'Vendor_Clean' => $this->moduleEntry(compatible: true, hasWarnings: false, critical: 0, total: 0),
            'Vendor_Broken' => $this->moduleEntry(compatible: false, hasWarnings: false, critical: 2, total: 2),
        ], hasIncompatibilities: true);

        $tableData = $this->checker->formatResultsForDisplay($results, true);

        $this->assertSame(['Vendor_Clean', 'Vendor_Broken'], array_column($tableData, 0));
        $this->assertSame('<fg=green>✓ Compatible</>', $tableData[0][1]);
        $this->assertSame('<fg=green>None</>', $tableData[0][2]);
        $this->assertSame('<fg=red>✗ Incompatible</>', $tableData[1][1]);
        $this->assertSame('<fg=red>2 critical</>', $tableData[1][2]);
    }

    public function testFormatsMixedIssuesAndHyvaAwareStatus(): void
    {
        $results = $this->checkResults([
            'Vendor_Mixed' => $this->moduleEntry(
                compatible: false,
                hasWarnings: true,
                critical: 1,
                total: 3,
                hyvaAware: true,
            ),
        ], hasIncompatibilities: true);

        $tableData = $this->checker->formatResultsForDisplay($results);

        $this->assertSame('<fg=red>✗ Incompatible (Hyvä-Aware)</>', $tableData[0][1]);
        $this->assertSame('<fg=red>1 critical</>, <fg=yellow>2 warning(s)</>', $tableData[0][2]);
    }

    public function testHyvaAwareCompatibleModuleGetsDedicatedStatus(): void
    {
        $results = $this->checkResults([
            'Vendor_Aware' => $this->moduleEntry(
                compatible: true,
                hasWarnings: false,
                critical: 0,
                total: 0,
                hyvaAware: true,
            ),
        ], hasIncompatibilities: false);

        $tableData = $this->checker->formatResultsForDisplay($results, true);

        $this->assertSame('<fg=green>✓ Hyvä-Aware</>', $tableData[0][1]);
    }

    // -------------------------------------------------------------------------
    // getDetailedIssues
    // -------------------------------------------------------------------------

    public function testReturnsIssuesGroupedByFile(): void
    {
        $jsIssue = [
            'description' => 'RequireJS define() usage',
            'severity' => 'critical',
            'line' => 3,
            'pattern' => 'define(',
        ];
        $xmlIssue = [
            'description' => 'UI Component usage',
            'severity' => 'critical',
            'line' => 7,
            'pattern' => '<uiComponent',
        ];
        $moduleData = $this->moduleEntry(compatible: false, hasWarnings: false, critical: 2, total: 2);
        $moduleData['scanResult']['files'] = [
            'view/frontend/web/js/widget.js' => [$jsIssue],
            'view/frontend/layout/default.xml' => [$xmlIssue],
        ];

        $this->assertSame(
            [
                ['file' => 'view/frontend/web/js/widget.js', 'issues' => [$jsIssue]],
                ['file' => 'view/frontend/layout/default.xml', 'issues' => [$xmlIssue]],
            ],
            $this->checker->getDetailedIssues($moduleData),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $modules
     */
    private function givenModules(array $modules): void
    {
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn($modules);
    }

    /**
     * @param array<string, array<string, mixed>> $resultsByPath
     */
    private function givenScanResults(array $resultsByPath): void
    {
        $this->moduleScanner
            ->method('scanModule')
            ->willReturnCallback(
                static fn(string $path): array => $resultsByPath[$path]
                    ?? ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0],
            );
    }

    private function givenModuleInfo(bool $hyvaAware): void
    {
        $this->moduleScanner
            ->method('getModuleInfo')
            ->willReturn(['name' => 'vendor/module', 'version' => '1.0.0', 'isHyvaAware' => $hyvaAware]);
    }

    /**
     * @param array<string, ModuleEntry> $modules
     * @return CheckResults
     */
    private function checkResults(array $modules, bool $hasIncompatibilities): array
    {
        return [
            'modules' => $modules,
            'summary' => [
                'total' => count($modules),
                'compatible' => 0,
                'incompatible' => 0,
                'hyvaAware' => 0,
                'criticalIssues' => 0,
                'warningIssues' => 0,
            ],
            'hasIncompatibilities' => $hasIncompatibilities,
        ];
    }

    /**
     * @return ScanResult
     */
    private function scanResult(int $critical, int $total): array
    {
        return ['files' => [], 'totalIssues' => $total, 'criticalIssues' => $critical];
    }

    /**
     * @return ModuleEntry
     */
    private function moduleEntry(
        bool $compatible,
        bool $hasWarnings,
        int $critical,
        int $total,
        bool $hyvaAware = false,
    ): array {
        return [
            'compatible' => $compatible,
            'hasWarnings' => $hasWarnings,
            'scanResult' => $this->scanResult($critical, $total),
            'moduleInfo' => ['name' => 'vendor/module', 'version' => '1.0.0', 'isHyvaAware' => $hyvaAware],
        ];
    }
}
