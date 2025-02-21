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

        if (empty($themeCodes)) {
            $io->title('No theme codes provided. Available themes:');
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

        $io->title($themesCount > 1
            ? 'Build ' . $themesCount . ' themes! This can take a while, please wait.'
            : 'Build the theme. Please wait...');

        foreach ($themeCodes as $themeCode) {
            $themePath = $this->themePath->getPath($themeCode);
            if ($themePath === null) {
                $io->error("Theme $themeCode is not installed.");
                continue;
            }
            $io->section("Theme: $themeCode");

            if (!$this->checkPackageJson($io) || !$this->checkNodeModules($io)) {
                continue;
            }

            // Check Gruntfile.js file
            if (!$this->checkFile($io, 'Gruntfile.js', 'Gruntfile.js.sample')) {
                continue;
            }

            // Run Grunt only on the first run
            static $isFirstRun = true;
            if ($isFirstRun) {
                $io->section("Running 'grunt'... Please wait.");
                try {
                    $output = $this->shell->execute('node_modules/.bin/grunt clean');
                    $io->writeln($output);
                    $io->success("'grunt clean' has been successfully executed.");

                    $output = $this->shell->execute('node_modules/.bin/grunt less');
                    $io->writeln($output);
                    $io->success("'grunt less' has been successfully executed.");
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                    continue;
                }
                $isFirstRun = false;
            } else {
                $io->success("Grunt has been already executed.");
            }

            // Run static content deploy
            $io->section("Running 'magento setup:static-content:deploy -t $themeCode -f'... Please wait.");
            $sanitizedThemeCode = escapeshellarg($themeCode);
            try {
                $output = $this->shell->execute(
                    "php bin/magento setup:static-content:deploy -t %s -f",
                    [$sanitizedThemeCode]
                );
                $io->writeln($output);
                $io->success(
                    "'magento setup:static-content:deploy -t $sanitizedThemeCode -f' has been successfully executed."
                );
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                continue;
            }

            // Clean the output before the next theme is running
            $outputLines = [];

            $io->success("Theme $themeCode has been successfully built.");
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $io->section("Build process completed.");
        $io->success("All themes have been built successfully in " . round($duration, 2) . " seconds.");

        return Command::SUCCESS;
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
     * @return bool True if package.json is ready, false if setup failed
     */
    private function checkPackageJson(SymfonyStyle $io): bool
    {
        if (!is_file('package.json')) {
            $io->warning("The 'package.json' file does not exist in the Magento root path.");
            if (!is_file('package.json.sample')) {
                $io->warning("The 'package.json.sample' file does not exist in the Magento root path.");
                $io->error("Skipping this theme build.");
                return false;
            } else {
                $io->success("The 'package.json.sample' file found.");
                if ($io->confirm("Copy 'package.json.sample' to 'package.json'?", false)) {
                    copy('package.json.sample', 'package.json');
                    $io->success("'package.json.sample' has been copied to 'package.json'.");
                }
            }
        } else {
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
     * @return bool True if node_modules is ready, false if setup failed
     */
    private function checkNodeModules(SymfonyStyle $io): bool
    {
        if (!$this->isDirectory('node_modules')) {
            $io->warning("The 'node_modules' folder does not exist in the Magento root path.");
            if ($io->confirm("Run 'npm install' to install the dependencies?", false)) {
                $io->section("Running 'npm install'... Please wait.");
                try {
                    $output = $this->shell->execute('npm install');
                    $io->writeln($output);
                    $io->success("'npm install' has been successfully executed.");
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                    return false;
                }
            } else {
                $io->error("Skipping this theme build.");
                return false;
            }
        } else {
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
     * @return bool True if file is ready, false if setup failed
     */
    private function checkFile(SymfonyStyle $io, string $file, string $sampleFile): bool
    {
        if (!is_file($file)) {
            $io->warning("The '$file' file does not exist in the Magento root path.");
            if (!is_file($sampleFile)) {
                $io->warning("The '$sampleFile' file does not exist in the Magento root path.");
                $io->error("Skipping this theme build.");
                return false;
            } else {
                $io->success("The '$sampleFile' file found.");
                if ($io->confirm("Copy '$sampleFile' to '$file'?", false)) {
                    copy($sampleFile, $file);
                    $io->success("'$sampleFile' has been copied to '$file'.");
                }
            }
        } else {
            $io->success("The '$file' file found.");
        }
        return true;
    }
}
