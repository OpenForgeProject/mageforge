<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\DependencyChecker;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\HyvaThemeDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use OpenForgeProject\MageForge\Model\ThemeList;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\HyvaThemeBuilder;

class BuildThemesCommand extends Command
{
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly File $fileDriver,
        private readonly HyvaThemeDetector $hyvaThemeDetector,
        private readonly DependencyChecker $dependencyChecker,
        private readonly GruntTaskRunner $gruntTaskRunner,
        private readonly StaticContentDeployer $staticContentDeployer,
        private readonly HyvaThemeBuilder $hyvaThemeBuilder
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
                return $this->displayAvailableThemes($io, $isVerbose);
            }

            return $this->processBuildThemes($themeCodes, $io, $output, $isVerbose);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function displayAvailableThemes(SymfonyStyle $io, bool $isVerbose): int
    {
        if ($isVerbose) {
            $io->title('No theme codes provided. Available themes:');
        }

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
        $hasStandardTheme = false;

        // Check if there are any standard Magento themes
        foreach ($themeCodes as $themeCode) {
            $themePath = $this->themePath->getPath($themeCode);
            if ($themePath && !$this->hyvaThemeDetector->isHyvaTheme($themePath)) {
                $hasStandardTheme = true;
                break;
            }
        }

        $totalSteps = count($themeCodes) * ($hasStandardTheme ? 4 : 2);
        $progressBar = $this->createProgressBar($output, $totalSteps);

        if ($isVerbose) {
            $this->displayBuildHeader($io, count($themeCodes));
        }

        foreach ($themeCodes as $themeCode) {
            if (!$this->processTheme($themeCode, $io, $output, $isVerbose, $progressBar, $successList, $hasStandardTheme)) {
                continue;
            }
        }

        $this->displayBuildSummary($io, $output, $progressBar, $successList, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

    private function processTheme(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList,
        bool $hasStandardTheme
    ): bool {
        $progressBar->setMessage("Validating theme: $themeCode");

        // Validate theme and get theme path
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $io->error("Theme $themeCode is not installed.");
            return false;
        }
        $progressBar->advance();
        $successList[] = "$themeCode: Theme validated";

        // Check if it's a Hyva theme
        $isHyvaTheme = $this->hyvaThemeDetector->isHyvaTheme($themePath);
        if ($isVerbose) {
            $io->section("Theme: $themeCode" . ($isHyvaTheme ? ' (Hyvä Theme)' : ' (Magento Standard Theme)'));
        }

        if ($isHyvaTheme) {
            return $this->processHyvaThemeBuild($themeCode, $io, $output, $isVerbose, $progressBar, $successList);
        }

        return $this->processMagentoThemeBuild($themeCode, $io, $output, $isVerbose, $progressBar, $successList, $hasStandardTheme);
    }

    private function processMagentoThemeBuild(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList,
        bool $hasStandardTheme
    ): bool {
        // Check dependencies only if there are standard themes
        if ($hasStandardTheme) {
            if (!$this->dependencyChecker->checkDependencies($io, $isVerbose)) {
                return false;
            }
            $progressBar->advance();
            $successList[] = "$themeCode: Dependencies checked";

            // Run Grunt tasks
            static $gruntTasksRun = false;
            if (!$gruntTasksRun) {
                $progressBar->setMessage('Running Grunt tasks');
                if (!$this->gruntTaskRunner->runTasks($io, $output, $isVerbose)) {
                    return false;
                }
                $successList[] = "Global: Grunt tasks executed";
                $gruntTasksRun = true;
            }
            $progressBar->advance();
        }

        // Deploy static content
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }
        $successList[] = "$themeCode: Static content deployed";
        $progressBar->advance();

        if ($isVerbose) {
            $io->success("Theme $themeCode has been successfully built.");
        }

        return true;
    }

    private function processHyvaThemeBuild(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList
    ): bool {
        $themePath = $this->themePath->getPath($themeCode);

        // Build Hyva theme
        if (!$this->hyvaThemeBuilder->build($themePath, $io, $output, $isVerbose)) {
            return false;
        }
        $successList[] = "$themeCode: Hyvä theme built successfully";
        $progressBar->advance(2);

        // Deploy static content
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }
        $successList[] = "$themeCode: Static content deployed (Hyvä Theme)";
        $progressBar->advance();

        if ($isVerbose) {
            $io->success("Hyvä theme $themeCode has been successfully built.");
        }

        return true;
    }

    private function validateTheme(string $themeCode, SymfonyStyle $io, array &$successList): bool
    {
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $io->error("Theme $themeCode is not installed.");
            return false;
        }

        if ($this->hyvaThemeDetector->isHyvaTheme($themePath)) {
            $io->note("Theme $themeCode is a Hyvä theme. Adjust build process ...");
        }

        $successList[] = "✓ Theme $themeCode validated";
        return true;
    }

    private function createProgressBar(OutputInterface $output, int $max): ProgressBar
    {
        $progressBar = new ProgressBar($output, $max);
        $progressBar->setFormat(
            "\n%current%/%max% [%bar%] %percent:3s%% "
            . "in %elapsed:6s% | used Memory: %memory:6s%\n%message%"
        );
        $progressBar->setMessage('Starting build process...');
        return $progressBar;
    }

    private function displayBuildHeader(SymfonyStyle $io, int $themesCount): void
    {
        $title = $themesCount > 1
            ? sprintf('Build %d themes! This can take a while, please wait.', $themesCount)
            : 'Build the theme. Please wait...';
        $io->title($title);
    }

    private function displayBuildSummary(
        SymfonyStyle $io,
        OutputInterface $output,
        ProgressBar $progressBar,
        array $successList,
        float $duration
    ): void {
        $progressBar->finish();
        $output->writeln('');

        // Group success messages by theme
        $themeGroups = [];
        foreach ($successList as $success) {
            if (strpos($success, 'Global:') === 0) {
                $themeGroups['global'][] = $success;
            } else {
                $themeCode = substr($success, 0, strpos($success, ':'));
                $themeGroups[$themeCode][] = $success;
            }
        }

        // Show global success messages first
        if (isset($themeGroups['global'])) {
            $output->writeln('<info>Global Build Steps</info>');
            $output->writeln(str_repeat('-', 18));
            foreach ($themeGroups['global'] as $success) {
                $output->writeln($success);
            }
        }

        // Show theme-specific success messages
        foreach ($themeGroups as $themeCode => $successes) {
            if ($themeCode !== 'global') {
                $output->writeln('');
                $output->writeln("<info>Theme: $themeCode</info>");
                $output->writeln(str_repeat('-', strlen("Theme: $themeCode")));
                foreach ($successes as $success) {
                    $message = substr($success, strpos($success, ':') + 2);
                    $output->writeln("✓ $message");
                }
            }
        }

        if ($this->isVerbose($output)) {
            $output->writeln('');
            $output->writeln('<info>Build process completed.</info>');
        }

        $output->writeln('');
        $io->success(sprintf(
            "All themes have been built successfully in %.2f seconds.",
            $duration
        ));
    }

    private function isVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }
}
