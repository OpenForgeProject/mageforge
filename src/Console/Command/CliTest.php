<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Dotenv\Regex\Success;
use Laravel\Prompts\MultiSelectPrompt;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CliTest extends Command
{

     /**
     * Constructor
     *
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly BuilderPool $builderPool
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mageforge:system:clitest')
            ->setDescription('Tests the Command Line Interface')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Command test'
            )
            ->setAliases(['frontend:test']);
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        try {
            for ($i = 5; $i >= 1; $i--) {
                $output->writeln('Start npm in ' . $i);
                sleep(1);
            }

            /**
             * list all available themes
             */
            $themes = $this->themeList->getAllThemes();
            if (empty($themes)) {
                $output->writeln('<info>No themes found.</info>');
                return Cli::RETURN_SUCCESS;
            }

            $output->writeln('<info>Available Themes:</info>');
            $table = new Table($output);
            $table->setHeaders(['Code', 'Title', 'Path']);

            foreach ($themes as $path => $theme) {
                $table->addRow([
                    sprintf('<fg=yellow>%s</>', $theme->getCode()),
                    $theme->getThemeTitle(),
                    $path
                ]);
            }

            $table->render();

            /**
             * Run NPM Outdated and NPM Install
             */
            $output->writeln('Running npm outdated...');
            exec('npm outdated', $npmOutput, $returnValue);

            if ($returnValue !== 0 || !empty($npmOutput)) {
                $io = new SymfonyStyle($input, $output);
                $io->warning('Outdated packages found!');
            } else {
                $io = new SymfonyStyle($input, $output);
                $io->success('No outdated packages found, proceeding with installation.');
            }

            foreach ($npmOutput as $line) {
                $output->writeln($line);
            }

            sleep(2);
            $output->writeln('Running npm install...');
            exec('npm install', $npmOutput, $returnValue);
            foreach ($npmOutput as $line) {
                $output->writeln($line);
            }

            if ($returnValue !== 0) {
                $io = new SymfonyStyle($input, $output);
                $io->error('Npm install failed!');
                return Command::FAILURE;
            }

            $io->success('Npm install completed successfully.');


            // build
            $themeCodes = $input->getArgument('themeCodes');
            $isVerbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

            if (empty($themeCodes)) {
                $themes = $this->themeList->getAllThemes();
                $options = array_map(fn($theme) => $theme->getCode(), $themes);


                $themeCodesPrompt = new MultiSelectPrompt(
                    label: 'Select themes to build',
                    options: $options,
                    scroll: 10,
                    hint: 'Arrow keys to navigate, Space to select, Enter to confirm'
                );

                $themeCodes = $themeCodesPrompt->prompt();
            }

            return $this->processBuildThemes($themeCodes, $io, $output, $isVerbose);

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
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
