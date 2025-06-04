<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Spinner;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for checking Magento themes for outdated dependencies
 */
class CheckCommand extends AbstractCommand
{
    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'check'))
            ->setDescription('Checks a Magento theme for outdated dependencies')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'The codes of the themes to check'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCodes = $input->getArgument('themeCodes');

        if (empty($themeCodes)) {
            $themes = $this->themeList->getAllThemes();
            $options = array_map(fn($theme) => $theme->getCode(), $themes);

            $themeCodesPrompt = new MultiSelectPrompt(
                label: 'Select themes to check',
                options: $options,
                scroll: 10,
                hint: 'Arrow keys to navigate, Space to select, Enter to confirm',
            );

            $themeCodes = $themeCodesPrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        }

        return $this->processCheckThemes($themeCodes, $this->io);
    }

    /**
     * Process theme checking
     *
     * @param array $themeCodes
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @return int
     */
    private function processCheckThemes(
        array $themeCodes,
        SymfonyStyle $symfonyStyle
    ): int {
        $startTime = microtime(true);
        $results = [];
        $totalThemes = count($themeCodes);

        if (empty($themeCodes)) {
            $symfonyStyle->warning('No themes selected for checking.');
            return Command::SUCCESS;
        }

        $symfonyStyle->title(sprintf('Analyzing %d theme(s) for outdated dependencies', $totalThemes));

        // Process each theme
        foreach ($themeCodes as $index => $themeCode) {
            $currentTheme = $index + 1;
            // Show which theme is currently being checked
            $themeNameCyan = sprintf("<fg=cyan>%s</>", $themeCode);
            $spinner = new Spinner(sprintf("Analyzing %s (%d of %d) ...", $themeNameCyan, $currentTheme, $totalThemes));

            $themeResults = [];

            $spinner->spin(function() use ($themeCode, &$themeResults) {
                $themeResults = $this->checkTheme($themeCode);
                return true;
            });

            if (empty($themeResults)) {
                $symfonyStyle->writeln(sprintf("   Analyzing %s (%d of %d) ... <fg=yellow>no dependencies found</>", $themeNameCyan, $currentTheme, $totalThemes));
                continue;
            }

            $symfonyStyle->writeln(sprintf("   Analyzing %s (%d of %d) ... <fg=green>done</>", $themeNameCyan, $currentTheme, $totalThemes));
            $results[$themeCode] = $themeResults;
        }

        $this->displayCheckResults($symfonyStyle, $results, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

    /**
     * Check a single theme for outdated dependencies
     *
     * @param string $themeCode
     * @return array
     */
    private function checkTheme(string $themeCode): array
    {
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            return ['error' => "Theme $themeCode is not installed."];
        }

        $results = [
            'path' => $themePath,
            'composer' => $this->checkComposerDependencies($themePath),
            'npm' => $this->checkNpmDependencies($themePath),
        ];

        return $results;
    }

    /**
     * Check composer dependencies
     *
     * @param string $themePath
     * @return array
     */
    private function checkComposerDependencies(string $themePath): array
    {
        $composerJsonPath = $themePath . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return [];
        }

        // Check if composer is installed
        $composerCheckOutput = [];
        exec('which composer', $composerCheckOutput, $returnVar);
        if ($returnVar !== 0) {
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

            // Add a note that we're using the project root vendor
            $usingProjectRoot = true;
        } else {
            $usingProjectRoot = false;
        }

        // Determine the path where to run composer outdated
        $composerPath = $usingProjectRoot ? $this->findProjectRoot() : $themePath;

        // Run composer outdated
        $cwd = getcwd();
        chdir($composerPath);
        $output = [];
        $exitCode = 0;
        exec('composer outdated --direct --format=json 2>/dev/null', $output, $exitCode);
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
        exec('composer outdated --direct 2>/dev/null', $output);
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
    }

    /**
     * Check NPM dependencies
     *
     * @param string $themePath
     * @return array
     */
    private function checkNpmDependencies(string $themePath): array
    {
        // Determine the correct path to check for npm dependencies
        $packageJsonPath = $this->determineNpmPackagePath($themePath);

        // If no package.json found, return empty result
        if (empty($packageJsonPath)) {
            return [];
        }

        // Get npm dependency information
        $outdatedInfo = $this->executeNpmOutdated($packageJsonPath);

        // Add Hyvä theme metadata if needed
        $isHyvaTheme = $this->isHyvaTheme($themePath);
        if ($isHyvaTheme && !isset($outdatedInfo['error'])) {
            $outdatedInfo['_meta'] = [
                'path' => 'web/tailwind',
                'type' => 'Hyvä theme'
            ];
        }

        return $outdatedInfo;
    }

    /**
     * Display check results
     *
     * @param SymfonyStyle $symfonyStyle
     * @param array $results
     * @param float $duration
     */
    private function displayCheckResults(SymfonyStyle $symfonyStyle, array $results, float $duration): void
    {
        $symfonyStyle->newLine();
        $symfonyStyle->success(sprintf(
            "🔍 Dependency check completed in %.2f seconds",
            $duration
        ));

        if (empty($results)) {
            $symfonyStyle->warning('No themes were checked successfully.');
            return;
        }

        $composerIssuesCount = 0;
        $npmIssuesCount = 0;

        foreach ($results as $themeCode => $themeResults) {
            $symfonyStyle->section(sprintf("Results for theme <fg=cyan>%s</>", $themeCode));

            if (isset($themeResults['error'])) {
                $symfonyStyle->error($themeResults['error']);
                continue;
            }

            $this->displayComposerResults($symfonyStyle, $themeResults, $composerIssuesCount);
            $this->displayNpmResults($symfonyStyle, $themeResults, $npmIssuesCount);
        }

        // Display summary
        $this->displaySummary($symfonyStyle, $composerIssuesCount, $npmIssuesCount, count($results));
    }

    /**
     * Display composer results
     *
     * @param SymfonyStyle $symfonyStyle
     * @param array $themeResults
     * @param int &$composerIssuesCount
     */    private function displayComposerResults(SymfonyStyle $symfonyStyle, array $themeResults, int &$composerIssuesCount): void
    {
        // Display Composer outdated packages
        if (empty($themeResults['composer'])) {
            $symfonyStyle->writeln("✅ No outdated Composer packages found.");
            $symfonyStyle->newLine();
            return;
        }

        if (isset($themeResults['composer']['error'])) {
            $symfonyStyle->error($themeResults['composer']['error']);
            return;
        }

        if (isset($themeResults['composer']['warning'])) {
            $symfonyStyle->warning($themeResults['composer']['warning']);
            return;
        }

        // Check if we're using project root dependencies
        $projectRootInfo = '';

        if (isset($themeResults['composer']['_meta']) &&
            isset($themeResults['composer']['_meta']['using_project_root']) &&
            $themeResults['composer']['_meta']['using_project_root'] === true) {

            $projectRoot = $themeResults['composer']['_meta']['project_root'] ?? 'project root';
            $projectRootInfo = sprintf(" <fg=blue>[Using project dependencies from %s]</>", $projectRoot);

            // Remove meta information to prevent it from appearing in the results
            unset($themeResults['composer']['_meta']);
        }

        $symfonyStyle->writeln(sprintf("<fg=yellow>Outdated Composer packages:%s</>", $projectRootInfo));
        $table = new Table($symfonyStyle);
        $table->setHeaders(['Package', 'Current', 'Latest', 'Status']);

        foreach ($themeResults['composer'] as $package) {
            $status = $this->getStatusColor($package['latest-status'] ?? '');
            $table->addRow([
                $package['name'],
                $package['version'] ?? 'unknown',
                $package['latest'] ?? 'unknown',
                sprintf("<%s>%s</>", $status, $package['latest-status'] ?? 'unknown')
            ]);
            $composerIssuesCount++;
        }

        $table->render();
        $symfonyStyle->newLine();
    }

    /**
     * Display NPM results
     *
     * @param SymfonyStyle $symfonyStyle
     * @param array $themeResults
     * @param int &$npmIssuesCount
     */
    private function displayNpmResults(SymfonyStyle $symfonyStyle, array $themeResults, int &$npmIssuesCount): void
    {
        // Display NPM outdated packages
        if (empty($themeResults['npm'])) {
            $symfonyStyle->writeln("✅ No outdated NPM packages found.");
            $symfonyStyle->newLine();
            return;
        }

        if (isset($themeResults['npm']['error'])) {
            $symfonyStyle->error($themeResults['npm']['error']);
            return;
        }

        // Get path info for Hyvä themes
        $pathInfo = $this->getHyvaThemePathInfo($themeResults);

        $symfonyStyle->writeln(sprintf("<fg=yellow>Outdated NPM packages:%s</>", $pathInfo));
        $table = new Table($symfonyStyle);
        $table->setHeaders(['Package', 'Current', 'Wanted', 'Latest', 'Type']);

        foreach ($themeResults['npm'] as $packageName => $package) {
            $type = $this->getDependencyType($package);
            $table->addRow([
                $packageName,
                $package['current'] ?? 'unknown',
                $package['wanted'] ?? 'unknown',
                $package['latest'] ?? 'unknown',
                $type
            ]);
            $npmIssuesCount++;
        }

        $table->render();
        $symfonyStyle->newLine();
    }

    /**
     * Get color for status
     *
     * @param string $status
     * @return string
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'semver:patch' => 'fg=green',
            'semver:minor' => 'fg=yellow',
            'semver:major' => 'fg=red',
            default => 'fg=default',
        };
    }

    /**
     * Get dependency type with color
     *
     * @param array $package
     * @return string
     */
    private function getDependencyType(array $package): string
    {
        $type = $package['type'] ?? 'unknown';

        $color = match ($type) {
            'dependencies' => 'fg=white',
            'devDependencies' => 'fg=gray',
            'peerDependencies' => 'fg=cyan',
            'optionalDependencies' => 'fg=blue',
            default => 'fg=default',
        };

        return sprintf("<%s>%s</>", $color, $type);
    }

    /**
     * Display check summary
     *
     * @param SymfonyStyle $symfonyStyle
     * @param int $composerIssues
     * @param int $npmIssues
     * @param int $themeCount
     */
    private function displaySummary(
        SymfonyStyle $symfonyStyle,
        int $composerIssues,
        int $npmIssues,
        int $themeCount
    ): void {
        $symfonyStyle->section("📊 Summary");

        $totalIssues = $composerIssues + $npmIssues;

        if ($totalIssues === 0) {
            $symfonyStyle->success("All dependencies are up to date! No outdated packages found in any theme.");
            return;
        }

        $summary = [
            sprintf("Checked <fg=cyan>%d</> themes", $themeCount),
            sprintf("Found <fg=yellow>%d</> outdated packages:", $totalIssues),
            sprintf("  • <fg=blue>%d</> outdated Composer packages", $composerIssues),
            sprintf("  • <fg=blue>%d</> outdated NPM packages", $npmIssues),
        ];

        foreach ($summary as $line) {
            $symfonyStyle->writeln($line);
        }

        $symfonyStyle->newLine();

    }

    /**
     * Check if a theme is a Hyvä theme
     *
     * @param string $themePath
     * @return bool
     */
    private function isHyvaTheme(string $themePath): bool
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
     * Get path info string for Hyvä themes
     *
     * @param array $themeResults
     * @return string
     */
    private function getHyvaThemePathInfo(array &$themeResults): string
    {
        // Check if this is a Hyvä theme by looking at the _meta information
        $isHyvaTheme = isset($themeResults['npm']['_meta']) &&
                       isset($themeResults['npm']['_meta']['type']) &&
                       $themeResults['npm']['_meta']['type'] === 'Hyvä theme';

        if (!$isHyvaTheme) {
            return '';
        }

        $pathInfo = sprintf(" <fg=blue>[Hyvä theme, checked in %s]</>", $themeResults['npm']['_meta']['path']);

        // Remove meta information to prevent it from appearing in the results
        unset($themeResults['npm']['_meta']);

        return $pathInfo;
    }

    /**
     * Determine the correct path for npm package.json
     *
     * @param string $themePath
     * @return string
     */
    private function determineNpmPackagePath(string $themePath): string
    {
        // Normalize path
        $themePath = rtrim($themePath, '/');

        // Determine if this is a Hyvä theme
        $isHyvaTheme = $this->isHyvaTheme($themePath);

        // For Hyvä themes, check in web/tailwind
        if ($isHyvaTheme && file_exists($themePath . '/web/tailwind/package.json')) {
            return $themePath . '/web/tailwind';
        }

        // For standard themes, check in theme root
        if (file_exists($themePath . '/package.json')) {
            return $themePath;
        }

        // No package.json found
        return '';
    }

    /**
     * Execute npm outdated command and return results
     *
     * @param string $packagePath
     * @return array
     */
    private function executeNpmOutdated(string $packagePath): array
    {
        if (empty($packagePath)) {
            return [];
        }

        // Check if npm is installed
        $npmCheckOutput = [];
        exec('which npm', $npmCheckOutput, $returnVar);
        if ($returnVar !== 0) {
            return ['error' => 'NPM not found on the system.'];
        }

        // Run npm outdated
        $cwd = getcwd();
        chdir($packagePath);

        // Important: 'npm outdated' returns exit code 1 if there are outdated packages,
        // which is NOT an error in this context - it's the expected behavior.
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
                return $this->parseNpmOutdatedOutput($output);
            }
        }

        return ['error' => 'Error executing npm outdated command.'];
    }

    /**
     * Parse npm outdated output in non-JSON format
     *
     * @param array $output Lines of output from npm outdated command
     * @return array
     */
    private function parseNpmOutdatedOutput(array $output): array
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
     * Determine npm package type based on its location
     *
     * @param string $location
     * @return string
     */
    private function determinePackageType(string $location): string
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

    /**
     * Parse composer outdated output in non-JSON format
     *
     * @param array $output Lines of output from composer outdated command
     * @return array
     */
    private function parseComposerOutdatedOutput(array $output): array
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
                    $status = $this->determineComposerVersionStatus($version, $latest);

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
     * Determine the version status based on semver differences
     *
     * @param string $currentVersion
     * @param string $latestVersion
     * @return string
     */
    private function determineComposerVersionStatus(string $currentVersion, string $latestVersion): string
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
     * Find the Magento project root directory
     *
     * @return string
     */
    private function findProjectRoot(): string
    {
        // Start with the current working directory
        $path = getcwd();

        // Go up the directory tree looking for app/etc/env.php, which indicates the Magento root
        while ($path !== '/' && $path !== '') {
            if (file_exists($path . '/app/etc/env.php')) {
                return $path;
            }

            // Go one directory up
            $path = dirname($path);
        }

        return '';
    }
}
