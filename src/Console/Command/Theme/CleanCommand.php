<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\MultiSelectPrompt;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeCleaner;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning static files and preprocessed view files for specific themes
 */
class CleanCommand extends AbstractCommand
{
    /** @var array<string, string|null>|null */
    private static ?array $cachedEnv = null;
    /** @var array<string, string|null> */
    private array $originalEnv = [];
    /** @var array<string, string|null> */
    private array $secureEnvStorage = [];

    /**
     * @param ThemeCleaner $themeCleaner
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param ThemeSuggester $themeSuggester
     */
    public function __construct(
        private readonly ThemeCleaner $themeCleaner,
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly ThemeSuggester $themeSuggester
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'clean'))
            ->setDescription('Clean theme static files and cache directories')
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
            ->setAliases(['frontend:clean']);
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $this->io->note('DRY RUN MODE: No files will be deleted');
        }

        $themeCodes = $this->resolveThemeCodes($input, $output);

        if ($themeCodes === null) {
            return Cli::RETURN_SUCCESS;
        }

        [$totalCleaned, $failedThemes] = $this->processThemes($themeCodes, $dryRun, $output);

        $this->displaySummary($themeCodes, $totalCleaned, $failedThemes, $dryRun);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Resolve which themes to clean based on input
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array<string>|null Array of theme codes or null to exit
     */
    private function resolveThemeCodes(InputInterface $input, OutputInterface $output): ?array
    {
        $themeCodes = $input->getArgument('themeCodes');
        $cleanAll = $input->getOption('all');

        if ($cleanAll) {
            return $this->getAllThemeCodes();
        }

        if (empty($themeCodes)) {
            return $this->selectThemesInteractively($output);
        }

        return $themeCodes;
    }

    /**
     * Get all theme codes
     *
     * @return array<string>|null
     */
    private function getAllThemeCodes(): ?array
    {
        $themes = $this->themeList->getAllThemes();
        $themeCodes = array_values(array_map(fn($theme) => $theme->getCode(), $themes));

        if (empty($themeCodes)) {
            $this->io->info('No themes found.');
            return null;
        }

        $this->io->info(sprintf('Cleaning all %d theme%s...', count($themeCodes), count($themeCodes) === 1 ? '' : 's'));

        return $themeCodes;
    }

    /**
     * Select themes interactively
     *
     * @param OutputInterface $output
     * @return array<string>|null
     */
    private function selectThemesInteractively(OutputInterface $output): ?array
    {
        $themes = $this->themeList->getAllThemes();
        $options = array_map(fn($theme) => $theme->getCode(), $themes);

        if (!$this->isInteractiveTerminal($output)) {
            $this->displayAvailableThemes($themes);
            return null;
        }

        return $this->promptForThemes($options, $themes);
    }

    /**
     * Display available themes for non-interactive environments
     *
     * @param array<mixed> $themes
     * @return void
     */
    private function displayAvailableThemes(array $themes): void
    {
        $this->io->warning('No theme specified. Available themes:');

        if (empty($themes)) {
            $this->io->info('No themes found.');
            return;
        }

        foreach ($themes as $theme) {
            $this->io->writeln(sprintf('  - <fg=cyan>%s</> (%s)', $theme->getCode(), $theme->getThemeTitle()));
        }

        $this->io->newLine();
        $this->io->info('Usage: bin/magento mageforge:theme:clean <theme-code> [<theme-code>...]');
        $this->io->info('       bin/magento mageforge:theme:clean --all');
        $this->io->info('Example: bin/magento mageforge:theme:clean Magento/luma');
    }

    /**
     * Prompt user to select themes
     *
     * @param array<string> $options
     * @param array<mixed> $themes
     * @return array<string>|null
     */
    private function promptForThemes(array $options, array $themes): ?array
    {
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
            $this->resetPromptEnvironment();

            if (empty($themeCodes)) {
                $this->io->info('No themes selected.');
                return null;
            }

            return $themeCodes;
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            $this->displayAvailableThemes($themes);
            return null;
        }
    }

    /**
     * Process cleaning for all selected themes
     *
     * @param array<string> $themeCodes
     * @param bool $dryRun
     * @param OutputInterface $output
     * @return array<int, mixed> [totalCleaned, failedThemes]
     */
    private function processThemes(array $themeCodes, bool $dryRun, OutputInterface $output): array
    {
        $totalThemes = count($themeCodes);
        $totalCleaned = 0;
        $failedThemes = [];

        foreach ($themeCodes as $index => $themeName) {
            $currentTheme = $index + 1;

            // Validate and potentially correct theme name
            $validatedTheme = $this->validateTheme($themeName, $failedThemes, $output);

            if ($validatedTheme === null) {
                continue;
            }

            // Use validated/corrected theme name
            $this->displayThemeHeader($validatedTheme, $currentTheme, $totalThemes);

            $cleaned = $this->cleanThemeDirectories($validatedTheme, $dryRun);

            $this->displayThemeResult($validatedTheme, $cleaned, $dryRun);

            $totalCleaned += $cleaned;
        }

        return [$totalCleaned, $failedThemes];
    }

    /**
     * Validate theme exists
     *
     * @param string $themeName
     * @param array<string> $failedThemes
     * @param OutputInterface $output
     * @return string|null Theme code if valid or corrected, null if invalid
     */
    private function validateTheme(string $themeName, array &$failedThemes, OutputInterface $output): ?string
    {
        $themePath = $this->themePath->getPath($themeName);

        if ($themePath === null) {
            // Try to suggest similar themes
            $correctedTheme = $this->handleInvalidThemeWithSuggestions(
                $themeName,
                $this->themeSuggester,
                $output
            );

            // If no theme was selected, mark as failed
            if ($correctedTheme === null) {
                $failedThemes[] = $themeName;
                return null;
            }

            // Use the corrected theme code
            $themePath = $this->themePath->getPath($correctedTheme);

            // Double-check the corrected theme exists
            if ($themePath === null) {
                $this->io->error(sprintf("Theme '%s' not found.", $correctedTheme));
                $failedThemes[] = $themeName;
                return null;
            }

            $this->io->info("Using theme: $correctedTheme");
            return $correctedTheme;
        }

        return $themeName;
    }

    /**
     * Display header for theme being cleaned
     *
     * @param string $themeName
     * @param int $currentTheme
     * @param int $totalThemes
     * @return void
     */
    private function displayThemeHeader(string $themeName, int $currentTheme, int $totalThemes): void
    {
        if ($totalThemes > 1) {
            $this->io->section(sprintf("Cleaning theme %d of %d: %s", $currentTheme, $totalThemes, $themeName));
        } else {
            $this->io->section(sprintf("Cleaning static files for theme: %s", $themeName));
        }
    }

    /**
     * Clean all directories for a theme
     *
     * @param string $themeName
     * @param bool $dryRun
     * @return int Number of directories cleaned
     */
    private function cleanThemeDirectories(string $themeName, bool $dryRun): int
    {
        static $globalCleaned = false;

        $cleaned = 0;
        $cleaned += $this->themeCleaner->cleanViewPreprocessed($themeName, $this->io, $dryRun, true);
        $cleaned += $this->themeCleaner->cleanPubStatic($themeName, $this->io, $dryRun, true);

        if (!$globalCleaned) {
            $cleaned += $this->themeCleaner->cleanPageCache($this->io, $dryRun, true);
            $cleaned += $this->themeCleaner->cleanVarTmp($this->io, $dryRun, true);
            $cleaned += $this->themeCleaner->cleanGenerated($this->io, $dryRun, true);
            $globalCleaned = true;
        }
        return $cleaned;
    }

    /**
     * Display result for individual theme
     *
     * @param string $themeName
     * @param int $cleaned
     * @param bool $dryRun
     * @return void
     */
    private function displayThemeResult(string $themeName, int $cleaned, bool $dryRun): void
    {
        if ($cleaned > 0) {
            $action = $dryRun ? 'Would clean' : 'Cleaned';
            $this->io->writeln(sprintf(
                "  <fg=green>✓</> %s %d director%s for theme '%s'",
                $action,
                $cleaned,
                $cleaned === 1 ? 'y' : 'ies',
                $themeName
            ));
        } else {
            $this->io->writeln(sprintf("  <fg=yellow>ℹ</> No files to clean for theme '%s'", $themeName));
        }
    }

    /**
     * Display summary of cleaning operation
     *
     * @param array<string> $themeCodes
     * @param int $totalCleaned
     * @param array<string> $failedThemes
     * @param bool $dryRun
     * @return void
     */
    private function displaySummary(array $themeCodes, int $totalCleaned, array $failedThemes, bool $dryRun): void
    {
        $this->io->newLine();
        $totalThemes = count($themeCodes);

        if ($totalThemes === 1) {
            $this->displaySingleThemeSummary($themeCodes[0], $totalCleaned, $dryRun);
        } else {
            $this->displayMultiThemeSummary($totalThemes, $totalCleaned, $failedThemes, $dryRun);
        }
    }

    /**
     * Display summary for single theme
     *
     * @param string $themeCode
     * @param int $totalCleaned
     * @param bool $dryRun
     * @return void
     */
    private function displaySingleThemeSummary(string $themeCode, int $totalCleaned, bool $dryRun): void
    {
        if ($totalCleaned > 0) {
            $action = $dryRun ? 'Would clean' : 'Successfully cleaned';
            $this->io->success(sprintf(
                "%s %d director%s for theme '%s'",
                $action,
                $totalCleaned,
                $totalCleaned === 1 ? 'y' : 'ies',
                $themeCode
            ));
        } else {
            $this->io->info(sprintf("No files to clean for theme '%s'", $themeCode));
        }
    }

    /**
     * Display summary for multiple themes
     *
     * @param int $totalThemes
     * @param int $totalCleaned
     * @param array<string> $failedThemes
     * @param bool $dryRun
     * @return void
     */
    private function displayMultiThemeSummary(int $totalThemes, int $totalCleaned, array $failedThemes, bool $dryRun): void
    {
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
     *
     * @return array<string, string|null>
     */
    private function getCachedEnvironmentVariables(): array
    {
        if (self::$cachedEnv === null) {
            self::$cachedEnv = [];
            $allowedVars = ['COLUMNS', 'LINES', 'TERM', 'CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'TEAMCITY_VERSION'];

            foreach ($allowedVars as $var) {
                if (isset($this->secureEnvStorage[$var])) {
                    self::$cachedEnv[$var] = $this->secureEnvStorage[$var];
                } else {
                    $globalEnv = filter_input_array(INPUT_ENV) ?: [];
                    if (array_key_exists($var, $globalEnv)) {
                        self::$cachedEnv[$var] = (string) $globalEnv[$var];
                    }
                }
            }
        }

        return self::$cachedEnv;
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
        if (empty($name) ) {
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
        self::$cachedEnv = null;
    }
}
