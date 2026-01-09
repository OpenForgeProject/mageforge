<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;

class ModuleScanner
{
    private const SCAN_EXTENSIONS = ['js', 'xml', 'phtml'];
    private const EXCLUDE_DIRECTORIES = ['Test', 'tests', 'node_modules', 'vendor'];

    public function __construct(
        private readonly File $fileDriver,
        private readonly IncompatibilityDetector $incompatibilityDetector
    ) {
    }

    /**
     * Scan a module directory for compatibility issues
     *
     * @return array Array with structure: ['files' => [], 'totalIssues' => int, 'criticalIssues' => int]
     */
    public function scanModule(string $modulePath): array
    {
        if (!$this->fileDriver->isDirectory($modulePath)) {
            return ['files' => [], 'totalIssues' => 0, 'criticalIssues' => 0];
        }

        $filesWithIssues = [];
        $totalIssues = 0;
        $criticalIssues = 0;

        $files = $this->findRelevantFiles($modulePath);

        foreach ($files as $file) {
            $issues = $this->incompatibilityDetector->detectInFile($file);

            if (!empty($issues)) {
                $relativePath = str_replace($modulePath . '/', '', $file);
                $filesWithIssues[$relativePath] = $issues;
                $totalIssues += count($issues);

                foreach ($issues as $issue) {
                    if ($issue['severity'] === 'critical') {
                        $criticalIssues++;
                    }
                }
            }
        }

        return [
            'files' => $filesWithIssues,
            'totalIssues' => $totalIssues,
            'criticalIssues' => $criticalIssues,
        ];
    }

    /**
     * Recursively find all relevant files in a directory
     */
    private function findRelevantFiles(string $directory): array
    {
        $relevantFiles = [];

        try {
            $items = $this->fileDriver->readDirectory($directory);

            foreach ($items as $item) {
                $basename = basename($item);

                // Skip excluded directories
                if ($this->fileDriver->isDirectory($item)) {
                    if (in_array($basename, self::EXCLUDE_DIRECTORIES, true)) {
                        continue;
                    }
                    // Recursively scan subdirectories
                    $relevantFiles = array_merge($relevantFiles, $this->findRelevantFiles($item));
                    continue;
                }

                // Check if file has relevant extension
                $extension = pathinfo($item, PATHINFO_EXTENSION);
                if (in_array($extension, self::SCAN_EXTENSIONS, true)) {
                    $relevantFiles[] = $item;
                }
            }
        } catch (\Exception $e) {
            // Silently skip directories that can't be read
        }

        return $relevantFiles;
    }

    /**
     * Check if module has Hyvä compatibility package
     */
    public function hasHyvaCompatibilityPackage(string $modulePath): bool
    {
        $composerPath = $modulePath . '/composer.json';

        if (!$this->fileDriver->isExists($composerPath)) {
            return false;
        }

        try {
            $content = $this->fileDriver->fileGetContents($composerPath);
            $composerData = json_decode($content, true);

            if (!is_array($composerData)) {
                return false;
            }

            // Check if this IS a Hyvä compatibility package
            $packageName = $composerData['name'] ?? '';
            if (str_contains($packageName, 'hyva-themes/') && str_contains($packageName, '-compat')) {
                return true;
            }

            // Check dependencies for Hyvä packages
            $requires = $composerData['require'] ?? [];
            foreach ($requires as $package => $version) {
                if (str_contains($package, 'hyva-themes/')) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Get module info from composer.json
     */
    public function getModuleInfo(string $modulePath): array
    {
        $composerPath = $modulePath . '/composer.json';

        if (!$this->fileDriver->isExists($composerPath)) {
            return ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false];
        }

        try {
            $content = $this->fileDriver->fileGetContents($composerPath);
            $composerData = json_decode($content, true);

            return [
                'name' => $composerData['name'] ?? 'Unknown',
                'version' => $composerData['version'] ?? 'Unknown',
                'isHyvaAware' => $this->hasHyvaCompatibilityPackage($modulePath),
            ];
        } catch (\Exception $e) {
            return ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false];
        }
    }
}
