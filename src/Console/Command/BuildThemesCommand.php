<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Model\ThemePath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Model\ThemeList;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\HyvaThemeDetector;

/**
 * Command to build Magento themes
 *
 * This command executes the necessary steps to build one or more Magento themes:
 * - Verifies required files (package.json, Gruntfile.js)
 * - Runs Grunt tasks (clean, less)
 * - Deploys static content for the theme
 */
class BuildThemesCommand extends Command
{
    private const PACKAGE_JSON = 'package.json';
    private const PACKAGE_JSON_SAMPLE = 'package.json.sample';
    private const GRUNTFILE = 'Gruntfile.js';
    private const GRUNTFILE_SAMPLE = 'Gruntfile.js.sample';
    private const NODE_MODULES = 'node_modules';
    private const GRUNT_PATH = 'node_modules/.bin/grunt';

    /**
     * Constructor
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly Shell $shell,
        private readonly ThemeList $themeList,
        private readonly File $fileDriver,
        private readonly HyvaThemeDetector $hyvaThemeDetector
    ) {
        parent::__construct();
    }

    /**
     * Configures the command
     */
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

    /**
     * Executes the theme building process
     *
     * This method:
     * 1. Validates theme existence
     * 2. Checks for required files and dependencies
     * 3. Runs Grunt tasks
     * 4. Deploys static content
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $io = new SymfonyStyle($input, $output);
            $themeCodes = $input->getArgument('themeCodes');
            $isVerbose = $this->isVerbose($output);

            if (empty($themeCodes)) {
                return $this->displayAvailableThemes($io, $isVerbose);
            }

            return $this->processBuildThemes($themeCodes, $io, $output, $isVerbose);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Displays available themes when no theme code is provided
     */
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

    /**
     * Processes the build for multiple themes
     */
    private function processBuildThemes(
        array $themeCodes,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): int {
        $startTime = microtime(true);
        $successList = [];
        $totalSteps = count($themeCodes) * 4;

        $progressBar = $this->createProgressBar($output, $totalSteps);

        if ($isVerbose) {
            $this->displayBuildHeader($io, count($themeCodes));
        }

        foreach ($themeCodes as $themeCode) {
            $themePath = $this->themePath->getPath($themeCode);
            if ($themePath && $this->hyvaThemeDetector->isHyvaTheme($themePath)) {
                if ($isVerbose) {
                    $io->note("Theme $themeCode is a Hyvä theme - adjusting build process");
                }
                // Here you could add specific Hyvä theme build logic in the future
            }

            // Process the theme build regardless of whether it's Hyvä or not
            if (!$this->processTheme($themeCode, $io, $output, $isVerbose, $progressBar, $successList)) {
                continue;
            }
        }

        $this->displayBuildSummary($io, $output, $progressBar, $successList, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

    /**
     * Processes a single theme build
     */
    private function processTheme(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList
    ): bool {
        $progressBar->setMessage("Validating theme: $themeCode");
        if (!$this->validateTheme($themeCode, $io, $successList)) {
            return false;
        }
        $progressBar->advance();

        if ($isVerbose) {
            $io->section("Theme: $themeCode");
        }

        if (!$this->checkDependencies($themeCode, $io, $isVerbose, $progressBar, $successList)) {
            return false;
        }

        if (!$this->runGruntTasks($io, $output, $isVerbose, $progressBar, $successList)) {
            return false;
        }

        if (!$this->deployStaticContent($themeCode, $io, $output, $isVerbose, $progressBar, $successList)) {
            return false;
        }

        if ($isVerbose) {
            $io->success("Theme $themeCode has been successfully built.");
        }

        return true;
    }

    /**
     * Deploys static content for a theme
     */
    private function runGruntTasks(
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList
    ): bool {
        static $isFirstRun = true;
        if (!$isFirstRun) {
            $progressBar->advance();
            return true;
        }

        $progressBar->setMessage('Running Grunt tasks');
        try {
            foreach (['clean', 'less'] as $task) {
                $shellOutput = $this->shell->execute(self::GRUNT_PATH . ' ' . $task . ' --quiet');
                if ($isVerbose) {
                    $output->writeln($shellOutput);
                    $io->success("'grunt $task' has been successfully executed.");
                }
            }
            $successList[] = "✓ Grunt tasks executed";
            $isFirstRun = false;
            $progressBar->advance();
            return true;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return false;
        }
    }

    /**
     * Deploys static content for a theme
     */
    private function deployStaticContent(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList
    ): bool {
        $progressBar->setMessage("Deploying static content for theme: $themeCode");
        try {
            // @codingStandardsIgnoreLine
            $sanitizedThemeCode = escapeshellarg($themeCode);
            $shellOutput = $this->shell->execute(
                "php bin/magento setup:static-content:deploy -t %s -f -q",
                [$sanitizedThemeCode]
            );

            if ($isVerbose) {
                $output->writeln($shellOutput);
                $io->success(sprintf(
                    "'magento setup:static-content:deploy -t %s -f' has been successfully executed.",
                    $sanitizedThemeCode
                ));
            }

            $successList[] = "✓ Static content deployed for $themeCode";
            $progressBar->advance();
            return true;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return false;
        }
    }

    /**
     * Checks if output should be verbose
     */
    private function isVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Checks if the given path is a directory
     */
    private function isDirectory(string $path): bool
    {
        return $this->fileDriver->isDirectory($path);
    }

    /**
     * Verifies and sets up package.json
     */
    private function checkPackageJson(SymfonyStyle $io, bool $isVerbose): bool
    {
        if (!$this->fileDriver->isFile(self::PACKAGE_JSON)) {
            if ($isVerbose) {
                $io->warning("The 'package.json' file does not exist in the Magento root path.");
            }
            if (!$this->fileDriver->isFile(self::PACKAGE_JSON_SAMPLE)) {
                if ($isVerbose) {
                    $io->warning("The 'package.json.sample' file does not exist in the Magento root path.");
                }
                $io->error("Skipping this theme build.");
                return false;
            } else {
                if ($isVerbose) {
                    $io->success("The 'package.json.sample' file found.");
                }
                if ($io->confirm("Copy 'package.json.sample' to 'package.json'?", false)) {
                    $this->fileDriver->copy(self::PACKAGE_JSON_SAMPLE, self::PACKAGE_JSON);
                    if ($isVerbose) {
                        $io->success("'package.json.sample' has been copied to 'package.json'.");
                    }
                }
            }
        } elseif ($isVerbose) {
            $io->success("The 'package.json' file found.");
        }
        return true;
    }

    /**
     * Verifies and sets up node_modules
     *
     * Checks if node_modules exists, if not, offers to run npm install.
     */
    private function checkNodeModules(SymfonyStyle $io, bool $isVerbose): bool
    {
        if (!$this->isDirectory(self::NODE_MODULES)) {
            if ($isVerbose) {
                $io->warning("The 'node_modules' folder does not exist in the Magento root path.");
            }
            if ($io->confirm("Run 'npm install' to install the dependencies?", false)) {
                if ($isVerbose) {
                    $io->section("Running 'npm install'... Please wait.");
                }
                try {
                    $shellOutput = $this->shell->execute('npm install --quiet');
                    if ($isVerbose) {
                        $io->writeln($shellOutput);
                        $io->success("'npm install' has been successfully executed.");
                    }
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                    return false;
                }
            } else {
                $io->error("Skipping this theme build.");
                return false;
            }
        } elseif ($isVerbose) {
            $io->success("The 'node_modules' folder found.");
        }
        return true;
    }

    /**
     * Verifies and sets up required files
     *
     * Checks if a required file exists, if not, offers to copy from sample file.
     */
    private function checkFile(SymfonyStyle $io, string $file, string $sampleFile, bool $isVerbose): bool
    {
        if (!$this->fileDriver->isFile($file)) {
            if ($isVerbose) {
                $io->warning("The '$file' file does not exist in the Magento root path.");
            }
            if (!$this->fileDriver->isFile($sampleFile)) {
                if ($isVerbose) {
                    $io->warning("The '$sampleFile' file does not exist in the Magento root path.");
                }
                $io->error("Skipping this theme build.");
                return false;
            } else {
                if ($isVerbose) {
                    $io->success("The '$sampleFile' file found.");
                }
                if ($io->confirm("Copy '$sampleFile' to '$file'?", false)) {
                    $this->fileDriver->copy($sampleFile, $file);
                    if ($isVerbose) {
                        $io->success("'$sampleFile' has been copied to '$file'.");
                    }
                }
            }
        } elseif ($isVerbose) {
            $io->success("The '$file' file found.");
        }
        return true;
    }

    /**
     * Creates and configures the progress bar
     */
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

    /**
     * Displays the build process header
     */
    private function displayBuildHeader(SymfonyStyle $io, int $themesCount): void
    {
        $title = $themesCount > 1
            ? sprintf('Build %d themes! This can take a while, please wait.', $themesCount)
            : 'Build the theme. Please wait...';
        $io->title($title);
    }

    /**
     * Displays the build process summary
     */
    private function displayBuildSummary(
        SymfonyStyle $io,
        OutputInterface $output,
        ProgressBar $progressBar,
        array $successList,
        float $duration
    ): void {
        $progressBar->finish();

        $output->writeln("\n");
        $io->section('Build Summary');
        foreach ($successList as $success) {
            $output->writeln($success);
        }

        if ($this->isVerbose($output)) {
            $io->section("Build process completed.");
        }

        $output->writeln('');
        $io->success(sprintf(
            "All themes have been built successfully in %.2f seconds.",
            $duration
        ));
    }

    /**
     * Validates if a theme exists
     */
    private function validateTheme(string $themeCode, SymfonyStyle $io, array &$successList): bool
    {
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $io->error("Theme $themeCode is not installed.");
            return false;
        }
        $successList[] = "✓ Theme $themeCode validated";
        return true;
    }

    /**
     * Checks theme dependencies
     */
    private function checkDependencies(
        string $themeCode,
        SymfonyStyle $io,
        bool $isVerbose,
        ProgressBar $progressBar,
        array &$successList
    ): bool {
        $progressBar->setMessage("Checking dependencies for theme: $themeCode");
        if (!$this->checkPackageJson($io, $isVerbose) || !$this->checkNodeModules($io, $isVerbose)) {
            return false;
        }
        if (!$this->checkFile($io, self::GRUNTFILE, self::GRUNTFILE_SAMPLE, $isVerbose)) {
            return false;
        }
        $successList[] = "✓ Dependencies for $themeCode checked";
        $progressBar->advance();
        return true;
    }
}
