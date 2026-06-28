<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service that orchestrates Hyvä compatibility checking across Magento modules
 *
 * This service scans modules, detects incompatibilities with Hyvä theme framework,
 * and provides formatted results with summary statistics.
 *
 * @phpstan-import-type ScanIssue from IncompatibilityDetector
 * @phpstan-import-type ScanResult from ModuleScanner
 * @phpstan-import-type ModuleInfo from ModuleScanner
 * @phpstan-type ModuleEntry array{
 *     compatible: bool,
 *     hasWarnings: bool,
 *     scanResult: ScanResult,
 *     moduleInfo: ModuleInfo
 * }
 * @phpstan-type CheckSummary array{
 *     total: int,
 *     compatible: int,
 *     incompatible: int,
 *     hyvaAware: int,
 *     criticalIssues: int,
 *     warningIssues: int
 * }
 * @phpstan-type CheckResults array{
 *     modules: array<string, ModuleEntry>,
 *     summary: CheckSummary,
 *     hasIncompatibilities: bool
 * }
 */
class CompatibilityChecker
{
    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ModuleScanner $moduleScanner
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly ModuleScanner $moduleScanner,
    ) {
    }

    /**
     * Check all modules for Hyvä compatibility
     *
     * @param SymfonyStyle $io Symfony Style IO for output
     * @param bool $showAll Whether to show all modules (including compatible ones)
     * @param bool $thirdPartyOnly Whether to scan only third-party modules (excludes Magento_* modules)
     * @param bool $excludeVendor Whether to exclude modules from the vendor/ directory
     * @return array<string, mixed> Results with structure: ['modules' => [], 'summary' => [],
     *     'hasIncompatibilities' => bool]
     * @phpstan-return CheckResults
     */
    public function check(
        SymfonyStyle $io,
        bool $showAll = false,
        bool $thirdPartyOnly = false,
        bool $excludeVendor = true,
    ): array {
        $modules = $this->componentRegistrar->getPaths(ComponentRegistrar::MODULE);
        $results = [
            'modules' => [],
            'summary' => [
                'total' => 0,
                'compatible' => 0,
                'incompatible' => 0,
                'hyvaAware' => 0,
                'criticalIssues' => 0,
                'warningIssues' => 0,
            ],
            'hasIncompatibilities' => false,
        ];

        $io->text(sprintf('Scanning %d modules for Hyvä compatibility...', count($modules)));
        $io->newLine();

        foreach ($modules as $moduleName => $modulePath) {
            // Filter by options
            if ($excludeVendor && $this->isVendorModule($modulePath)) {
                continue;
            }

            if ($thirdPartyOnly && $this->isMagentoModule($moduleName)) {
                continue;
            }

            $results['summary']['total']++;

            if ($showAll) {
                $io->text(sprintf('  Scanning: <fg=cyan>%s</>', $moduleName));
            }

            $scanResult = $this->moduleScanner->scanModule($modulePath);
            $moduleInfo = $this->moduleScanner->getModuleInfo($modulePath);

            $isCompatible = $scanResult['criticalIssues'] === 0;
            $hasWarnings = $scanResult['totalIssues'] > $scanResult['criticalIssues'];

            $results['modules'][$moduleName] = [
                'compatible' => $isCompatible,
                'hasWarnings' => $hasWarnings,
                'scanResult' => $scanResult,
                'moduleInfo' => $moduleInfo,
            ];

            // Update summary
            if ($isCompatible) {
                $results['summary']['compatible']++;
            } else {
                $results['summary']['incompatible']++;
                $results['hasIncompatibilities'] = true;
            }

            // Warnings alone still trigger the detail/recommendation display
            if ($hasWarnings) {
                $results['hasIncompatibilities'] = true;
            }

            if ($moduleInfo['isHyvaAware']) {
                $results['summary']['hyvaAware']++;
            }

            $results['summary']['criticalIssues'] += (int) $scanResult['criticalIssues'];
            // Calculate warnings explicitly to support future severity levels
            $warningCount = max(0, (int) $scanResult['totalIssues'] - (int) $scanResult['criticalIssues']);
            $results['summary']['warningIssues'] += (int) $warningCount;
        }

        return $results;
    }

    /**
     * Check if module is a vendor module
     *
     * @param string $modulePath
     * @return bool
     */
    private function isVendorModule(string $modulePath): bool
    {
        return str_contains($modulePath, '/vendor/');
    }

    /**
     * Check if module is a core Magento module
     *
     * @param string $moduleName
     * @return bool
     */
    private function isMagentoModule(string $moduleName): bool
    {
        return str_starts_with($moduleName, 'Magento_');
    }

    /**
     * Format results for display
     *
     * @param array $results
     * @phpstan-param CheckResults $results
     * @param bool $showAll
     * @return array<int, array<int, string>>
     */
    public function formatResultsForDisplay(array $results, bool $showAll = false): array
    {
        $tableData = [];

        foreach ($results['modules'] as $moduleName => $data) {
            $status = $this->getStatusDisplay($data);
            $issues = $this->getIssuesDisplay($data);

            if ($showAll || !$data['compatible'] || $data['hasWarnings']) {
                $tableData[] = [
                    $moduleName,
                    $status,
                    $issues,
                ];
            }
        }

        return $tableData;
    }

    /**
     * Get status display string with colors
     *
     * @param array $moduleData
     * @phpstan-param ModuleEntry $moduleData
     * @return string
     */
    private function getStatusDisplay(array $moduleData): string
    {
        $hyvaTag = $moduleData['moduleInfo']['isHyvaAware'] ? ' (Hyvä-Aware)' : '';

        if ($moduleData['compatible'] && !$moduleData['hasWarnings']) {
            return $moduleData['moduleInfo']['isHyvaAware'] ? '<fg=green>✓ Hyvä-Aware</>' : '<fg=green>✓ Compatible</>';
        }

        if ($moduleData['compatible']) {
            return sprintf('<fg=yellow>⚠ Warnings%s</>', $hyvaTag);
        }

        return sprintf('<fg=red>✗ Incompatible%s</>', $hyvaTag);
    }

    /**
     * Get issues display string
     *
     * @param array $moduleData
     * @phpstan-param ModuleEntry $moduleData
     * @return string
     */
    private function getIssuesDisplay(array $moduleData): string
    {
        $scanResult = $moduleData['scanResult'];

        if ($scanResult['totalIssues'] === 0) {
            return '<fg=green>None</>';
        }

        $parts = [];

        if ($scanResult['criticalIssues'] > 0) {
            $parts[] = sprintf('<fg=red>%d critical</>', $scanResult['criticalIssues']);
        }

        // Calculate warnings explicitly to ensure non-negative values
        $warnings = max(0, $scanResult['totalIssues'] - $scanResult['criticalIssues']);
        if ($warnings > 0) {
            $parts[] = sprintf('<fg=yellow>%d warning(s)</>', $warnings);
        }

        return implode(', ', $parts);
    }

    /**
     * Get detailed file issues for a module
     *
     * @param array $moduleData
     * @phpstan-param ModuleEntry $moduleData
     * @return array<int, array{file: string, issues: array<int, ScanIssue>}>
     */
    public function getDetailedIssues(array $moduleData): array
    {
        $scanResult = $moduleData['scanResult'];
        $files = $scanResult['files'];

        $details = [];

        foreach ($files as $filePath => $issues) {
            $details[] = [
                'file' => $filePath,
                'issues' => $issues,
            ];
        }

        return $details;
    }
}
