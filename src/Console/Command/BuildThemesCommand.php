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
     *
     * @param ThemePath $themePath Service to resolve theme paths
     * @param Shell $shell Magento shell command executor
     * @param ThemeList $themeList Service to get the list of themes
     * @param File $fileDriver Magento filesystem driver
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly Shell $shell,
        private readonly ThemeList $themeList,
        private readonly File $fileDriver,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command
     *
     * @return void
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
     *
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     * @return int Exit code (0 for success, non-zero for failure)
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
     *
     * @param SymfonyStyle $io The IO interface for interaction
     * @param bool $isVerbose Whether to output verbose messages
     * @return int Command result code
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
     *
     * @param array $themeCodes List of theme codes to build
     * @param SymfonyStyle $io The IO interface for interaction
     * @param OutputInterface $output Console output
     * @param bool $isVerbose Whether to output verbose messages
     * @return int Command result code
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
            if (!$this->processTheme($themeCode, $io, $output, $isVerbose, $progressBar, $successList)) {
                continue;
            }
        }

        $this->displayBuildSummary($io, $output, $progressBar, $successList, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

    /**
     * Processes a single theme build
     *
     * @param string $themeCode The theme code to process
     * @param SymfonyStyle $io The IO interface for interaction
     * @param OutputInterface $output Console output
     * @param bool $isVerbose Whether to output verbose messages
     * @param ProgressBar $progressBar Progress indicator
     * @param array $successList List of successful operations
     * @return bool True if theme was processed successfully, false otherwise
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
     * Executes Grunt tasks for theme building
     *
     * @param SymfonyStyle $io The IO interface for interaction
     * @param OutputInterface $output Console output
     * @param bool $isVerbose Whether to output verbose messages
     * @param ProgressBar $progressBar Progress indicator
     * @param array $successList List of successful operations
     * @return bool True if grunt tasks executed successfully, false otherwise
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
     *
     * @param string $themeCode The theme code to deploy
     * @param SymfonyStyle $io The IO interface for interaction
     * @param OutputInterface $output Console output
     * @param bool $isVerbose Whether to output verbose messages
     * @param ProgressBar $progressBar Progress indicator
     * @param array $successList List of successful operations
     * @return bool True if deployment was successful, false otherwise
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
     *
     * @param OutputInterface $output The output interface to check
     * @return bool True if output should be verbose, false otherwise
     */
    private function isVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Checks if the given path is a directory
     *
     * @param string $path The path to check
     * @return bool True if the path is a directory, false otherwise
     */
    private function isDirectory(string $path): bool
    {
        return $this->fileDriver->isDirectory($path);
    }

    /**
     * Verifies and sets up package.json
     *
     * Checks if package.json exists, if not, offers to copy from sample file.
     *
     * @param SymfonyStyle $io The IO interface for user interaction
     * @param bool $isVerbose Whether to output verbose messages
     * @return bool True if package.json is ready, false if setup failed
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
     *
     * @param SymfonyStyle $io The IO interface for user interaction
     * @param bool $isVerbose Whether to output verbose messages
     * @return bool True if node_modules is ready, false if setup failed
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
     *
     * @param SymfonyStyle $io The IO interface for user interaction
     * @param string $file The target file to check
     * @param string $sampleFile The sample file to copy from if needed
     * @param bool $isVerbose Whether to output verbose messages
     * @return bool True if file is ready, false if setup failed
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
     *
     * @param OutputInterface $output Console output
     * @param int $max Maximum steps for the progress bar
     * @return ProgressBar Configured progress bar instance
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
     *
     * @param SymfonyStyle $io The IO interface for interaction
     * @param int $themesCount Number of themes to build
     * @return void
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
     *
     * @param SymfonyStyle $io The IO interface for interaction
     * @param OutputInterface $output Console output
     * @param ProgressBar $progressBar Progress indicator
     * @param array $successList List of successful operations
     * @param float $duration Total build duration in seconds
     * @return void
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
     *
     * @param string $themeCode The theme code to validate
     * @param SymfonyStyle $io The IO interface for interaction
     * @param array $successList List of successful operations
     * @return bool True if theme is valid, false otherwise
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
     *
     * @param string $themeCode The theme code being processed
     * @param SymfonyStyle $io The IO interface for interaction
     * @param bool $isVerbose Whether to output verbose messages
     * @param ProgressBar $progressBar Progress indicator
     * @param array $successList List of successful operations
     * @return bool True if all dependencies are satisfied, false otherwise
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
