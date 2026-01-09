<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompatibilityChecker
{
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly ModuleScanner $moduleScanner,
        private readonly IncompatibilityDetector $incompatibilityDetector
    ) {
    }

    /**
     * Check all modules for Hyvä compatibility
     *
     * @return array Results with structure: ['modules' => [], 'summary' => [], 'hasIncompatibilities' => bool]
     */
    public function check(
        SymfonyStyle $io,
        OutputInterface $output,
        bool $showAll = false,
        bool $thirdPartyOnly = false,
        bool $excludeVendor = true
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
                'path' => $modulePath,
                'compatible' => $isCompatible,
                'hasWarnings' => $hasWarnings,
                'scanResult' => $scanResult,
                'moduleInfo' => $moduleInfo,
            ];

            // Update summary
            if ($isCompatible && !$hasWarnings) {
                $results['summary']['compatible']++;
            } else {
                $results['summary']['incompatible']++;
                $results['hasIncompatibilities'] = true;
            }

            if ($moduleInfo['isHyvaAware']) {
                $results['summary']['hyvaAware']++;
            }

            $results['summary']['criticalIssues'] += $scanResult['criticalIssues'];
            $results['summary']['warningIssues'] += ($scanResult['totalIssues'] - $scanResult['criticalIssues']);
        }

        return $results;
    }

    /**
     * Check if module is a vendor module
     */
    private function isVendorModule(string $modulePath): bool
    {
        return str_contains($modulePath, '/vendor/');
    }

    /**
     * Check if module is a core Magento module
     */
    private function isMagentoModule(string $moduleName): bool
    {
        return str_starts_with($moduleName, 'Magento_');
    }

    /**
     * Format results for display
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
     */
    private function getStatusDisplay(array $moduleData): string
    {
        if ($moduleData['moduleInfo']['isHyvaAware']) {
            return '<fg=green>✓ Hyvä-Aware</>';
        }

        if ($moduleData['compatible'] && !$moduleData['hasWarnings']) {
            return '<fg=green>✓ Compatible</>';
        }

        if ($moduleData['compatible'] && $moduleData['hasWarnings']) {
            return '<fg=yellow>⚠ Warnings</>';
        }

        return '<fg=red>✗ Incompatible</>';
    }

    /**
     * Get issues display string
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

        $warnings = $scanResult['totalIssues'] - $scanResult['criticalIssues'];
        if ($warnings > 0) {
            $parts[] = sprintf('<fg=yellow>%d warning(s)</>', $warnings);
        }

        return implode(', ', $parts);
    }

    /**
     * Get detailed file issues for a module
     */
    public function getDetailedIssues(string $moduleName, array $moduleData): array
    {
        $files = $moduleData['scanResult']['files'] ?? [];
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
