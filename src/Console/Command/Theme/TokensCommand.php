<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\SelectPrompt;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for generating Hyvä design tokens
 */
class TokensCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param BuilderPool $builderPool
     * @param File $fileDriver
     * @param Shell $shell
     * @param ThemeSuggester $themeSuggester
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly BuilderPool $builderPool,
        private readonly File $fileDriver,
        private readonly Shell $shell,
        private readonly ThemeSuggester $themeSuggester,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('hyva', 'tokens'))
            ->setAliases(['hyva:tokens'])
            ->setDescription('Generate Hyvä design tokens from design.tokens.json or hyva.config.json')
            ->addArgument(
                'themeCode',
                InputArgument::OPTIONAL,
                'Theme code to generate tokens for (format: Vendor/theme)'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCode = $this->selectTheme($input->getArgument('themeCode'));
        if ($themeCode === null) {
            return Cli::RETURN_FAILURE;
        }

        $themePath = $this->validateHyvaTheme($themeCode, $output);
        if ($themePath === null) {
            return Cli::RETURN_FAILURE;
        }

        $tailwindPath = $this->validateTailwindDirectory($themePath, $themeCode);
        if ($tailwindPath === null) {
            return Cli::RETURN_FAILURE;
        }

        $isVerbose = $this->isVerbose($output);
        if (!$this->generateTokens($tailwindPath, $themeCode, $isVerbose)) {
            return Cli::RETURN_FAILURE;
        }

        $this->handleOutputFile($tailwindPath, $themePath, $themeCode);

        return Cli::RETURN_SUCCESS;
    }

    private function selectTheme(?string $themeCode): ?string
    {
        if (!empty($themeCode)) {
            return $themeCode;
        }

        $themes = $this->themeList->getAllThemes();
        $options = array_map(fn($theme) => $theme->getCode(), $themes);

        $themeCodePrompt = new SelectPrompt(
            label: 'Select theme to generate tokens for',
            options: $options,
            scroll: 10,
            hint: 'Arrow keys to navigate, Enter to confirm',
        );

        try {
            $themeCode = $themeCodePrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            return $themeCode;
        } catch (\Exception $e) {
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            return null;
        }
    }

    private function validateHyvaTheme(string $themeCode, OutputInterface $output): ?string
    {
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            // Try to suggest similar themes
            $correctedTheme = $this->handleInvalidThemeWithSuggestions(
                $themeCode,
                $this->themeSuggester,
                $output
            );

            // If no theme was selected, exit
            if ($correctedTheme === null) {
                return null;
            }

            // Use the corrected theme code
            $themeCode = $correctedTheme;
            $themePath = $this->themePath->getPath($themeCode);

            // Double-check the corrected theme exists
            if ($themePath === null) {
                $this->io->error("Theme $themeCode is not installed.");
                return null;
            }

            $this->io->info("Using theme: $themeCode");
        }

        $builder = $this->builderPool->getBuilder($themePath);
        if ($builder === null || $builder->getName() !== 'HyvaThemes') {
            $this->io->error("Theme $themeCode is not a Hyvä theme. This command only works with Hyvä themes.");
            return null;
        }

        return $themePath;
    }

    private function validateTailwindDirectory(string $themePath, string $themeCode): ?string
    {
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';

        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $this->io->error("Tailwind directory not found in: $tailwindPath");
            return null;
        }

        if (!$this->fileDriver->isDirectory($tailwindPath . '/node_modules')) {
            $this->io->warning('Node modules not found. Please run: bin/magento mageforge:theme:build ' . $themeCode);
            return null;
        }

        return $tailwindPath;
    }

    private function generateTokens(string $tailwindPath, string $themeCode, bool $isVerbose): bool
    {
        if ($isVerbose) {
            $this->io->section("Generating Hyvä design tokens for theme: $themeCode");
            $this->io->text("Working directory: $tailwindPath");
        }

        $currentDir = getcwd();
        chdir($tailwindPath);

        try {
            if ($isVerbose) {
                $this->io->text('Running npx hyva-tokens...');
            }

            $this->shell->execute('npx hyva-tokens');
            chdir($currentDir);

            return true;
        } catch (\Exception $e) {
            chdir($currentDir);
            $this->io->error('Failed to generate Hyvä design tokens: ' . $e->getMessage());
            return false;
        }
    }

    private function handleOutputFile(string $tailwindPath, string $themePath, string $themeCode): void
    {
        $isVendorTheme = str_contains($themePath, '/vendor/');
        $sourceFilePath = $tailwindPath . '/generated/hyva-tokens.css';

        if ($isVendorTheme) {
            $generatedFilePath = $this->copyToVarGenerated($sourceFilePath, $themeCode);
            $this->io->success('Hyvä design tokens generated successfully.');
            $this->io->note('This is a vendor theme. Tokens have been saved to var/generated/hyva-token/ instead.');
            $this->io->text('Generated file: ' . $generatedFilePath);
        } else {
            $this->io->success('Hyvä design tokens generated successfully.');
            $this->io->text('Generated file: ' . $sourceFilePath);
        }

        $this->io->newLine();
    }

    private function copyToVarGenerated(string $sourceFilePath, string $themeCode): string
    {
        $currentDir = getcwd();
        $varGeneratedPath = $currentDir . '/var/generated/hyva-token/' . str_replace('/', '/', $themeCode);

        if (!$this->fileDriver->isDirectory($varGeneratedPath)) {
            $this->fileDriver->createDirectory($varGeneratedPath, 0755);
        }

        $generatedFilePath = $varGeneratedPath . '/hyva-tokens.css';

        if ($this->fileDriver->isExists($sourceFilePath)) {
            $this->fileDriver->copy($sourceFilePath, $generatedFilePath);
        }

        return $generatedFilePath;
    }
}
