<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Template;

use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\TextPrompt;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\TemplateReference;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\TemplateOverride\AreaEmulator;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateCopier;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateFallbackResolver;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplatePathParser;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command that copies a module template into a theme as an override
 *
 * The override location is derived from Magento's own view file fallback rule, so plugins
 * like Hyvä's compat module fallback are honored and the copied file is guaranteed to be
 * picked up by Magento's template resolution.
 */
class OverrideCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     * @param ThemeSuggester $themeSuggester
     * @param TemplatePathParser $templatePathParser
     * @param TemplateFallbackResolver $fallbackResolver
     * @param TemplateCopier $templateCopier
     * @param AreaEmulator $areaEmulator
     * @param CacheCleaner $cacheCleaner
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ThemeSuggester $themeSuggester,
        private readonly TemplatePathParser $templatePathParser,
        private readonly TemplateFallbackResolver $fallbackResolver,
        private readonly TemplateCopier $templateCopier,
        private readonly AreaEmulator $areaEmulator,
        private readonly CacheCleaner $cacheCleaner,
        private readonly DirectoryList $directoryList,
    ) {
        parent::__construct();
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName($this->getCommandName('template', 'override'))
            ->setDescription('Copies a module template into a theme, following Magento\'s fallback logic')
            ->addArgument(
                'template',
                InputArgument::OPTIONAL,
                'Template to override (Module_Name::path/to/template.phtml or a file path)',
            )
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'Target theme code (format: Vendor/theme)')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only show source, target and fallback order without copying',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Replace an existing override with the next file in the fallback chain',
            )
            ->setAliases(['template:override']);
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = (bool) $input->getOption('dry-run');
        $isForce = (bool) $input->getOption('force');

        $frontendThemes = $this->getFrontendThemes();
        if ($frontendThemes === []) {
            $this->io->warning('No frontend themes found.');
            return Cli::RETURN_SUCCESS;
        }

        $templateInput = $this->resolveTemplateInput($input, $output);
        if ($templateInput === null) {
            $this->displayUsage();
            return Cli::RETURN_SUCCESS;
        }

        /** @var string|null $themeOption */
        $themeOption = $input->getOption('theme');
        $theme = $this->resolveTheme($themeOption, $frontendThemes, $output);
        if ($theme === null) {
            return $themeOption === null || $themeOption === '' ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
        }

        // Load the theme area's DI configuration so fallback plugins (e.g. Hyvä compat) apply
        $this->areaEmulator->emulate($theme->getArea());

        $reference = $this->templatePathParser->parse($templateInput);
        $templatePath = $reference->getTemplatePath();

        $fallbackDirs = $this->fallbackResolver->getFallbackDirs($reference, $theme);
        if ($fallbackDirs === []) {
            $this->io->error(sprintf('No fallback directories found for %s.', $reference->getTemplateId()));
            return Cli::RETURN_FAILURE;
        }

        $targetDir = $this->fallbackResolver->getThemeTargetDir($fallbackDirs, $theme);
        if ($targetDir === null) {
            $this->io->error(sprintf(
                "Could not determine the override directory inside theme '%s'.",
                $theme->getCode(),
            ));
            return Cli::RETURN_FAILURE;
        }
        $targetFile = $targetDir . '/' . $templatePath;

        $sourceFile = $this->fallbackResolver->findFirstExistingFile($fallbackDirs, $templatePath);
        if ($sourceFile === null) {
            $this->io->error(sprintf(
                'Template %s was not found in any fallback location.',
                $reference->getTemplateId(),
            ));
            $this->displayFallbackDirs($fallbackDirs, null, $targetDir, $templatePath);
            return Cli::RETURN_FAILURE;
        }

        $overrideExists = $sourceFile === $targetFile;
        if ($overrideExists && !$isForce) {
            $this->io->info(sprintf(
                'The template is already overridden in this theme: %s',
                $this->toRelativePath($targetFile),
            ));
            $this->io->text('Use --force to replace it with the next file in the fallback chain.');
            return Cli::RETURN_SUCCESS;
        }

        if ($overrideExists) {
            $sourceFile = $this->fallbackResolver->findFirstExistingFile($fallbackDirs, $templatePath, $targetDir);
            if ($sourceFile === null) {
                $this->io->error('No other file found in the fallback chain to copy the override from.');
                return Cli::RETURN_FAILURE;
            }
        }

        $this->io->newLine();
        $this->io->definitionList(
            ['Template' => $reference->getTemplateId()],
            ['Theme' => $theme->getCode()],
            ['Source' => $this->toRelativePath($sourceFile)],
            ['Target' => $this->toRelativePath($targetFile)],
        );

        if ($isDryRun || $this->isVerbose($output)) {
            $this->displayFallbackDirs($fallbackDirs, $sourceFile, $targetDir, $templatePath);
        }

        if ($isDryRun) {
            $this->io->success('Dry run: no files were copied.');
            return Cli::RETURN_SUCCESS;
        }

        if ($overrideExists) {
            $this->io->text('Replacing the existing override (--force).');
        }

        $sourceModuleName = $this->resolveSourceModuleName($sourceFile, $fallbackDirs, $reference);
        $this->templateCopier->copy($sourceFile, $targetFile, $sourceModuleName);

        // Verify that Magento's fallback now resolves the template to the new override
        $resolved = $this->fallbackResolver->findFirstExistingFile($fallbackDirs, $templatePath);
        if ($resolved !== $targetFile) {
            $this->io->warning(sprintf(
                'The file was copied, but Magento resolves the template to %s instead of the new override.',
                $resolved === null ? 'nothing' : $this->toRelativePath($resolved),
            ));
            return Cli::RETURN_FAILURE;
        }

        if (!$this->cacheCleaner->clean($this->io, $this->isVerbose($output))) {
            $this->io->warning(sprintf(
                'Template override created at %s, but cleaning the caches failed. '
                . "Run 'bin/magento cache:clean full_page block_html layout translate' manually.",
                $this->toRelativePath($targetFile),
            ));
            return Cli::RETURN_FAILURE;
        }

        $this->io->success(sprintf('Template override created: %s', $this->toRelativePath($targetFile)));
        return Cli::RETURN_SUCCESS;
    }

    /**
     * Get the template input from the argument or an interactive prompt
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string|null
     */
    private function resolveTemplateInput(InputInterface $input, OutputInterface $output): ?string
    {
        /** @var string|null $templateArg */
        $templateArg = $input->getArgument('template');
        if ($templateArg !== null && trim($templateArg) !== '') {
            return trim($templateArg);
        }

        if (!$this->isInteractiveTerminal($output)) {
            return null;
        }

        $this->setPromptEnvironment();
        $prompt = new TextPrompt(
            label: 'Which template do you want to override?',
            placeholder: 'Magento_Catalog::product/view/details.phtml',
            required: true,
            hint: 'Module_Name::path/to/template.phtml notation or a file path',
        );

        try {
            $value = $prompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            $this->resetPromptEnvironment();
            $value = is_string($value) ? trim($value) : '';

            return $value === '' ? null : $value;
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Determine the module name the source file belongs to, falling back to the reference
     *
     * @param string $sourceFile
     * @param string[] $fallbackDirs
     * @param TemplateReference $reference
     * @return string
     */
    private function resolveSourceModuleName(
        string $sourceFile,
        array $fallbackDirs,
        TemplateReference $reference,
    ): string {
        foreach ($fallbackDirs as $dir) {
            $normalizedDir = str_replace('\\', '/', $dir);
            $dirWithTrailingSlash = rtrim($normalizedDir, '/') . '/';
            if (!str_starts_with(str_replace('\\', '/', $sourceFile), $dirWithTrailingSlash)) {
                continue;
            }

            $relativePath = substr(str_replace('\\', '/', $sourceFile), strlen($dirWithTrailingSlash));
            if (!str_contains($relativePath, '/')) {
                continue;
            }

            $firstSegment = explode('/', $relativePath)[0];
            if (str_contains($firstSegment, '_')) {
                return $firstSegment;
            }
        }

        return $reference->getModuleName();
    }

    /**
     * Resolve the target theme from the option or an interactive prompt
     *
     * @param string|null $themeOption
     * @param array<string,ThemeInterface> $frontendThemes
     * @param OutputInterface $output
     * @return ThemeInterface|null
     */
    private function resolveTheme(?string $themeOption, array $frontendThemes, OutputInterface $output): ?ThemeInterface
    {
        if ($themeOption !== null && $themeOption !== '') {
            return $this->resolveThemeFromOption($themeOption, $frontendThemes, $output);
        }

        if (!$this->isInteractiveTerminal($output)) {
            $this->displayAvailableThemes($frontendThemes);
            return null;
        }

        return $this->promptForTheme($frontendThemes);
    }

    /**
     * Resolve a theme given via the --theme option, offering suggestions when invalid
     *
     * @param string $themeCode
     * @param array<string,ThemeInterface> $frontendThemes
     * @param OutputInterface $output
     * @return ThemeInterface|null
     */
    private function resolveThemeFromOption(
        string $themeCode,
        array $frontendThemes,
        OutputInterface $output,
    ): ?ThemeInterface {
        if (isset($frontendThemes[$themeCode])) {
            return $frontendThemes[$themeCode];
        }

        $correctedTheme = $this->handleInvalidThemeWithSuggestions($themeCode, $this->themeSuggester, $output);
        if ($correctedTheme !== null && isset($frontendThemes[$correctedTheme])) {
            $this->io->info("Using theme: $correctedTheme");
            return $frontendThemes[$correctedTheme];
        }

        return null;
    }

    /**
     * Let the user pick the target theme interactively
     *
     * @param array<string,ThemeInterface> $frontendThemes
     * @return ThemeInterface|null
     */
    private function promptForTheme(array $frontendThemes): ?ThemeInterface
    {
        $options = array_keys($frontendThemes);

        $this->setPromptEnvironment();
        $prompt = new SearchPrompt(
            label: 'Select the target theme',
            options: static fn(string $value) => $value === ''
                ? $options
                : array_values(array_filter($options, static fn(string $option) => stripos($option, $value) !== false)),
            placeholder: 'Type to search theme...',
            scroll: 10,
            hint: 'Type to search, arrow keys to navigate, Enter to confirm',
        );

        try {
            $selection = $prompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            $this->resetPromptEnvironment();

            return is_string($selection) ? $frontendThemes[$selection] ?? null : null;
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all frontend themes keyed by theme code
     *
     * @return array<string,ThemeInterface>
     */
    private function getFrontendThemes(): array
    {
        $themes = [];
        foreach ($this->themeList->getAllThemes() as $theme) {
            if ($theme->getArea() !== Area::AREA_FRONTEND) {
                continue;
            }
            $themes[$theme->getCode()] = $theme;
        }

        return $themes;
    }

    /**
     * Display the fallback search order with source and target markers
     *
     * @param string[] $fallbackDirs
     * @param string|null $sourceFile
     * @param string $targetDir
     * @param string $templatePath
     * @return void
     */
    private function displayFallbackDirs(
        array $fallbackDirs,
        ?string $sourceFile,
        string $targetDir,
        string $templatePath,
    ): void {
        $this->io->text('Fallback search order (the first existing file wins):');

        $rows = [];
        $position = 0;
        foreach ($fallbackDirs as $dir) {
            $position++;
            $notes = [];
            if ($dir === $targetDir) {
                $notes[] = 'override target';
            }
            if ($sourceFile !== null && $sourceFile === $dir . '/' . $templatePath) {
                $notes[] = 'current source';
            }
            $rows[] = [$position, $this->toRelativePath($dir), implode(', ', $notes)];
        }

        $this->io->table(['#', 'Directory', ''], $rows);
    }

    /**
     * Display available frontend themes with usage instructions
     *
     * @param array<string,ThemeInterface> $frontendThemes
     * @return void
     */
    private function displayAvailableThemes(array $frontendThemes): void
    {
        $this->io->writeln('No theme specified. Available frontend themes:');
        foreach (array_keys($frontendThemes) as $code) {
            $this->io->writeln("  - $code");
        }
        $this->io->newLine();
        $this->displayUsage();
    }

    /**
     * Display usage examples
     *
     * @return void
     */
    private function displayUsage(): void
    {
        $this->io->writeln('Usage: bin/magento mageforge:template:override <template> --theme <theme-code>');
        $this->io->writeln(
            'Example: bin/magento mageforge:template:override '
            . "'Magento_Catalog::product/view/details.phtml' --theme Vendor/theme",
        );
        $this->io->writeln('The template can also be given as a file path, e.g.');
        $this->io->writeln('  vendor/magento/module-catalog/view/frontend/templates/product/view/details.phtml');
    }

    /**
     * Make a path relative to the Magento root directory for display
     *
     * @param string $path
     * @return string
     */
    private function toRelativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->directoryList->getRoot()), '/');
        $normalized = str_replace('\\', '/', $path);
        if ($root !== '' && str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $path;
    }
}
