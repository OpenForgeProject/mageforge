<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuildThemesCommand extends Command
{
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly BuilderPool $builderPool
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mageforge:themes:build')
            ->setDescription('Builds a Magento theme')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'The codes of the theme to build'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $io = new SymfonyStyle($input, $output);
            $themeCodes = $input->getArgument('themeCodes');
            $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

            if (empty($themeCodes)) {
                return $this->displayAvailableThemes($io);
            }

            return $this->processBuildThemes($themeCodes, $io, $output, $isVerbose);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayAvailableThemes(SymfonyStyle $io): int
    {
        $table = new Table($io);
        $table->setHeaders(['Theme Code', 'Title']);

        foreach ($this->themeList->getAllThemes() as $theme) {
            $table->addRow([$theme->getCode(), $theme->getThemeTitle()]);
        }

        $table->render();
        $io->info('Usage: bin/magento mageforge:themes:build <theme-code> [<theme-code>...]');

        return Command::SUCCESS;
    }

    private function processBuildThemes(
        array $themeCodes,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): int {
        $startTime = microtime(true);
        $successList = [];

        if ($isVerbose) {
            $io->title(sprintf('Building %d theme(s)', count($themeCodes)));
        }

        foreach ($themeCodes as $themeCode) {
            if (!$this->processTheme($themeCode, $io, $output, $isVerbose, $successList)) {
                continue;
            }
        }

        $this->displayBuildSummary($io, $successList, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

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

    private function displayBuildSummary(SymfonyStyle $io, array $successList, float $duration): void
    {
        if (empty($successList)) {
            $io->warning('No themes were built successfully.');
            return;
        }

        $io->success(sprintf(
            "Build process completed in %.2f seconds with the following results:",
            $duration
        ));

        foreach ($successList as $success) {
            $io->writeln("✓ $success");
        }
    }
}
