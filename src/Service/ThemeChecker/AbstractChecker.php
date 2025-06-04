<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker;

abstract class AbstractChecker implements CheckerInterface
{
    /**
     * @var FileSystem
     */
    protected FileSystem $fileSystem;

    /**
     * AbstractChecker constructor
     */
    public function __construct()
    {
        $this->fileSystem = new FileSystem();
    }

    /**
     * Execute a system command safely
     *
     * @param string $command The command to execute
     * @param array $output Command output will be stored here
     * @param int|null $returnVar Command return code will be stored here
     * @return bool True on success, false on failure
     */
    protected function safeExec(string $command, array &$output = [], int &$returnVar = null): bool
    {
        // List of allowed commands for security
        $allowedCommands = [
            'which composer',
            'which npm',
            'composer outdated --direct --format=json 2>/dev/null',
            'composer outdated --direct 2>/dev/null',
            'npm outdated --json 2>/dev/null',
            'npm outdated 2>/dev/null'
        ];

        // Check if command is exactly one of the allowed commands
        if (!in_array($command, $allowedCommands, true)) {
            // Command not in the allowed list
            $returnVar = 1;
            $output = ['Error: Command not allowed for security reasons.'];
            return false;
        }

        // Execute the command in a safe manner
        exec($command, $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Execute a system command safely with arguments
     *
     * @param string $baseCommand The base command to execute (e.g., 'npm', 'composer')
     * @param array $allowedArgs List of allowed arguments
     * @param array $args Arguments to use
     * @param array $output Command output will be stored here
     * @param int|null $returnVar Command return code will be stored here
     * @return bool True on success, false on failure
     */
    protected function safeExecWithArgs(string $baseCommand, array $allowedArgs, array $args, array &$output = [], int &$returnVar = null): bool
    {
        // List of allowed base commands
        $allowedBaseCommands = ['composer', 'npm', 'which'];

        // Check if base command is allowed
        if (!in_array($baseCommand, $allowedBaseCommands, true)) {
            $returnVar = 1;
            $output = ['Error: Base command not allowed for security reasons.'];
            return false;
        }

        // Validate and sanitize arguments
        $safeArgs = [];
        foreach ($args as $arg) {
            // Remove any potentially dangerous characters
            $sanitizedArg = preg_replace('/[;&|`$(){}]/', '', $arg);

            // Check if the sanitized argument is in the allowed list
            if (!in_array($sanitizedArg, $allowedArgs, true)) {
                $returnVar = 1;
                $output = ['Error: Argument not allowed for security reasons: ' . $arg];
                return false;
            }

            $safeArgs[] = $sanitizedArg;
        }

        // Build the safe command
        $command = $baseCommand;
        if (!empty($safeArgs)) {
            $command .= ' ' . implode(' ', $safeArgs);
        }

        // Execute the command
        exec($command, $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Check if composer is installed
     *
     * @return bool
     */
    protected function isComposerInstalled(): bool
    {
        $returnVar = null;
        $output = [];
        $this->safeExec('which composer', $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Check if npm is installed
     *
     * @return bool
     */
    protected function isNpmInstalled(): bool
    {
        $returnVar = null;
        $output = [];
        $this->safeExec('which npm', $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Find the Magento project root directory
     *
     * @return string
     */
    protected function findProjectRoot(): string
    {
        // Start with the current working directory
        $path = $this->fileSystem->getCurrentDir();

        // Go up the directory tree looking for app/etc/env.php, which indicates the Magento root
        while ($path !== '/' && $path !== '') {
            if ($this->fileSystem->fileExists($path . '/app/etc/env.php')) {
                return $path;
            }

            // Go one directory up
            $path = $this->fileSystem->getDirname($path);
        }

        return '';
    }

    /**
     * Parse composer outdated output in non-JSON format
     *
     * @param array $output Lines of output from composer outdated command
     * @return array
     */
    protected function parseComposerOutdatedOutput(array $output): array
    {
        $result = [];

        // Skip header lines (first 3 lines usually)
        $startParsing = false;
        foreach ($output as $line) {
            $line = trim($line);

            // Look for the separator line with dashes
            if (!$startParsing && strpos($line, '---') === 0) {
                $startParsing = true;
                continue;
            }

            // Parse actual package lines
            if ($startParsing && !empty($line) && $line !== '---') {
                // Split by whitespace, but respect multiple spaces
                $parts = preg_split('/\s+/', $line);

                if (count($parts) >= 3) {
                    $name = trim($parts[0]);
                    $version = trim($parts[1]);
                    $latest = trim($parts[2]);

                    // Determine latest-status
                    $status = $this->determineVersionStatus($version, $latest);

                    $result[] = [
                        'name' => $name,
                        'version' => $version,
                        'latest' => $latest,
                        'latest-status' => $status
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Parse npm outdated output in non-JSON format
     *
     * @param array $output Lines of output from npm outdated command
     * @return array
     */
    protected function parseNpmOutdatedOutput(array $output): array
    {
        $result = [];

        // Skip the first line which is the header
        if (count($output) > 1) {
            array_shift($output);

            foreach ($output as $line) {
                // Split by whitespace, but respect multiple spaces
                $parts = preg_split('/\s+/', trim($line));

                if (count($parts) >= 4) {
                    $package = $parts[0];
                    $current = $parts[1];
                    $wanted = $parts[2];
                    $latest = $parts[3];
                    $location = isset($parts[4]) ? $parts[4] : '';

                    $result[$package] = [
                        'current' => $current,
                        'wanted' => $wanted,
                        'latest' => $latest,
                        'location' => $location,
                        'type' => $this->determinePackageType($location)
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Determine the version status based on semver differences
     *
     * @param string $currentVersion
     * @param string $latestVersion
     * @return string
     */
    protected function determineVersionStatus(string $currentVersion, string $latestVersion): string
    {
        // Remove any prefixes like v, V, etc.
        $currentVersion = ltrim($currentVersion, 'vV');
        $latestVersion = ltrim($latestVersion, 'vV');

        // Extract version components
        $current = explode('.', $currentVersion);
        $latest = explode('.', $latestVersion);

        // Ensure we have at least three components (major.minor.patch)
        $currentCount = count($current);
        for ($i = $currentCount; $i < 3; $i++) {
            $current[] = '0';
        }

        $latestCount = count($latest);
        for ($i = $latestCount; $i < 3; $i++) {
            $latest[] = '0';
        }

        // Compare major versions
        if ((int)$latest[0] > (int)$current[0]) {
            return 'semver:major';
        }

        // Compare minor versions (if major is the same)
        if ((int)$latest[0] === (int)$current[0] && (int)$latest[1] > (int)$current[1]) {
            return 'semver:minor';
        }

        // Compare patch versions (if major and minor are the same)
        if ((int)$latest[0] === (int)$current[0] &&
            (int)$latest[1] === (int)$current[1] &&
            (int)$latest[2] > (int)$current[2]) {
            return 'semver:patch';
        }

        return 'up-to-date';
    }

    /**
     * Determine npm package type based on its location
     *
     * @param string $location
     * @return string
     */
    protected function determinePackageType(string $location): string
    {
        if (strpos($location, 'node_modules') !== false) {
            if (strpos($location, 'dependencies') !== false) {
                return 'dependencies';
            } elseif (strpos($location, 'devDependencies') !== false) {
                return 'devDependencies';
            } elseif (strpos($location, 'peerDependencies') !== false) {
                return 'peerDependencies';
            }
        }

        return 'dependencies'; // Default
    }
}
