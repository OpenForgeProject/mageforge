<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\SelectPrompt;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeInfoCollector;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for showing detailed information about a single theme
 */
class InfoCommand extends AbstractCommand
{
    private const MAX_PARENT_DEPTH = 20;
    private const HYVA_BUILDER_NAME = 'HyvaThemes';
    private const STANDARD_BUILDER_NAME = 'MagentoStandard';
    private const HYVA_THEME_PREFIX = 'Hyva/';

    /**
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param BuilderPool $builderPool
     * @param ThemeSuggester $themeSuggester
     * @param Shell $shell
     * @param File $fileDriver
     * @param ThemeInfoCollector $infoCollector
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly BuilderPool $builderPool,
        private readonly ThemeSuggester $themeSuggester,
        private readonly Shell $shell,
        private readonly File $fileDriver,
        private readonly ThemeInfoCollector $infoCollector,
        private readonly DirectoryList $directoryList,
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
            ->setName($this->getCommandName('theme', 'info'))
            ->setDescription('Shows theme details (parents, builder, Node.js and Tailwind versions)')
            ->addArgument(
                'themeCode',
                InputArgument::OPTIONAL,
                'Theme code (format: Vendor/theme), omit to select interactively',
            )
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output the information as JSON')
            ->setAliases(['m:t:i', 'frontend:info']);
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
        /** @var string|null $themeCode */
        $themeCode = $input->getArgument('themeCode');
        $asJson = (bool) $input->getOption('json');

        if ($themeCode === null) {
            $themeCode = $this->selectThemeInteractively($input, $output);
            if ($themeCode === null) {
                return Cli::RETURN_SUCCESS;
            }
        }

        $path = $this->themePath->getPath($themeCode);
        $theme = $this->findTheme($themeCode);

        if ($path === null || $theme === null) {
            $suggestions = $this->themeSuggester->findSimilarThemes($themeCode);
            $this->io->error("Theme '$themeCode' is not installed.");
            if ($suggestions !== []) {
                $this->io->writeln('Did you mean: ' . implode(', ', $suggestions) . '?');
            }

            return Cli::RETURN_FAILURE;
        }

        $builder = $this->builderPool->getBuilder($path);
        $info = [
            'code' => $theme->getCode(),
            'title' => $theme instanceof DataObject ? $this->stringify($theme->getData('theme_title')) : '',
            'area' => $theme->getArea(),
            'path' => $path,
            'composer' => $this->infoCollector->getComposerInfo($path),
            'parents' => $this->getParentChain($theme),
            'builder' => $builder?->getName(),
            'node' => $this->getNodeVersion(),
            'tailwind' => $this->getTailwindInfo($path),
            'node_modules' => $this->hasNodeModules($path, $builder?->getName()),
            'overrides' => $this->infoCollector->getOverrideCounts($path),
            'hyva' => $this->isHyvaTheme($theme, $builder?->getName())
                ? $this->infoCollector->getHyvaInfo($path)
                : null,
            'locales' => $this->infoCollector->getDeployedLocales($theme->getArea(), $theme->getCode()),
        ];

        if ($asJson) {
            $output->writeln(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return Cli::RETURN_SUCCESS;
        }

        $this->renderInfo($info);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Let the user pick a theme from a select menu.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string|null
     */
    private function selectThemeInteractively(InputInterface $input, OutputInterface $output): ?string
    {
        $options = array_values(array_map(
            static fn(ThemeInterface $theme) => $theme->getCode(),
            $this->themeList->getAllThemes(),
        ));

        if ($options === []) {
            $this->io->info('No themes found.');
            return null;
        }

        if (!$input->isInteractive() || !$this->isInteractiveTerminal($output)) {
            $this->io->warning('No theme specified. Available themes:');
            $this->io->listing($options);
            $this->io->info('Usage: bin/magento mageforge:theme:info <theme-code>');
            return null;
        }

        $this->setPromptEnvironment();

        $prompt = new SelectPrompt(
            label: 'Select a theme',
            options: $options,
            scroll: 10,
            hint: 'Arrow keys to navigate, Enter to confirm',
        );

        try {
            $selection = $prompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();

            return is_string($selection) ? $selection : null;
        } finally {
            $this->resetPromptEnvironment();
        }
    }

    /**
     * Render the collected information.
     *
     * @param array<string,mixed> $info
     * @return void
     */
    private function renderInfo(array $info): void
    {
        /** @var string[] $parents */
        $parents = $info['parents'];
        /** @var array{required: ?string, installed: ?string}|null $tailwind */
        $tailwind = $info['tailwind'];
        /** @var array{package: ?string, version: ?string, source: string} $composer */
        $composer = $info['composer'];
        /** @var array{modules: int, templates: int, layouts: int, requirejs: int, webAssets: int} $overrides */
        $overrides = $info['overrides'];
        /** @var array{version: ?string, tailwindConfig: ?string, extensions: array<int, string>}|null $hyva */
        $hyva = $info['hyva'];
        /** @var string[] $locales */
        $locales = $info['locales'];
        $none = '<fg=gray>-</>';

        $this->io->section('Theme: ' . $this->stringify($info['code']));
        $this->io->definitionList(
            ['Title' => $info['title']],
            ['Area' => $info['area']],
            ['Path' => $info['path']],
            [
                'Composer' => $composer['package'] === null
                    ? sprintf('%s (no composer.json)', $composer['source'])
                    : sprintf('%s %s (%s)', $composer['package'], $composer['version'] ?? '?', $composer['source']),
            ],
            ['Parent chain' => $parents === [] ? $none : implode(' → ', $parents)],
            ['Builder' => $info['builder'] ?? '<fg=red>none detected</>'],
            ['Node.js' => $info['node'] ?? '<fg=red>not found</>'],
            [
                'Tailwind CSS' => $tailwind === null
                    ? $none
                    : sprintf(
                        'required %s, installed %s',
                        $tailwind['required'] ?? '?',
                        $tailwind['installed'] ?? 'not installed',
                    ),
            ],
            ['node_modules' => $info['node_modules'] ? 'present' : 'missing'],
            [
                'Overrides' => sprintf(
                    '%d modules: %d templates, %d layouts, %d requirejs-config, %d web files',
                    $overrides['modules'],
                    $overrides['templates'],
                    $overrides['layouts'],
                    $overrides['requirejs'],
                    $overrides['webAssets'],
                ),
            ],
            ['Deployed locales' => $locales === [] ? $none : implode(', ', $locales)],
        );

        if ($hyva !== null) {
            $this->io->definitionList(
                ['Hyvä version' => $hyva['version'] ?? '?'],
                ['Tailwind config' => $hyva['tailwindConfig'] ?? $none],
                [
                    'Extensions (hyva-themes.json)' => $hyva['extensions'] === []
                        ? $none
                        : implode(PHP_EOL, $hyva['extensions']),
                ],
            );
        }
    }

    /**
     * Convert a scalar value to string for display.
     *
     * @param mixed $value
     * @return string
     */
    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Find a theme model by its code.
     *
     * @param string $themeCode
     * @return ThemeInterface|null
     */
    private function findTheme(string $themeCode): ?ThemeInterface
    {
        foreach ($this->themeList->getAllThemes() as $theme) {
            if ($theme->getCode() === $themeCode) {
                return $theme;
            }
        }

        return null;
    }

    /**
     * Get the codes of all parent themes, nearest first.
     *
     * @param ThemeInterface $theme
     * @return string[]
     */
    private function getParentChain(ThemeInterface $theme): array
    {
        $chain = [];
        $parent = $theme->getParentTheme();

        while ($parent !== null && count($chain) < self::MAX_PARENT_DEPTH) {
            $chain[] = $parent->getCode();
            $parent = $parent->getParentTheme();
        }

        return $chain;
    }

    /**
     * Get the installed Node.js version.
     *
     * @return string|null
     */
    private function getNodeVersion(): ?string
    {
        try {
            return trim($this->shell->execute('node --version'));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Read the required and installed Tailwind CSS versions.
     *
     * @param string $themePath
     * @return array{required: ?string, installed: ?string}|null
     */
    private function getTailwindInfo(string $themePath): ?array
    {
        foreach ([$themePath, $themePath . '/web/tailwind'] as $dir) {
            $package = $this->readJson($dir . '/package.json');
            $required = $this->getDependency($package, 'dependencies') ?? $this->getDependency(
                $package,
                'devDependencies',
            );

            if (!is_string($required)) {
                continue;
            }

            $installed = $this->readJson($dir . '/node_modules/tailwindcss/package.json')['version'] ?? null;

            return [
                'required' => $required,
                'installed' => is_string($installed) ? $installed : null,
            ];
        }

        return null;
    }

    /**
     * Get the tailwindcss version constraint from a package.json section.
     *
     * @param array<string,mixed> $package
     * @param string $section
     * @return string|null
     */
    private function getDependency(array $package, string $section): ?string
    {
        $dependencies = $package[$section] ?? null;
        $version = is_array($dependencies) ? $dependencies['tailwindcss'] ?? null : null;

        return is_string($version) ? $version : null;
    }

    /**
     * Check whether the theme has installed Node.js dependencies.
     *
     * Standard Magento themes are built with Grunt from the Magento root, so its node_modules counts as well.
     *
     * @param string $themePath
     * @param string|null $builderName
     * @return bool
     */
    private function hasNodeModules(string $themePath, ?string $builderName): bool
    {
        $candidates = [$themePath . '/node_modules', $themePath . '/web/tailwind/node_modules'];
        if ($builderName === self::STANDARD_BUILDER_NAME) {
            $candidates[] = $this->directoryList->getRoot() . '/node_modules';
        }

        foreach ($candidates as $candidate) {
            if ($this->fileDriver->isDirectory($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a theme is a Hyvä theme, by its builder or by inheriting from a Hyvä theme.
     *
     * A theme extending a custom Hyvä base is detected as plain Tailwind theme by the builders, so its ancestors
     * are checked as well.
     *
     * @param ThemeInterface $theme
     * @param string|null $builderName
     * @return bool
     */
    private function isHyvaTheme(ThemeInterface $theme, ?string $builderName): bool
    {
        if ($builderName === self::HYVA_BUILDER_NAME) {
            return true;
        }

        $parent = $theme->getParentTheme();
        for ($depth = 0; $parent !== null && $depth < self::MAX_PARENT_DEPTH; $depth++) {
            if (str_starts_with($parent->getCode(), self::HYVA_THEME_PREFIX)) {
                return true;
            }
            $parent = $parent->getParentTheme();
        }

        return false;
    }

    /**
     * Read a JSON file into an array.
     *
     * @param string $file
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        if (!$this->fileDriver->isFile($file)) {
            return [];
        }

        $data = json_decode($this->fileDriver->fileGetContents($file), true);

        if (!is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
