<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Static;

use Laravel\Prompts\MultiSelectPrompt;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning static files and preprocessed view files for specific themes
 */
class CleanCommand extends AbstractCommand
{
    private array $originalEnv = [];
    private array $secureEnvStorage = [];

    /**
     * @param Filesystem $filesystem
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('static', 'clean'))
            ->setDescription('Clean var/view_preprocessed, pub/static, var/page_cache, var/tmp and generated directories for specific theme')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Theme codes to clean (format: Vendor/theme, Vendor/theme 2, ...)'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Clean all themes'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be cleaned without actually deleting anything'
            )
            ->setAliases(['m:s:c', 'frontend:clean']);
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCodes = $input->getArgument('themeCodes');
        $cleanAll = $input->getOption('all');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $this->io->note('DRY RUN MODE: No files will be deleted');
        }

        // If --all option is set, get all themes
        if ($cleanAll) {
            $themes = $this->themeList->getAllThemes();
            $themeCodes = array_values(array_map(fn($theme) => $theme->getCode(), $themes));

            if (empty($themeCodes)) {
                $this->io->info('No themes found.');
                return Cli::RETURN_SUCCESS;
            }

            $this->io->info(sprintf('Cleaning all %d theme%s...', count($themeCodes), count($themeCodes) === 1 ? '' : 's'));
        }

        // If no theme specified and --all not set, show theme selection
        if (empty($themeCodes)) {
            $themes = $this->themeList->getAllThemes();
            $options = array_map(fn($theme) => $theme->getCode(), $themes);

            // Check if we're in an interactive terminal environment
            if (!$this->isInteractiveTerminal($output)) {
                // Fallback for non-interactive environments
                $this->io->warning('No theme specified. Available themes:');

                if (empty($themes)) {
                    $this->io->info('No themes found.');
                    return Cli::RETURN_SUCCESS;
                }

                foreach ($themes as $theme) {
                    $this->io->writeln(sprintf('  - <fg=cyan>%s</> (%s)', $theme->getCode(), $theme->getThemeTitle()));
                }

                $this->io->newLine();
                $this->io->info('Usage: bin/magento mageforge:static:clean <theme-code> [<theme-code>...]');
                $this->io->info('       bin/magento mageforge:static:clean --all');
                $this->io->info('Example: bin/magento mageforge:static:clean Magento/luma');

                return Cli::RETURN_SUCCESS;
            }

            // Set environment variables for Laravel Prompts
            $this->setPromptEnvironment();

            $themeCodesPrompt = new MultiSelectPrompt(
                label: 'Select themes to clean',
                options: $options,
                default: [],
                hint: 'Arrow keys to navigate, Space to toggle, Enter to confirm (scroll with arrows if needed)',
                required: false,
            );

            try {
                $themeCodes = $themeCodesPrompt->prompt();
                \Laravel\Prompts\Prompt::terminal()->restoreTty();

                // Reset environment
                $this->resetPromptEnvironment();

                // If no themes selected, show info and exit
                if (empty($themeCodes)) {
                    $this->io->info('No themes selected.');
                    return Cli::RETURN_SUCCESS;
                }
            } catch (\Exception $e) {
                // Reset environment on exception
                $this->resetPromptEnvironment();
                // Fallback if prompt fails
                $this->io->error('Interactive mode failed: ' . $e->getMessage());
                $this->io->warning('No theme specified. Available themes:');

                foreach ($themes as $theme) {
                    $this->io->writeln(sprintf('  - <fg=cyan>%s</> (%s)', $theme->getCode(), $theme->getThemeTitle()));
                }

                $this->io->newLine();
                $this->io->info('Usage: bin/magento mageforge:static:clean <theme-code> [<theme-code>...]');

                return Cli::RETURN_SUCCESS;
            }
        }

        // Process all selected themes
        $totalThemes = count($themeCodes);
        $totalCleaned = 0;
        $failedThemes = [];

        foreach ($themeCodes as $index => $themeName) {
            $currentTheme = $index + 1;

            // Validate theme exists
            $themePath = $this->themePath->getPath($themeName);
            if ($themePath === null) {
                $this->io->error(sprintf("Theme '%s' not found.", $themeName));
                $failedThemes[] = $themeName;
                continue;
            }

            if ($totalThemes > 1) {
                $this->io->section(sprintf("Cleaning theme %d of %d: %s", $currentTheme, $totalThemes, $themeName));
            } else {
                $this->io->section(sprintf("Cleaning static files for theme: %s", $themeName));
            }

            $cleaned = 0;

            // Clean var/view_preprocessed
            $cleaned += $this->cleanViewPreprocessed($themeName, $dryRun);

            // Clean pub/static
            $cleaned += $this->cleanPubStatic($themeName, $dryRun);

            // Clean var/page_cache
            $cleaned += $this->cleanPageCache($dryRun);

            // Clean var/tmp
            $cleaned += $this->cleanVarTmp($dryRun);

            // Clean generated
            $cleaned += $this->cleanGenerated($dryRun);

            if ($cleaned > 0) {
                $action = $dryRun ? 'Would clean' : 'Cleaned';
                $this->io->writeln(sprintf(
                    "  <fg=green>✓</> %s %d director%s for theme '%s'",
                    $action,
                    $cleaned,
                    $cleaned === 1 ? 'y' : 'ies',
                    $themeName
                ));
                $totalCleaned += $cleaned;
            } else {
                $this->io->writeln(sprintf("  <fg=yellow>ℹ</> No files to clean for theme '%s'", $themeName));
            }
        }

        // Display summary
        $this->io->newLine();
        if ($totalThemes === 1) {
            if ($totalCleaned > 0) {
                $action = $dryRun ? 'Would clean' : 'Successfully cleaned';
                $this->io->success(sprintf(
                    "%s %d director%s for theme '%s'",
                    $action,
                    $totalCleaned,
                    $totalCleaned === 1 ? 'y' : 'ies',
                    $themeCodes[0]
                ));
            } else {
                $this->io->info(sprintf("No files to clean for theme '%s'", $themeCodes[0]));
            }
        } else {
            $successCount = $totalThemes - count($failedThemes);
            if ($successCount > 0 && $totalCleaned > 0) {
                $action = $dryRun ? 'Would clean' : 'Successfully cleaned';
                $this->io->success(sprintf(
                    "%s %d director%s across %d theme%s",
                    $action,
                    $totalCleaned,
                    $totalCleaned === 1 ? 'y' : 'ies',
                    $successCount,
                    $successCount === 1 ? '' : 's'
                ));
            } else {
                $this->io->info('No files were cleaned.');
            }

            if (!empty($failedThemes)) {
                $this->io->warning(sprintf(
                    "Failed to process %d theme%s: %s",
                    count($failedThemes),
                    count($failedThemes) === 1 ? '' : 's',
                    implode(', ', $failedThemes)
                ));
            }
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Check if the current environment supports interactive terminal input
     *
     * @param OutputInterface $output
     * @return bool
     */
    private function isInteractiveTerminal(OutputInterface $output): bool
    {
        // Check if output is decorated (supports ANSI codes)
        if (!$output->isDecorated()) {
            return false;
        }

        // Check if STDIN is available and readable
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return false;
        }

        // Check for common non-interactive environments
        $nonInteractiveEnvs = [
            'CI',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'TEAMCITY_VERSION',
        ];

        foreach ($nonInteractiveEnvs as $env) {
            if ($this->getEnvVar($env) || $this->getServerVar($env)) {
                return false;
            }
        }

        // Additional check: try to detect if running in a proper TTY
        $sttyOutput = shell_exec('stty -g 2>/dev/null');
        return !empty($sttyOutput);
    }

    /**
     * Set environment for Laravel Prompts to work properly in Docker/DDEV
     */
    private function setPromptEnvironment(): void
    {
        // Store original values for reset
        $this->originalEnv = [
            'COLUMNS' => $this->getEnvVar('COLUMNS'),
            'LINES' => $this->getEnvVar('LINES'),
            'TERM' => $this->getEnvVar('TERM'),
        ];

        // Set terminal environment variables using safe method
        $this->setEnvVar('COLUMNS', '100');
        $this->setEnvVar('LINES', '40');
        $this->setEnvVar('TERM', 'xterm-256color');
    }

    /**
     * Reset terminal environment after prompts
     */
    private function resetPromptEnvironment(): void
    {
        // Reset environment variables to original state
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                $this->removeSecureEnvironmentValue($key);
            } else {
                $this->setEnvVar($key, $value);
            }
        }
    }

    /**
     * Safely get environment variable with sanitization
     */
    private function getEnvVar(string $name): ?string
    {
        $value = $this->getSecureEnvironmentValue($name);

        if ($value === null || $value === '') {
            return null;
        }

        return $this->sanitizeEnvironmentValue($name, $value);
    }

    /**
     * Securely retrieve environment variable without direct superglobal access
     */
    private function getSecureEnvironmentValue(string $name): ?string
    {
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        $envVars = $this->getCachedEnvironmentVariables();
        return $envVars[$name] ?? null;
    }

    /**
     * Cache and filter environment variables safely
     */
    private function getCachedEnvironmentVariables(): array
    {
        static $cachedEnv = null;

        if ($cachedEnv === null) {
            $cachedEnv = [];
            $allowedVars = ['COLUMNS', 'LINES', 'TERM', 'CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'TEAMCITY_VERSION'];

            foreach ($allowedVars as $var) {
                if (isset($this->secureEnvStorage[$var])) {
                    $cachedEnv[$var] = $this->secureEnvStorage[$var];
                } else {
                    $globalEnv = filter_input_array(INPUT_ENV) ?: [];
                    if (array_key_exists($var, $globalEnv)) {
                        $cachedEnv[$var] = (string) $globalEnv[$var];
                    }
                }
            }
        }

        return $cachedEnv;
    }

    /**
     * Sanitize environment value based on variable type
     */
    private function sanitizeEnvironmentValue(string $name, string $value): ?string
    {
        return match($name) {
            'COLUMNS', 'LINES' => $this->sanitizeNumericValue($value),
            'TERM' => $this->sanitizeTermValue($value),
            'CI', 'GITHUB_ACTIONS', 'GITLAB_CI' => $this->sanitizeBooleanValue($value),
            'JENKINS_URL', 'TEAMCITY_VERSION' => $this->sanitizeAlphanumericValue($value),
            default => $this->sanitizeAlphanumericValue($value)
        };
    }

    /**
     * Sanitize numeric values
     */
    private function sanitizeNumericValue(string $value): ?string
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
        return $filtered !== false ? (string) $filtered : null;
    }

    /**
     * Sanitize terminal type values
     */
    private function sanitizeTermValue(string $value): ?string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '', $value);
        return (strlen($sanitized) > 0 && strlen($sanitized) <= 50) ? $sanitized : null;
    }

    /**
     * Sanitize boolean-like values
     */
    private function sanitizeBooleanValue(string $value): ?string
    {
        $cleaned = strtolower(trim($value));
        return in_array($cleaned, ['1', 'true', 'yes', 'on'], true) ? $cleaned : null;
    }

    /**
     * Sanitize alphanumeric values
     */
    private function sanitizeAlphanumericValue(string $value): ?string
    {
        $sanitized = preg_replace('/[^\w\-.]/', '', $value);
        return (strlen($sanitized) > 0 && strlen($sanitized) <= 255) ? $sanitized : null;
    }

    /**
     * Safely get server variable with sanitization
     */
    private function getServerVar(string $name): ?string
    {
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        $value = filter_input(INPUT_SERVER, $name);

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        return $this->sanitizeAlphanumericValue((string) $value);
    }

    /**
     * Safely set environment variable with validation
     */
    private function setEnvVar(string $name, string $value): void
    {
        if (empty($name) || !is_string($name)) {
            return;
        }

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return;
        }

        $sanitizedValue = $this->sanitizeEnvironmentValue($name, $value);

        if ($sanitizedValue !== null) {
            $this->setSecureEnvironmentValue($name, $sanitizedValue);
        }
    }

    /**
     * Securely store environment variable without direct superglobal access
     */
    private function setSecureEnvironmentValue(string $name, string $value): void
    {
        if (!isset($this->secureEnvStorage)) {
            $this->secureEnvStorage = [];
        }
        $this->secureEnvStorage[$name] = $value;
    }

    /**
     * Securely remove environment variable from cache
     */
    private function removeSecureEnvironmentValue(string $name): void
    {
        unset($this->secureEnvStorage[$name]);
        $this->clearEnvironmentCache();
    }

    /**
     * Clear the environment variable cache
     */
    private function clearEnvironmentCache(): void
    {
        $this->secureEnvStorage = [];
    }

    /**
     * Clean var/view_preprocessed directory for the theme
     *
     * @param string $themeName
     * @param bool $dryRun
     * @return int Number of directories cleaned
     */
    private function cleanViewPreprocessed(string $themeName, bool $dryRun = false): int
    {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        // Extract vendor and theme parts
        $themeParts = $this->parseThemeName($themeName);
        if ($themeParts === null) {
            return 0;
        }

        [$vendor, $theme] = $themeParts;

        // Check if view_preprocessed directory exists
        if (!$varDirectory->isDirectory('view_preprocessed')) {
            return 0;
        }

        // Pattern: view_preprocessed/css/frontend/Vendor/theme
        // and view_preprocessed/source/frontend/Vendor/theme
        $pathsToClean = [
            sprintf('view_preprocessed/css/frontend/%s/%s', $vendor, $theme),
            sprintf('view_preprocessed/source/frontend/%s/%s', $vendor, $theme),
        ];

        foreach ($pathsToClean as $path) {
            if ($varDirectory->isDirectory($path)) {
                try {
                    if (!$dryRun) {
                        $varDirectory->delete($path);
                    }
                    $action = $dryRun ? 'Would clean' : 'Cleaned';
                    $this->io->writeln(sprintf('  <fg=green>✓</> %s: var/%s', $action, $path));
                    $cleaned++;
                } catch (\Exception $e) {
                    $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: var/%s - %s', $path, $e->getMessage()));
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean pub/static directory for the theme
     *
     * @param string $themeName
     * @param bool $dryRun
     * @return int Number of directories cleaned
     */
    private function cleanPubStatic(string $themeName, bool $dryRun = false): int
    {
        $cleaned = 0;
        $staticDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);

        // Extract vendor and theme parts
        $themeParts = $this->parseThemeName($themeName);
        if ($themeParts === null) {
            return 0;
        }

        [$vendor, $theme] = $themeParts;

        // Check if frontend directory exists in pub/static
        if (!$staticDirectory->isDirectory('frontend')) {
            return 0;
        }

        // Pattern: frontend/Vendor/theme
        $pathToClean = sprintf('frontend/%s/%s', $vendor, $theme);

        if ($staticDirectory->isDirectory($pathToClean)) {
            try {
                if (!$dryRun) {
                    $staticDirectory->delete($pathToClean);
                }
                $action = $dryRun ? 'Would clean' : 'Cleaned';
                $this->io->writeln(sprintf('  <fg=green>✓</> %s: pub/static/%s', $action, $pathToClean));
                $cleaned++;
            } catch (\Exception $e) {
                $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: pub/static/%s - %s', $pathToClean, $e->getMessage()));
            }
        }

        return $cleaned;
    }

    /**
     * Clean var/page_cache directory
     *
     * @param bool $dryRun
     * @return int Number of directories cleaned
     */
    private function cleanPageCache(bool $dryRun = false): int
    {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if ($varDirectory->isDirectory('page_cache')) {
            try {
                if (!$dryRun) {
                    $varDirectory->delete('page_cache');
                }
                $action = $dryRun ? 'Would clean' : 'Cleaned';
                $this->io->writeln(sprintf('  <fg=green>✓</> %s: var/page_cache', $action));
                $cleaned++;
            } catch (\Exception $e) {
                $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: var/page_cache - %s', $e->getMessage()));
            }
        }

        return $cleaned;
    }

    /**
     * Clean var/tmp directory
     *
     * @param bool $dryRun
     * @return int Number of directories cleaned
     */
    private function cleanVarTmp(bool $dryRun = false): int
    {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if ($varDirectory->isDirectory('tmp')) {
            try {
                if (!$dryRun) {
                    $varDirectory->delete('tmp');
                }
                $action = $dryRun ? 'Would clean' : 'Cleaned';
                $this->io->writeln(sprintf('  <fg=green>✓</> %s: var/tmp', $action));
                $cleaned++;
            } catch (\Exception $e) {
                $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: var/tmp - %s', $e->getMessage()));
            }
        }

        return $cleaned;
    }

    /**
     * Clean generated directory
     *
     * @param bool $dryRun
     * @return int Number of directories cleaned
     */
    private function cleanGenerated(bool $dryRun = false): int
    {
        $cleaned = 0;
        $generatedDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::GENERATED);

        try {
            $deletedCount = 0;

            // Manually check for 'code' directory (the main generated content)
            if ($generatedDirectory->isDirectory('code')) {
                try {
                    if (!$dryRun) {
                        $generatedDirectory->delete('code');
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: generated/code - %s', $e->getMessage()));
                }
            }

            // Check for 'metadata' directory
            if ($generatedDirectory->isDirectory('metadata')) {
                try {
                    if (!$dryRun) {
                        $generatedDirectory->delete('metadata');
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: generated/metadata - %s', $e->getMessage()));
                }
            }

            if ($deletedCount > 0) {
                $action = $dryRun ? 'Would clean' : 'Cleaned';
                $this->io->writeln(sprintf('  <fg=green>✓</> %s: generated/*', $action));
                $cleaned++;
            }
        } catch (\Exception $e) {
            $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: generated - %s', $e->getMessage()));
        }

        return $cleaned;
    }

    /**
     * Parse theme name into vendor and theme parts
     *
     * @param string $themeName
     * @return array|null Array with [vendor, theme] or null if invalid format
     */
    private function parseThemeName(string $themeName): ?array
    {
        $themeParts = explode('/', $themeName);
        if (count($themeParts) !== 2) {
            return null;
        }

        return $themeParts;
    }
}
