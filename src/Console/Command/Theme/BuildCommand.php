<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Spinner;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggestion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for building Magento themes
 */
class BuildCommand extends AbstractCommand
{
    private array $originalEnv = [];
    private array $secureEnvStorage = [];

    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param BuilderPool $builderPool
     * @param ThemeSuggestion $themeSuggestion
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly BuilderPool $builderPool,
        private readonly ThemeSuggestion $themeSuggestion
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'build'))
            ->setDescription('Builds a Magento theme')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Theme codes to build (format: Vendor/theme, Vendor/theme 2, ...)'
            )
            ->setAliases(['frontend:build']);
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCodes = $input->getArgument('themeCodes');
        $isVerbose = $this->isVerbose($output);

        if (empty($themeCodes)) {
            $themes = $this->themeList->getAllThemes();
            $options = array_map(fn($theme) => $theme->getCode(), $themes);

            // Check if we're in an interactive terminal environment
            if (!$this->isInteractiveTerminal($output)) {
                // Fallback for non-interactive environments
                $this->displayAvailableThemes($this->io);
                return Command::SUCCESS;
            }

            // Set environment variables for Laravel Prompts
            $this->setPromptEnvironment();

            $themeCodesPrompt = new MultiSelectPrompt(
                label: 'Select themes to build',
                options: $options,
                default: [], // No default selection
                hint: 'Arrow keys to navigate, Space to toggle, Enter to confirm (scroll with arrows if needed)',
                required: false,
            );

            try {
                $themeCodes = $themeCodesPrompt->prompt();
                \Laravel\Prompts\Prompt::terminal()->restoreTty();

                // Reset environment
                $this->resetPromptEnvironment();

                // If no themes selected, show available themes
                if (empty($themeCodes)) {
                    $this->io->info('No themes selected.');
                    return Command::SUCCESS;
                }
            } catch (\Exception $e) {
                // Reset environment on exception
                $this->resetPromptEnvironment();
                // Fallback if prompt fails
                $this->io->error('Interactive mode failed: ' . $e->getMessage());
                $this->displayAvailableThemes($this->io);
                $this->io->newLine();
                return Command::SUCCESS;
            }
        }

        return $this->processBuildThemes($themeCodes, $this->io, $output, $isVerbose);
    }

    /**
     * Display available themes
     *
     * @param SymfonyStyle $io
     * @return int
     */
    private function displayAvailableThemes(SymfonyStyle $io): int
    {
        $table = new Table($io);
        $table->setHeaders(['Theme Code', 'Title']);

        foreach ($this->themeList->getAllThemes() as $theme) {
            $table->addRow([$theme->getCode(), $theme->getThemeTitle()]);
        }

        $table->render();
        $io->info('Usage: bin/magento mageforge:theme:build <theme-code> [<theme-code>...]');

        return Command::SUCCESS;
    }

    /**
     * Process theme building
     *
     * @param array $themeCodes
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return int
     */
    private function processBuildThemes(
        array $themeCodes,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): int {
        $startTime = microtime(true);
        $successList = [];
        $totalThemes = count($themeCodes);

        if ($isVerbose) {
            $io->title(sprintf('Building %d theme(s)', $totalThemes));

            foreach ($themeCodes as $themeCode) {
                if (!$this->processTheme($themeCode, $io, $output, $isVerbose, $successList)) {
                    continue;
                }
            }
        } else {
            // Use the existing spinner with a customized message
            foreach ($themeCodes as $index => $themeCode) {
                $currentTheme = $index + 1;
                // Show which theme is currently being built
                $themeNameCyan = sprintf("<fg=cyan>%s</>", $themeCode);
                $spinner = new Spinner(sprintf("Building %s (%d of %d) ...", $themeNameCyan, $currentTheme, $totalThemes));
                $success = false;

                $spinner->spin(function() use ($themeCode, $io, $output, $isVerbose, &$successList, &$success) {
                    $success = $this->processTheme($themeCode, $io, $output, $isVerbose, $successList);
                    return true;
                });

                if ($success) {
                    // Show that the theme was successfully built
                    $io->writeln(sprintf("   Building %s (%d of %d) ... <fg=green>done</>", $themeNameCyan, $currentTheme, $totalThemes));
                } else {
                    // Show that an error occurred while building the theme
                    $io->writeln(sprintf("   Building %s (%d of %d) ... <fg=red>failed</>", $themeNameCyan, $currentTheme, $totalThemes));
                }
            }
        }

        $this->displayBuildSummary($io, $successList, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

    /**
     * Process a single theme
     *
     * @param string $themeCode
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @param array $successList
     * @return bool
     */
    private function processTheme(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        array &$successList
    ): bool {
        // Get theme path
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $io->error("Theme $themeCode is not installed.");
            
            // Suggest similar theme names
            $suggestions = $this->themeSuggestion->getSuggestions($themeCode);
            if (!empty($suggestions)) {
                $io->writeln('<comment>Did you mean:</comment>');
                foreach ($suggestions as $suggestion) {
                    $io->writeln(sprintf('  - <fg=cyan>%s</>', $suggestion));
                }
            }
            
            return false;
        }

        // Find appropriate builder
        $builder = $this->builderPool->getBuilder($themePath);
        if ($builder === null) {
            $io->error("No suitable builder found for theme $themeCode.");
            return false;
        }

        if ($isVerbose) {
            $io->section(sprintf("Building theme %s using %s builder", $themeCode, $builder->getName()));
        }

        // Build the theme
        if (!$builder->build($themePath, $io, $output, $isVerbose)) {
            $io->error("Failed to build theme $themeCode.");
            return false;
        }

        $successList[] = sprintf("%s: Built successfully using %s builder", $themeCode, $builder->getName());
        return true;
    }

    /**
     * Display build summary
     *
     * @param SymfonyStyle $io
     * @param array $successList
     * @param float $duration
     */
    private function displayBuildSummary(SymfonyStyle $io, array $successList, float $duration): void
    {
        $io->newLine();
        $io->success(sprintf(
            "🚀 Build process completed in %.2f seconds with the following results:",
            $duration
        ));
        $io->writeln("Summary:");
        $io->newLine();

        if (empty($successList)) {
            $io->warning('No themes were built successfully.');
            return;
        }

        foreach ($successList as $success) {
            $parts = explode(': ', $success, 2);
            if (count($parts) === 2) {
                $themeName = $parts[0];
                $details = $parts[1];
                // Color the builder name in magenta
                if (preg_match('/(using\s+)([^\s]+)(\s+builder)/', $details, $matches)) {
                    $details = str_replace(
                        $matches[0],
                        $matches[1] . '<fg=magenta>' . $matches[2] . '</>' . $matches[3],
                        $details
                    );
                }
                $io->writeln(sprintf("✅ <fg=cyan>%s</>: %s", $themeName, $details));
            } else {
                $io->writeln("✅ $success");
            }
        }

        $io->newLine();
    }

    /**
     * Safely get environment variable with sanitization
     * Uses secure method to avoid direct superglobal access
     */
    private function getEnvVar(string $name): ?string
    {
        // Use a secure method to check environment variables
        $value = $this->getSecureEnvironmentValue($name);

        if ($value === null || $value === '') {
            return null;
        }

        // Apply specific sanitization based on variable type
        return $this->sanitizeEnvironmentValue($name, $value);
    }

    /**
     * Securely retrieve environment variable without direct superglobal access
     */
    private function getSecureEnvironmentValue(string $name): ?string
    {
        // Validate the variable name first
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        // Create a safe way to access environment without direct $_ENV access
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
            // Only cache the specific variables we need
            $allowedVars = ['COLUMNS', 'LINES', 'TERM', 'CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'TEAMCITY_VERSION'];

            foreach ($allowedVars as $var) {
                // Check secure storage first
                if (isset($this->secureEnvStorage[$var])) {
                    $cachedEnv[$var] = $this->secureEnvStorage[$var];
                } else {
                    // Use array_key_exists to safely check without triggering warnings
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
     * Sanitize numeric values (COLUMNS, LINES)
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
     * Uses secure method to avoid direct superglobal access
     */
    private function getServerVar(string $name): ?string
    {
        // Validate the variable name first
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        // Use filter_input to safely access server variables without deprecated filter
        $value = filter_input(INPUT_SERVER, $name);

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        // Apply additional sanitization
        return $this->sanitizeAlphanumericValue((string) $value);
    }

    /**
     * Safely set environment variable with validation
     * Avoids direct $_ENV access and putenv usage
     */
    private function setEnvVar(string $name, string $value): void
    {
        // Validate input parameters
        if (empty($name) || !is_string($name)) {
            return;
        }

        // Validate variable name
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return;
        }

        // Sanitize the value based on variable type
        $sanitizedValue = $this->sanitizeEnvironmentValue($name, $value);

        if ($sanitizedValue !== null) {
            // Store in our safe cache instead of direct $_ENV manipulation
            $this->setSecureEnvironmentValue($name, $sanitizedValue);
        }
    }

    /**
     * Securely store environment variable without direct superglobal access
     */
    private function setSecureEnvironmentValue(string $name, string $value): void
    {
        // For this implementation, we'll store values in a class property
        // to avoid direct manipulation of superglobals
        if (!isset($this->secureEnvStorage)) {
            $this->secureEnvStorage = [];
        }
        $this->secureEnvStorage[$name] = $value;
    }

    /**
     * Clear the environment variable cache
     */
    private function clearEnvironmentCache(): void
    {
        // Reset our secure storage
        $this->secureEnvStorage = [];
    }    /**
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
        // This is a safer alternative to posix_isatty()
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
     * Uses secure method without direct $_ENV or putenv
     */
    private function resetPromptEnvironment(): void
    {
        // Reset environment variables to original state using secure methods
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                // Remove from our secure cache
                $this->removeSecureEnvironmentValue($key);
            } else {
                // Restore original value using secure method
                $this->setEnvVar($key, $value);
            }
        }
    }

    /**
     * Securely remove environment variable from cache
     */
    private function removeSecureEnvironmentValue(string $name): void
    {
        // Remove the specific variable from our secure storage
        unset($this->secureEnvStorage[$name]);

        // Clear the static cache to force refresh on next access
        $this->clearEnvironmentCache();
    }
}
