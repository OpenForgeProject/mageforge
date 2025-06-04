<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker\HyvaThemes;

use OpenForgeProject\MageForge\Service\ThemeChecker\MagentoStandard\Checker as StandardChecker;

class Checker extends StandardChecker
{
    /**
     * {@inheritdoc}
     */
    public function detect(string $themePath): bool
    {
        // normalize path
        $themePath = rtrim($themePath, '/');

        // First check for tailwind directory in theme folder
        if (!file_exists($themePath . '/web/tailwind')) {
            return false;
        }

        // Check theme.xml for Hyva theme declaration
        if (file_exists($themePath . '/theme.xml')) {
            $themeXmlContent = file_get_contents($themePath . '/theme.xml');
            if ($themeXmlContent && stripos($themeXmlContent, 'hyva') !== false) {
                return true;
            }
        }

        // Check composer.json for Hyva module dependency
        if (file_exists($themePath . '/composer.json')) {
            $composerContent = file_get_contents($themePath . '/composer.json');
            if ($composerContent) {
                $composerJson = json_decode($composerContent, true);
                if (isset($composerJson['name']) && str_contains($composerJson['name'], 'hyva')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Hyvä';
    }

    /**
     * {@inheritdoc}
     */
    public function checkNpmDependencies(string $themePath): array
    {
        // Normalize path
        $themePath = rtrim($themePath, '/');

        // For Hyvä themes, check in web/tailwind
        if (file_exists($themePath . '/web/tailwind/package.json')) {
            $packageJsonPath = $themePath . '/web/tailwind';
        } else {
            if (!file_exists($themePath . '/package.json')) {
                return [];
            }
            $packageJsonPath = $themePath;
        }

        // Check if npm is installed
        if (!$this->isNpmInstalled()) {
            return ['error' => 'NPM not found on the system.'];
        }

        // Run npm outdated
        $cwd = getcwd();
        chdir($packageJsonPath);
        $output = [];
        $exitCode = null;
        exec('npm outdated --json 2>/dev/null', $output, $exitCode);
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
                // Add information about the path we checked if it's in web/tailwind
                if (strpos($packageJsonPath, '/web/tailwind') !== false) {
                    $outdated['_meta'] = [
                        'path' => 'web/tailwind',
                        'type' => 'Hyvä theme'
                    ];
                }
                return $outdated;
            }
        }

        // If we're here and exit code is 1, it likely means outdated packages were found
        // but npm had some issue with JSON output formatting
        if ($exitCode === 1) {
            // Try the non-JSON format and parse it manually
            $output = [];
            exec('npm outdated 2>/dev/null', $output);

            if (!empty($output)) {
                $result = $this->parseNpmOutdatedOutput($output);

                // Add information about the path we checked if it's in web/tailwind
                if (strpos($packageJsonPath, '/web/tailwind') !== false) {
                    $result['_meta'] = [
                        'path' => 'web/tailwind',
                        'type' => 'Hyvä theme'
                    ];
                }
                return $result;
            }
        }

        return ['error' => 'Error executing npm outdated command.'];
    }
}
