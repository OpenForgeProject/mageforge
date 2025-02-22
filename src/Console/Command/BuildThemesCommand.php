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
    /**
     * Constructor
     *
     * @param ThemePath $themePath Service to resolve theme paths
     * @param Shell $shell Magento shell command executor
     * @param ThemeList $themeList Service to get the list of themes
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly Shell $shell,
        private readonly ThemeList $themeList,
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
        $io = new SymfonyStyle($input, $output);
        $themeCodes = $input->getArgument('themeCodes');
        $isVerbose = $this->isVerbose($output);
        $successList = [];

        if (empty($themeCodes)) {
            if ($isVerbose) {
                $io->title('No theme codes provided. Available themes:');
            }
            $table = new Table($output);
            $table->setHeaders(['Theme Code', 'Title']);

            foreach ($this->themeList->getAllThemes() as $theme) {
                $table->addRow([
                    $theme->getCode(),
                    $theme->getThemeTitle()
                ]);
            }

            $table->render();
            $io->info('Usage: bin/magento mageforge:themes:build <theme-code> [<theme-code>...]');
            return Command::SUCCESS;
        }

        $themesCount = count($themeCodes);
        $startTime = microtime(true);

        // Calculate total steps (4 steps per theme)
        $totalSteps = $themesCount * 4;
        $progressBar = new ProgressBar($output, $totalSteps);
        $progressBar->setFormat(
            "\n%current%/%max% [%bar%] %percent:3s%% "
            . "in %elapsed:6s% | used Memory: %memory:6s%\n%message%"
        );
        $progressBar->setMessage('Starting build process...');

        if ($isVerbose) {
            $io->title($themesCount > 1
                ? 'Build ' . $themesCount . ' themes! This can take a while, please wait.'
                : 'Build the theme. Please wait...');
        }

        foreach ($themeCodes as $themeCode) {
            $progressBar->setMessage("Validating theme: $themeCode");
            $themePath = $this->themePath->getPath($themeCode);
            if ($themePath === null) {
                $io->error("Theme $themeCode is not installed.");
                continue;
            }
            $successList[] = "✓ Theme $themeCode validated";
            $progressBar->advance();

            if ($isVerbose) {
                $io->section("Theme: $themeCode");
            }

            $progressBar->setMessage("Checking dependencies for theme: $themeCode");
            if (!$this->checkPackageJson($io, $isVerbose) || !$this->checkNodeModules($io, $isVerbose)) {
                continue;
            }
            if (!$this->checkFile($io, 'Gruntfile.js', 'Gruntfile.js.sample', $isVerbose)) {
                continue;
            }
            $successList[] = "✓ Dependencies for $themeCode checked";
            $progressBar->advance();

            static $isFirstRun = true;
            if ($isFirstRun) {
                $progressBar->setMessage("Running Grunt tasks");
                try {
                    $shellOutput = $this->shell->execute('node_modules/.bin/grunt clean --quiet');
                    if ($isVerbose) {
                        $output->writeln($shellOutput);
                        $io->success("'grunt clean' has been successfully executed.");
                    }

                    $shellOutput = $this->shell->execute('node_modules/.bin/grunt less --quiet');
                    if ($isVerbose) {
                        $output->writeln($shellOutput);
                        $io->success("'grunt less' has been successfully executed.");
                    }
                    $successList[] = "✓ Grunt tasks executed";
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                    continue;
                }
                $isFirstRun = false;
            }
            $progressBar->advance();

            $progressBar->setMessage("Deploying static content for theme: $themeCode");
            $sanitizedThemeCode = escapeshellarg($themeCode);
            try {
                $shellOutput = $this->shell->execute(
                    "php bin/magento setup:static-content:deploy -t %s -f -q",
                    [$sanitizedThemeCode]
                );
                if ($isVerbose) {
                    $output->writeln($shellOutput);
                    $io->success(
                        sprintf(
                            "'magento setup:static-content:deploy -t %s -f' has been successfully executed.",
                            $sanitizedThemeCode
                        )
                    );
                }
                $successList[] = "✓ Static content deployed for $themeCode";
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                continue;
            }
            $progressBar->advance();

            if ($isVerbose) {
                $io->success("Theme $themeCode has been successfully built.");
            }
        }

        $progressBar->finish();
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Zeige die Erfolgsliste an
        $output->writeln("\n");
        $io->section('Build Summary');
        foreach ($successList as $success) {
            $output->writeln($success);
        }

        if ($isVerbose) {
            $io->section("Build process completed.");
        }
        $output->writeln('');
        $io->success("All themes have been built successfully in " . round($duration, 2) . " seconds.");

        return Command::SUCCESS;
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
        return is_dir($path);
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
        if (!is_file('package.json')) {
            if ($isVerbose) {
                $io->warning("The 'package.json' file does not exist in the Magento root path.");
            }
            if (!is_file('package.json.sample')) {
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
                    copy('package.json.sample', 'package.json');
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
        if (!$this->isDirectory('node_modules')) {
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
        if (!is_file($file)) {
            if ($isVerbose) {
                $io->warning("The '$file' file does not exist in the Magento root path.");
            }
            if (!is_file($sampleFile)) {
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
                    copy($sampleFile, $file);
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
}
