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
        return $this->fileSystem->fileExists($themePath . '/theme.xml');
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
        if (!$this->fileSystem->fileExists($composerJsonPath)) {
            return [];
        }

        // Check if composer is installed
        if (!$this->isComposerInstalled()) {
            return ['error' => 'Composer not found on the system.'];
        }

        // Find the correct composer path to use
        $composerConfig = $this->findComposerPath($themePath);
        if (isset($composerConfig['warning'])) {
            return $composerConfig;
        }

        $composerPath = $composerConfig['path'];
        $usingProjectRoot = $composerConfig['using_project_root'];

        // Get dependencies using JSON format first
        $result = $this->getComposerDependenciesJson($composerPath);

        // If JSON format failed, try table format
        if (empty($result)) {
            $result = $this->getComposerDependenciesTable($composerPath);
        }

        // If we have results, add metadata
        if (!empty($result) && !isset($result['error'])) {
            $result = $this->addComposerMetadata($result, $usingProjectRoot, $composerPath);
            return $result;
        }

        return $result ?: ['error' => 'Error parsing composer outdated output.'];
    }

    /**
     * Find the appropriate path for composer operations
     *
     * @param string $themePath The theme path to check
     * @return array Configuration with path and flag
     */
    protected function findComposerPath(string $themePath): array
    {
        // Check if the theme has a vendor directory
        $hasVendorDir = $this->fileSystem->isDir($themePath . '/vendor');

        if ($hasVendorDir) {
            return [
                'path' => $themePath,
                'using_project_root' => false
            ];
        }

        // If no vendor directory in theme, try project root
        $projectRoot = $this->findProjectRoot();
        if (!empty($projectRoot) && $this->fileSystem->isDir($projectRoot . '/vendor')) {
            return [
                'path' => $projectRoot,
                'using_project_root' => true
            ];
        }

        // No valid vendor directory found
        return ['warning' => 'No vendor directory found in theme or project root.'];
    }

    /**
     * Get composer dependencies using JSON format
     *
     * @param string $composerPath Path to run composer in
     * @return array Results or empty array if failed
     */
    protected function getComposerDependenciesJson(string $composerPath): array
    {
        $cwd = $this->fileSystem->getCurrentDir();
        $this->fileSystem->changeDir($composerPath);

        $output = [];
        $exitCode = 0;
        $this->safeExec('composer outdated --direct --format=json 2>/dev/null', $output, $exitCode);

        $this->fileSystem->changeDir($cwd);

        if (empty($output)) {
            return [];
        }

        $jsonOutput = implode('', $output);
        $outdated = json_decode($jsonOutput, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($outdated['installed'])) {
            return $outdated['installed'];
        }

        return [];
    }

    /**
     * Get composer dependencies using table format
     *
     * @param string $composerPath Path to run composer in
     * @return array Results or empty array if failed
     */
    protected function getComposerDependenciesTable(string $composerPath): array
    {
        $cwd = $this->fileSystem->getCurrentDir();
        $this->fileSystem->changeDir($composerPath);

        $output = [];
        $this->safeExec('composer outdated --direct 2>/dev/null', $output);

        $this->fileSystem->changeDir($cwd);

        if (!empty($output)) {
            return $this->parseComposerOutdatedOutput($output);
        }

        return [];
    }

    /**
     * Add metadata to composer results
     *
     * @param array $result The dependency results
     * @param bool $usingProjectRoot Whether using project root
     * @param string $composerPath The path used
     * @return array Results with metadata
     */
    protected function addComposerMetadata(array $result, bool $usingProjectRoot, string $composerPath): array
    {
        if ($usingProjectRoot) {
            $result['_meta'] = [
                'using_project_root' => true,
                'project_root' => $composerPath
            ];
        }

        return $result;
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
        if ($this->fileSystem->fileExists($themePath . '/package.json')) {
            return [
                'path' => $themePath,
                'using_project_root' => false
            ];
        }

        // If not in theme directory, try project root
        $projectRoot = $this->findProjectRoot();
        if (!empty($projectRoot) && $this->fileSystem->fileExists($projectRoot . '/package.json')) {
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
        $cwd = $this->fileSystem->getCurrentDir();
        $this->fileSystem->changeDir($packageJsonPath);
        $output = [];
        $exitCode = null;
        $this->safeExec('npm outdated --json 2>/dev/null', $output, $exitCode);
        $this->fileSystem->changeDir($cwd);

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
