<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrarInterface;
use OpenForgeProject\MageForge\Service\Hyva\CompatibilityChecker;
use OpenForgeProject\MageForge\Service\Hyva\ModuleScanner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompatibilityCheckerTest extends TestCase
{
    private ComponentRegistrarInterface $componentRegistrar;
    private ModuleScanner $moduleScanner;
    private CompatibilityChecker $checker;
    private SymfonyStyle $io;
    private OutputInterface $output;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->moduleScanner = $this->createMock(ModuleScanner::class);
        $this->checker = new CompatibilityChecker($this->componentRegistrar, $this->moduleScanner);

        $this->io = $this->createMock(SymfonyStyle::class);
        $this->output = $this->createMock(OutputInterface::class);
    }

    // ---- check() tests ----

    public function testCheckReturnsEmptyModulesWhenNoModulesRegistered(): void
    {
        $this->componentRegistrar->method('getPaths')->willReturn([]);

        $result = $this->checker->check($this->io, $this->output);

        $this->assertSame([], $result['modules']);
        $this->assertSame(0, $result['summary']['total']);
        $this->assertFalse($result['hasIncompatibilities']);
    }

    public function testCheckFiltersVendorModulesWhenExcludeVendorIsTrue(): void
    {
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Vendor_Module' => '/var/www/vendor/vendor/module',
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, true);

        $this->assertSame(0, $result['summary']['total']);
        $this->assertSame([], $result['modules']);
    }

    public function testCheckIncludesVendorModulesWhenExcludeVendorIsFalse(): void
    {
        $modulePath = '/var/www/vendor/vendor/module';

        $this->componentRegistrar->method('getPaths')->willReturn([
            'Vendor_Module' => $modulePath,
        ]);

        $this->moduleScanner->method('scanModule')->willReturn([
            'files' => [],
            'totalIssues' => 0,
            'criticalIssues' => 0,
        ]);
        $this->moduleScanner->method('getModuleInfo')->willReturn([
            'name' => 'vendor/module',
            'version' => '1.0.0',
            'isHyvaAware' => false,
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, false);

        $this->assertSame(1, $result['summary']['total']);
    }

    public function testCheckFiltersMagentoModulesWhenThirdPartyOnlyIsTrue(): void
    {
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Magento_Catalog' => '/app/code/Magento/Catalog',
        ]);

        $result = $this->checker->check($this->io, $this->output, false, true, false);

        $this->assertSame(0, $result['summary']['total']);
        $this->assertSame([], $result['modules']);
    }

    public function testCheckIncludesMagentoModulesWhenThirdPartyOnlyIsFalse(): void
    {
        $modulePath = '/app/code/Magento/Catalog';

        $this->componentRegistrar->method('getPaths')->willReturn([
            'Magento_Catalog' => $modulePath,
        ]);

        $this->moduleScanner->method('scanModule')->willReturn([
            'files' => [],
            'totalIssues' => 0,
            'criticalIssues' => 0,
        ]);
        $this->moduleScanner->method('getModuleInfo')->willReturn([
            'name' => 'magento/module-catalog',
            'version' => '103.0.0',
            'isHyvaAware' => false,
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, false);

        $this->assertSame(1, $result['summary']['total']);
    }

    public function testCheckCountsCompatibleModulesCorrectly(): void
    {
        $modulePath = '/app/code/Custom/Module';

        $this->componentRegistrar->method('getPaths')->willReturn([
            'Custom_Module' => $modulePath,
        ]);

        $this->moduleScanner->method('scanModule')->willReturn([
            'files' => [],
            'totalIssues' => 0,
            'criticalIssues' => 0,
        ]);
        $this->moduleScanner->method('getModuleInfo')->willReturn([
            'name' => 'custom/module',
            'version' => '1.0.0',
            'isHyvaAware' => false,
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, false);

        $this->assertSame(1, $result['summary']['compatible']);
        $this->assertSame(0, $result['summary']['incompatible']);
        $this->assertFalse($result['hasIncompatibilities']);
    }

    public function testCheckCountsIncompatibleModulesCorrectly(): void
    {
        $modulePath = '/app/code/Custom/Module';

        $this->componentRegistrar->method('getPaths')->willReturn([
            'Custom_Module' => $modulePath,
        ]);

        $this->moduleScanner->method('scanModule')->willReturn([
            'files' => ['widget.js' => [['severity' => 'critical', 'description' => 'RequireJS']]],
            'totalIssues' => 1,
            'criticalIssues' => 1,
        ]);
        $this->moduleScanner->method('getModuleInfo')->willReturn([
            'name' => 'custom/module',
            'version' => '1.0.0',
            'isHyvaAware' => false,
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, false);

        $this->assertSame(0, $result['summary']['compatible']);
        $this->assertSame(1, $result['summary']['incompatible']);
        $this->assertTrue($result['hasIncompatibilities']);
    }

    public function testCheckCountsHyvaAwareModules(): void
    {
        $modulePath = '/app/code/Hyva/Module';

        $this->componentRegistrar->method('getPaths')->willReturn([
            'Hyva_Module' => $modulePath,
        ]);

        $this->moduleScanner->method('scanModule')->willReturn([
            'files' => [],
            'totalIssues' => 0,
            'criticalIssues' => 0,
        ]);
        $this->moduleScanner->method('getModuleInfo')->willReturn([
            'name' => 'hyva-themes/magento2-compat',
            'version' => '1.0.0',
            'isHyvaAware' => true,
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, false);

        $this->assertSame(1, $result['summary']['hyvaAware']);
    }

    public function testCheckSummarizesIssueCountsCorrectly(): void
    {
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Module_A' => '/app/code/Module/A',
        ]);

        $this->moduleScanner->method('scanModule')->willReturn([
            'files' => [],
            'totalIssues' => 3,
            'criticalIssues' => 2,
        ]);
        $this->moduleScanner->method('getModuleInfo')->willReturn([
            'name' => 'module/a',
            'version' => '1.0.0',
            'isHyvaAware' => false,
        ]);

        $result = $this->checker->check($this->io, $this->output, false, false, false);

        $this->assertSame(2, $result['summary']['criticalIssues']);
        $this->assertSame(1, $result['summary']['warningIssues']);
    }

    // ---- formatResultsForDisplay tests ----

    public function testFormatResultsForDisplayReturnsIncompatibleModulesWhenShowAllFalse(): void
    {
        $results = [
            'modules' => [
                'Compatible_Module' => [
                    'compatible' => true,
                    'hasWarnings' => false,
                    'scanResult' => ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0],
                    'moduleInfo' => ['name' => 'compatible/module', 'version' => '1.0', 'isHyvaAware' => false],
                ],
                'Incompatible_Module' => [
                    'compatible' => false,
                    'hasWarnings' => false,
                    'scanResult' => ['files' => [], 'totalIssues' => 1, 'criticalIssues' => 1],
                    'moduleInfo' => ['name' => 'incompatible/module', 'version' => '1.0', 'isHyvaAware' => false],
                ],
            ],
            'summary' => [],
            'hasIncompatibilities' => true,
        ];

        $tableData = $this->checker->formatResultsForDisplay($results, false);

        $this->assertCount(1, $tableData);
        $this->assertSame('Incompatible_Module', $tableData[0][0]);
    }

    public function testFormatResultsForDisplayReturnsAllModulesWhenShowAllTrue(): void
    {
        $results = [
            'modules' => [
                'Module_A' => [
                    'compatible' => true,
                    'hasWarnings' => false,
                    'scanResult' => ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0],
                    'moduleInfo' => ['name' => 'module/a', 'version' => '1.0', 'isHyvaAware' => false],
                ],
                'Module_B' => [
                    'compatible' => false,
                    'hasWarnings' => false,
                    'scanResult' => ['files' => [], 'totalIssues' => 1, 'criticalIssues' => 1],
                    'moduleInfo' => ['name' => 'module/b', 'version' => '1.0', 'isHyvaAware' => false],
                ],
            ],
            'summary' => [],
            'hasIncompatibilities' => true,
        ];

        $tableData = $this->checker->formatResultsForDisplay($results, true);

        $this->assertCount(2, $tableData);
    }

    public function testFormatResultsForDisplayIncludesModulesWithWarnings(): void
    {
        $results = [
            'modules' => [
                'Warning_Module' => [
                    'compatible' => true,
                    'hasWarnings' => true,
                    'scanResult' => ['files' => [], 'totalIssues' => 1, 'criticalIssues' => 0],
                    'moduleInfo' => ['name' => 'warning/module', 'version' => '1.0', 'isHyvaAware' => false],
                ],
            ],
            'summary' => [],
            'hasIncompatibilities' => false,
        ];

        $tableData = $this->checker->formatResultsForDisplay($results, false);

        $this->assertCount(1, $tableData);
        $this->assertStringContainsString('Warnings', $tableData[0][1]);
    }

    public function testFormatResultsForDisplayShowsHyvaAwareStatus(): void
    {
        $results = [
            'modules' => [
                'Hyva_Module' => [
                    'compatible' => true,
                    'hasWarnings' => false,
                    'scanResult' => ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0],
                    'moduleInfo' => ['name' => 'hyva-themes/module', 'version' => '1.0', 'isHyvaAware' => true],
                ],
            ],
            'summary' => [],
            'hasIncompatibilities' => false,
        ];

        $tableData = $this->checker->formatResultsForDisplay($results, true);

        $this->assertCount(1, $tableData);
        $this->assertStringContainsString('Hyvä-Aware', $tableData[0][1]);
    }

    // ---- getDetailedIssues tests ----

    public function testGetDetailedIssuesReturnsFileIssuesList(): void
    {
        $issues = [
            ['description' => 'RequireJS', 'severity' => 'critical', 'line' => 1, 'pattern' => '/define/'],
        ];

        $moduleData = [
            'compatible' => false,
            'hasWarnings' => false,
            'scanResult' => [
                'files' => ['widget.js' => $issues],
                'totalIssues' => 1,
                'criticalIssues' => 1,
            ],
            'moduleInfo' => ['name' => 'module/name', 'version' => '1.0', 'isHyvaAware' => false],
        ];

        $details = $this->checker->getDetailedIssues('Test_Module', $moduleData);

        $this->assertCount(1, $details);
        $this->assertSame('widget.js', $details[0]['file']);
        $this->assertSame($issues, $details[0]['issues']);
    }

    public function testGetDetailedIssuesReturnsEmptyArrayWhenNoFiles(): void
    {
        $moduleData = [
            'compatible' => true,
            'hasWarnings' => false,
            'scanResult' => [
                'files' => [],
                'totalIssues' => 0,
                'criticalIssues' => 0,
            ],
            'moduleInfo' => ['name' => 'module/name', 'version' => '1.0', 'isHyvaAware' => false],
        ];

        $details = $this->checker->getDetailedIssues('Clean_Module', $moduleData);

        $this->assertSame([], $details);
    }
}
