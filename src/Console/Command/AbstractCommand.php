<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Laravel\Prompts\SelectPrompt;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Abstract base class for MageForge commands
 *
 * Provides common functionality and standardized structure for all commands
 */
abstract class AbstractCommand extends Command
{
    /**
     * Default command group prefix
     */
    protected const COMMAND_PREFIX = 'mageforge';

    /**
     * @var SymfonyStyle
     */
    protected SymfonyStyle $io;

    /**
     * @var array<string, string|null>
     */
    private array $originalEnv = [];

    /**
     * @var array<string, string|null>
     */
    private array $secureEnvStorage = [];

    /**
     * Get the command name with proper group structure
     *
     * @param string $group The command group (e.g. 'theme', 'system')
     * @param string $command The specific command (e.g. 'build', 'watch')
     * @return string The properly formatted command name
     */
    protected function getCommandName(string $group, string $command): string
    {
        return sprintf('%s:%s:%s', static::COMMAND_PREFIX, $group, $command);
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        try {
            return $this->executeCommand($input, $output);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Execute the command logic
     *
     * Each child class must implement this with their specific command logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

    /**
     * Get if the output is in verbose mode
     *
     * @param OutputInterface $output
     * @return bool
     */
    protected function isVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Get if the output is in very verbose mode
     *
     * @param OutputInterface $output
     * @return bool
     */
    protected function isVeryVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * Get if the output is in debug mode
     *
     * @param OutputInterface $output
     * @return bool
     */
    protected function isDebug(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
    }

    /**
     * Handle invalid theme with interactive suggestions
     *
     * When a theme code is invalid, this method finds similar themes using Levenshtein distance
     * and offers an interactive selection via Laravel Prompts (if terminal is interactive).
     * In non-interactive environments, suggestions are displayed as text.
     *
     * @param string $invalidTheme The invalid theme code entered by user
     * @param ThemeSuggester $themeSuggester Service to find similar themes
     * @param OutputInterface $output Output interface for terminal detection
     * @return string|null The selected theme code, or null if cancelled/no selection
     */
    protected function handleInvalidThemeWithSuggestions(
        string $invalidTheme,
        ThemeSuggester $themeSuggester,
        OutputInterface $output
    ): ?string {
        $suggestions = $themeSuggester->findSimilarThemes($invalidTheme);

        // No suggestions found
        if (empty($suggestions)) {
            $this->io->error("Theme '$invalidTheme' is not installed and no similar themes were found.");
            return null;
        }

        // Check if terminal is interactive
        if (!$this->isInteractiveTerminal($output)) {
            // Non-interactive fallback: display suggestions as text
            $this->io->error("Theme '$invalidTheme' is not installed.");
            $this->io->writeln("\nDid you mean one of these?");
            foreach ($suggestions as $suggestion) {
                $this->io->writeln("  - $suggestion");
            }
            return null;
        }

        // Interactive mode: show prompt with suggestions
        $this->io->error("Theme '$invalidTheme' is not installed.");
        $this->io->newLine();

        // Prepare options with "None of these" option
        $options = array_merge($suggestions, ['None of these']);

        // Set environment for Docker/DDEV compatibility
        $this->setPromptEnvironment();

        $prompt = new SelectPrompt(
            label: 'Did you mean one of these themes?',
            options: $options,
            scroll: 10,
            hint: 'Arrow keys to navigate, Enter to confirm'
        );

        try {
            $selection = $prompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            $this->resetPromptEnvironment();

            // Check if user selected "None of these"
            if ($selection === 'None of these') {
                return null;
            }

            return $selection;
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Selection failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if terminal is interactive (supports Laravel Prompts)
     *
     * @param OutputInterface $output
     * @return bool
     */
    private function isInteractiveTerminal(OutputInterface $output): bool
    {
        // Check if output supports ANSI
        if (!$output->isDecorated()) {
            return false;
        }

        // Check if STDIN is available
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return false;
        }

        // Check for CI environments
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

        // Check if TTY is available
        // phpcs:ignore Magento2.Security.InsecureFunction.Found -- shell_exec required for TTY detection
        $sttyOutput = shell_exec('stty -g 2>/dev/null');
        return !empty($sttyOutput);
    }

    /**
     * Set environment variables for Laravel Prompts in Docker/DDEV
     *
     * @return void
     */
    private function setPromptEnvironment(): void
    {
        // Store original values for restoration
        $this->originalEnv = [
            'COLUMNS' => $this->getEnvVar('COLUMNS'),
            'LINES' => $this->getEnvVar('LINES'),
            'TERM' => $this->getEnvVar('TERM'),
        ];

        // Set terminal dimensions for proper rendering
        $this->setEnvVar('COLUMNS', '100');
        $this->setEnvVar('LINES', '40');
        $this->setEnvVar('TERM', 'xterm-256color');
    }

    /**
     * Reset environment variables to original state
     *
     * @return void
     */
    private function resetPromptEnvironment(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                $this->removeSecureEnvironmentValue($key);
            } else {
                $this->setEnvVar($key, $value);
            }
        }
    }

    /**
     * Get environment variable value
     *
     * @param string $key
     * @return string|null
     */
    private function getEnvVar(string $key): ?string
    {
        return getenv($key) ?: null;
    }

    /**
     * Get server variable value
     *
     * @param string $key
     * @return string|null
     */
    private function getServerVar(string $key): ?string
    {
        return $_SERVER[$key] ?? null;
    }

    /**
     * Set environment variable securely
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    private function setEnvVar(string $key, string $value): void
    {
        $this->secureEnvStorage[$key] = $value;
        putenv("$key=$value");
    }

    /**
     * Remove environment variable securely
     *
     * @param string $key
     * @return void
     */
    private function removeSecureEnvironmentValue(string $key): void
    {
        unset($this->secureEnvStorage[$key]);
        putenv($key);
    }
}
