<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Dependencies;

use Laravel\Prompts\MultiSearchPrompt;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\DependencyUpdater;
use OpenForgeProject\MageForge\Service\DependencyUpdateResult;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for updating the Node.js dependencies of themes
 */
class UpdateCommand extends AbstractCommand
{
    /**
     * @param DependencyUpdater $dependencyUpdater
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param ThemeSuggester $themeSuggester
     */
    public function __construct(
        private readonly DependencyUpdater $dependencyUpdater,
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly ThemeSuggester $themeSuggester,
    ) {
        parent::__construct();
    }

    /**
     * Configure command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName($this->getCommandName('dependencies', 'update'))
            ->setDescription('Update the Node.js dependencies of one or more themes')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Theme codes to update (format: Vendor/theme, Vendor, ...)',
            )
            ->addOption(
                'latest',
                'l',
                InputOption::VALUE_NONE,
                'Update packages beyond their semver ranges to the latest versions (updates package.json)',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show outdated packages without changing anything')
            ->setAliases(['dependencies:update']);
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $latest = (bool) $input->getOption('latest');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $this->io->note('DRY RUN MODE: No dependencies will be changed');
        }

        $themeCodes = $this->resolveThemeCodes($input, $output);

        if ($themeCodes === null) {
            return Cli::RETURN_SUCCESS;
        }

        $startTime = microtime(true);

        [$updatedThemes, $skippedThemes, $failedThemes] = $this->processThemes($themeCodes, $latest, $dryRun, $output);

        $this->displaySummary($updatedThemes, $skippedThemes, $failedThemes, $dryRun, microtime(true) - $startTime);

        if (empty($updatedThemes) && !empty($failedThemes)) {
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Resolve which themes to update based on input
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array<string>|null Array of theme codes or null to exit
     */
    private function resolveThemeCodes(InputInterface $input, OutputInterface $output): ?array
    {
        /** @var array<string> $themeCodes */
        $themeCodes = $input->getArgument('themeCodes');

        if (!empty($themeCodes)) {
            $themeCodes = $this->resolveVendorThemes($themeCodes, $this->themeList);

            // If wildcards matched nothing and no other explicit themes remain
            if (empty($themeCodes)) {
                return null;
            }

            return $themeCodes;
        }

        return $this->selectThemesInteractively($output);
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
        $options = array_values(array_map(static fn($theme) => $theme->getCode(), $themes));

        if (!$this->isInteractiveTerminal($output)) {
            $this->displayAvailableThemes($themes);
            return null;
        }

        return $this->promptForThemes($options, $themes);
    }

    /**
     * Display available themes for non-interactive environments
     *
     * @param \Magento\Theme\Model\Theme[] $themes
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
        $this->io->info('Usage: bin/magento mageforge:dependencies:update <theme-code> [<theme-code>...]');
        $this->io->info('Example: bin/magento mageforge:dependencies:update Vendor/theme');
    }

    /**
     * Prompt user to select themes
     *
     * @param string[] $options
     * @param \Magento\Theme\Model\Theme[] $themes
     * @return string[]|null
     */
    private function promptForThemes(array $options, array $themes): ?array
    {
        $this->setPromptEnvironment();

        $themeCodesPrompt = new MultiSearchPrompt(
            label: 'Select themes to update dependencies for',
            options: static fn(string $value) => empty($value)
                ? $options
                : array_values(array_filter($options, static fn($option) => stripos($option, $value) !== false)),
            placeholder: 'Type to search theme...',
            hint: 'Type to search, arrow keys to navigate, Space to toggle, Enter to confirm',
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

            /** @var array<string> $themeCodes */
            return $themeCodes;
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            $this->displayAvailableThemes($themes);
            return null;
        }
    }

    /**
     * Process the dependency update for all selected themes
     *
     * @param array<string> $themeCodes
     * @param bool $latest
     * @param bool $dryRun
     * @param OutputInterface $output
     * @return array{array<string>, array<string>, array<string>} [updatedThemes, skippedThemes, failedThemes]
     */
    private function processThemes(array $themeCodes, bool $latest, bool $dryRun, OutputInterface $output): array
    {
        $isVerbose = $this->isVerbose($output);
        $totalThemes = count($themeCodes);
        $updatedThemes = [];
        $skippedThemes = [];
        $failedThemes = [];

        foreach ($themeCodes as $index => $themeCode) {
            $currentTheme = (int) $index + 1;

            $validatedTheme = $this->validateTheme($themeCode, $failedThemes, $output);

            if ($validatedTheme === null) {
                continue;
            }

            if ($totalThemes > 1) {
                $this->io->section(sprintf(
                    'Updating dependencies %d of %d: %s',
                    $currentTheme,
                    $totalThemes,
                    $validatedTheme,
                ));
            } else {
                $this->io->section(sprintf('Updating dependencies for theme: %s', $validatedTheme));
            }

            $themePath = (string) $this->themePath->getPath($validatedTheme);

            $result = $this->dependencyUpdater->updateThemeDependencies(
                $validatedTheme,
                $themePath,
                $this->io,
                $isVerbose,
                $latest,
                $dryRun,
            );

            match ($result) {
                DependencyUpdateResult::Updated => $updatedThemes[] = $validatedTheme,
                DependencyUpdateResult::Skipped => $skippedThemes[] = $validatedTheme,
                DependencyUpdateResult::Failed => $failedThemes[] = $validatedTheme,
            };
        }

        return [$updatedThemes, $skippedThemes, $failedThemes];
    }

    /**
     * Validate theme exists, offering suggestions for invalid codes
     *
     * @param string $themeCode
     * @param array<string> $failedThemes
     * @param OutputInterface $output
     * @return string|null Theme code if valid or corrected, null if invalid
     */
    private function validateTheme(string $themeCode, array &$failedThemes, OutputInterface $output): ?string
    {
        $themePath = $this->themePath->getPath($themeCode);

        if ($themePath === null) {
            // Try to suggest similar themes
            $correctedTheme = $this->handleInvalidThemeWithSuggestions($themeCode, $this->themeSuggester, $output);

            // If no theme was selected, mark as failed
            if ($correctedTheme === null) {
                $failedThemes[] = $themeCode;
                return null;
            }

            // Double-check the corrected theme exists
            if ($this->themePath->getPath($correctedTheme) === null) {
                $this->io->error(sprintf("Theme '%s' not found.", $correctedTheme));
                $failedThemes[] = $themeCode;
                return null;
            }

            $this->io->info("Using theme: $correctedTheme");
            return $correctedTheme;
        }

        return $themeCode;
    }

    /**
     * Display summary of the update operation
     *
     * @param array<string> $updatedThemes
     * @param array<string> $skippedThemes
     * @param array<string> $failedThemes
     * @param bool $dryRun
     * @param float $duration
     * @return void
     */
    private function displaySummary(
        array $updatedThemes,
        array $skippedThemes,
        array $failedThemes,
        bool $dryRun,
        float $duration,
    ): void {
        $this->io->newLine();

        if (!empty($updatedThemes)) {
            $action = $dryRun ? 'Checked' : 'Updated';
            $this->io->success(sprintf(
                '%s dependencies for %d theme(s) in %.2f seconds: %s',
                $action,
                count($updatedThemes),
                $duration,
                implode(', ', $updatedThemes),
            ));

            if (!$dryRun) {
                $this->io->note(sprintf('Rebuild the updated theme(s) to apply the changes: '
                . 'bin/magento mageforge:theme:build %s', implode(' ', $updatedThemes)));
            }
        } else {
            $this->io->info('No theme dependencies were updated.');
        }

        if (!empty($skippedThemes)) {
            $this->io->note(sprintf('Skipped %d theme(s): %s', count($skippedThemes), implode(', ', $skippedThemes)));
        }

        if (!empty($failedThemes)) {
            $this->io->warning(sprintf(
                'Failed to process %d theme(s): %s',
                count($failedThemes),
                implode(', ', $failedThemes),
            ));
        }
    }
}
