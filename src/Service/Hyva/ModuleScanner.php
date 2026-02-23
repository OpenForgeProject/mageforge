<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Service that scans module directories for files containing Hyvä compatibility issues
 *
 * Recursively scans JavaScript, XML, and PHTML files within module directories
 * to identify patterns that may be incompatible with Hyvä themes.
 */
class ModuleScanner
{
    private const SCAN_EXTENSIONS = ['js', 'xml', 'phtml'];
    private const EXCLUDE_DIRECTORIES = ['Test', 'tests', 'node_modules', 'vendor'];

    /**
     * @param File $fileDriver
     * @param IncompatibilityDetector $incompatibilityDetector
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly IncompatibilityDetector $incompatibilityDetector
    ) {
    }

    /**
     * Scan a module directory for compatibility issues
     *
     * @param string $modulePath
     * @return array<string, mixed> Array with structure: ['files' => [], 'totalIssues' => int, 'criticalIssues' => int]
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
     *
     * @param string $directory
     * @return array<int, string>
     */
    private function findRelevantFiles(string $directory): array
    {
        $relevantFiles = [];

        try {
            $items = $this->fileDriver->readDirectory($directory);

            foreach ($items as $item) {
                $basename = $this->getBasename($item);

                // Skip excluded directories
                if ($this->fileDriver->isDirectory($item)) {
                    if (in_array($basename, self::EXCLUDE_DIRECTORIES, true)) {
                        continue;
                    }
                    // Recursively scan subdirectories
                    foreach ($this->findRelevantFiles($item) as $childFile) {
                        $relevantFiles[] = $childFile;
                    }
                    continue;
                }

                // Check if file has relevant extension
                $extension = $this->getExtensionFromPath($item);
                if (in_array($extension, self::SCAN_EXTENSIONS, true)) {
                    $relevantFiles[] = $item;
                }
            }
        } catch (\Exception $e) {
            // Skip directories that can't be read
            return $relevantFiles;
        }

        return $relevantFiles;
    }

    /**
     * Check if module has Hyvä compatibility package based on composer data
     *
     * @param array $composerData Parsed composer.json data
     * @phpstan-param array<string, mixed> $composerData
     */
    private function isHyvaCompatibilityPackage(array $composerData): bool
    {
        // Check if this IS a Hyvä compatibility package
        $packageName = $composerData['name'] ?? '';
        if (str_starts_with($packageName, 'hyva-themes/') && str_contains($packageName, '-compat')) {
            return true;
        }

        // Check dependencies for Hyvä packages
        $requires = $composerData['require'] ?? [];
        foreach ($requires as $package => $version) {
            if (str_starts_with($package, 'hyva-themes/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if module has Hyvä compatibility package (public wrapper)
     *
     * @param string $modulePath
     * @return bool
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

            return $this->isHyvaCompatibilityPackage($composerData);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get module info from composer.json
     *
     * @param string $modulePath
     * @return array<string, mixed>
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

            if (!is_array($composerData)) {
                return ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false];
            }

            return [
                'name' => $composerData['name'] ?? 'Unknown',
                'version' => $composerData['version'] ?? 'Unknown',
                'isHyvaAware' => $this->isHyvaCompatibilityPackage($composerData),
            ];
        } catch (\Exception $e) {
            return ['name' => 'Unknown', 'version' => 'Unknown', 'isHyvaAware' => false];
        }
    }

    /**
     * Get basename without using basename().
     *
     * @param string $path
     * @return string
     */
    private function getBasename(string $path): string
    {
        $trimmed = rtrim($path, '/');
        $pos = strrpos($trimmed, '/');
        if ($pos === false) {
            return $trimmed;
        }
        return substr($trimmed, $pos + 1);
    }

    /**
     * Get file extension without using pathinfo().
     *
     * @param string $path
     * @return string
     */
    private function getExtensionFromPath(string $path): string
    {
        $basename = $this->getBasename($path);
        $pos = strrpos($basename, '.');
        if ($pos === false) {
            return '';
        }
        return strtolower(substr($basename, $pos + 1));
    }
}
