<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Laravel\Prompts\SelectPrompt;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\HyvaThemes\Builder as HyvaBuilder;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service for theme selection and validation
 */
class ThemeSelectionService
{
    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param HyvaBuilder $hyvaBuilder
     * @param EnvironmentService $environmentService
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly HyvaBuilder $hyvaBuilder,
        private readonly EnvironmentService $environmentService,
    ) {
    }

    /**
     * Get Hyva themes interactively or display available themes
     *
     * @param SymfonyStyle $io
     * @return string|null
     */
    public function selectHyvaTheme(SymfonyStyle $io): ?string
    {
        $hyvaThemes = $this->getHyvaThemes();

        if (empty($hyvaThemes)) {
            $io->error('No Hyvä themes found in this installation.');
            return null;
        }

        // Check if we're in an interactive terminal environment
        if (!$this->environmentService->isInteractiveTerminal()) {
            $this->displayAvailableThemes($io, $hyvaThemes);
            return null;
        }

        return $this->promptForTheme($io, $hyvaThemes);
    }

    /**
     * Validate theme exists and optionally check if it's a Hyva theme
     *
     * @param string $themeCode
     * @param bool $requireHyva
     * @return string|null
     */
    public function validateTheme(string $themeCode, bool $requireHyva = false): ?string
    {
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            return null;
        }

        if ($requireHyva && !$this->hyvaBuilder->detect($themePath)) {
            return null;
        }

        return $themePath;
    }

    /**
     * Get list of Hyva themes
     *
     * @return array
     */
    private function getHyvaThemes(): array
    {
        $allThemes = $this->themeList->getAllThemes();
        $hyvaThemes = [];

        foreach ($allThemes as $theme) {
            $themePath = $this->themePath->getPath($theme->getCode());
            if ($themePath && $this->hyvaBuilder->detect($themePath)) {
                $hyvaThemes[] = $theme;
            }
        }

        return $hyvaThemes;
    }

    /**
     * Display available themes for non-interactive environments
     *
     * @param SymfonyStyle $io
     * @param array $hyvaThemes
     * @return void
     */
    private function displayAvailableThemes(SymfonyStyle $io, array $hyvaThemes): void
    {
        $io->info('Available Hyvä themes:');
        foreach ($hyvaThemes as $theme) {
            $io->writeln(' - ' . $theme->getCode());
        }
        $io->newLine();
        $io->info('Usage: bin/magento mageforge:hyva:tokens <theme-code>');
    }

    /**
     * Prompt user to select a theme
     *
     * @param SymfonyStyle $io
     * @param array $hyvaThemes
     * @return string|null
     */
    private function promptForTheme(SymfonyStyle $io, array $hyvaThemes): ?string
    {
        $options = [];
        foreach ($hyvaThemes as $theme) {
            $options[] = $theme->getCode();
        }

        // Set environment variables for Laravel Prompts
        $this->environmentService->setPromptEnvironment();

        $themeCodePrompt = new SelectPrompt(
            label: 'Select Hyvä theme to generate tokens for',
            options: $options,
            hint: 'Arrow keys to navigate, Enter to confirm'
        );

        try {
            $selectedTheme = $themeCodePrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();

            // Reset environment
            $this->environmentService->resetPromptEnvironment();

            return $selectedTheme;
        } catch (\Exception $e) {
            // Reset environment on exception
            $this->environmentService->resetPromptEnvironment();
            $io->error('Interactive mode failed: ' . $e->getMessage());
            return null;
        }
    }
}
