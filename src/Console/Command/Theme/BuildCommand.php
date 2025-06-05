<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Spinner;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
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
    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param BuilderPool $builderPool
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly BuilderPool $builderPool
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

            $themeCodesPrompt = new MultiSelectPrompt(
                label: 'Select themes to build',
                options: $options,
                scroll: 10,
                hint: 'Arrow keys to navigate, Space to select, Enter to confirm',
            );

            $themeCodes = $themeCodesPrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
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
}
