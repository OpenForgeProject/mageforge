<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker\MagentoStandard;

use OpenForgeProject\MageForge\Service\ThemeChecker\AbstractChecker;

class Checker extends AbstractChecker
{
    /**
     * {@inheritdoc}
     */
    public function detect(string $themePath): bool
    {
        // Any Magento theme is acceptable for this checker
        return file_exists($themePath . '/theme.xml');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Standard Magento';
    }

    /**
     * {@inheritdoc}
     */
    public function checkComposerDependencies(string $themePath): array
    {
        $composerJsonPath = $themePath . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return [];
        }

        // Check if composer is installed
        if (!$this->isComposerInstalled()) {
            return ['error' => 'Composer not found on the system.'];
        }

        // Check if the theme has a vendor directory
        $hasVendorDir = is_dir($themePath . '/vendor');

        // If there is no vendor directory in the theme, try to use the project root vendor
        if (!$hasVendorDir) {
            $projectRoot = $this->findProjectRoot();
            if (empty($projectRoot) || !is_dir($projectRoot . '/vendor')) {
                return ['warning' => 'No vendor directory found in theme or project root.'];
            }

            // Use the project root for checking dependencies
            $usingProjectRoot = true;
            $composerPath = $projectRoot;
        } else {
            $usingProjectRoot = false;
            $composerPath = $themePath;
        }

        // Run composer outdated
        $cwd = getcwd();
        chdir($composerPath);
        $output = [];
        $exitCode = 0;
        $this->safeExec('composer outdated --direct --format=json 2>/dev/null', $output, $exitCode);
        chdir($cwd);

        // Parse JSON output if available
        if (!empty($output)) {
            $jsonOutput = implode('', $output);
            $outdated = json_decode($jsonOutput, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($outdated['installed'])) {
                $result = $outdated['installed'];
                if ($usingProjectRoot) {
                    $result['_meta'] = [
                        'using_project_root' => true,
                        'project_root' => $composerPath
                    ];
                }
                return $result;
            }
        }

        // If JSON parsing failed or no structured output, try the table format
        $output = [];
        chdir($composerPath);
        $this->safeExec('composer outdated --direct 2>/dev/null', $output);
        chdir($cwd);

        if (!empty($output)) {
            $result = $this->parseComposerOutdatedOutput($output);
            if ($usingProjectRoot) {
                $result['_meta'] = [
                    'using_project_root' => true,
                    'project_root' => $composerPath
                ];
            }
            return $result;
        }

        return ['error' => 'Error parsing composer outdated output.'];
    }    /**
     * {@inheritdoc}
     */
    public function checkNpmDependencies(string $themePath): array
    {
        // Normalize path
        $themePath = rtrim($themePath, '/');

        // Determine the correct package.json path
        $packageJsonInfo = $this->findPackageJsonPath($themePath);

        if (empty($packageJsonInfo['path'])) {
            return []; // No package.json found
        }

        // Check if npm is installed
        if (!$this->isNpmInstalled()) {
            return ['error' => 'NPM not found on the system.'];
        }

        return $this->executeNpmOutdated($packageJsonInfo['path'], $packageJsonInfo['using_project_root']);
    }

    /**
     * Find the appropriate package.json path
     *
     * @param string $themePath
     * @return array
     */
    protected function findPackageJsonPath(string $themePath): array
    {
        // First check theme directory
        if (file_exists($themePath . '/package.json')) {
            return [
                'path' => $themePath,
                'using_project_root' => false
            ];
        }

        // If not in theme directory, try project root
        $projectRoot = $this->findProjectRoot();
        if (!empty($projectRoot) && file_exists($projectRoot . '/package.json')) {
            return [
                'path' => $projectRoot,
                'using_project_root' => true
            ];
        }

        return ['path' => '', 'using_project_root' => false];
    }

    /**
     * Execute npm outdated command
     *
     * @param string $packageJsonPath
     * @param bool $usingProjectRoot
     * @return array
     */
    protected function executeNpmOutdated(string $packageJsonPath, bool $usingProjectRoot): array
    {
        $cwd = getcwd();
        chdir($packageJsonPath);
        $output = [];
        $exitCode = null;
        $this->safeExec('npm outdated --json 2>/dev/null', $output, $exitCode);
        chdir($cwd);

        // Check if we have output regardless of exit code
        if (!empty($output)) {
            $jsonOutput = implode('', $output);
            if (empty($jsonOutput) || $jsonOutput === '{}') {
                return []; // No outdated packages
            }

            // Parse JSON output
            $outdated = json_decode($jsonOutput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->addProjectRootMeta($outdated, $usingProjectRoot, $packageJsonPath);
            }
        }

        // If we're here and exit code is 1, it likely means outdated packages were found
        // but npm had some issue with JSON output formatting
        if ($exitCode === 1) {
            // Try the non-JSON format and parse it manually
            $output = [];
            $this->safeExec('npm outdated 2>/dev/null', $output);

            if (!empty($output)) {
                $result = $this->parseNpmOutdatedOutput($output);
                return $this->addProjectRootMeta($result, $usingProjectRoot, $packageJsonPath);
            }
        }

        return ['error' => 'Error executing npm outdated command.'];
    }

    /**
     * Add project root metadata to results
     *
     * @param array $result
     * @param bool $usingProjectRoot
     * @param string $packageJsonPath
     * @return array
     */
    protected function addProjectRootMeta(array $result, bool $usingProjectRoot, string $packageJsonPath): array
    {
        if ($usingProjectRoot) {
            $result['_meta'] = [
                'using_project_root' => true,
                'project_root' => $packageJsonPath,
                'path' => 'project root',
                'type' => 'Magento Standard'
            ];
        }

        return $result;
    }
}
