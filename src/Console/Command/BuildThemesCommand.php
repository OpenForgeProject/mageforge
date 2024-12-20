<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Model\ThemePath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuildThemesCommand extends Command
{
    /**
     * Constructor
     *
     * @param ThemePath $themePath
     */
    public function __construct(
        private readonly ThemePath $themePath,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mageforge:themes:build')
            ->setDescription('Builds a Magento theme')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The codes of the theme to build'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeCodes = $input->getArgument('themeCodes');
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
                exec('node_modules/.bin/grunt clean', $outputLines, $resultCode);
                $io->writeln($outputLines);
                if ($resultCode === 0) {
                    $io->success("'grunt clean' has been successfully executed.");
                } else {
                    $io->error("'grunt clean' failed. Please check the output for more details.");
                    continue;
                }
                exec('node_modules/.bin/grunt less', $outputLines, $resultCode);
                $io->writeln($outputLines);
                if ($resultCode === 0) {
                    $io->success("'grunt less' has been successfully executed.");
                } else {
                    $io->error("'grunt less' failed. Please check the output for more details.");
                    continue;
                }
                $isFirstRun = false;
            } else {
                $io->success("Grunt has been already executed.");
            }

            // Run static content deploy
            $io->section("Running 'magento setup:static-content:deploy -t $themeCode -f'... Please wait.");
            $sanitizedThemeCode = escapeshellarg($themeCode);
            exec("php bin/magento setup:static-content:deploy -t $sanitizedThemeCode -f", $outputLines, $resultCode);
            $io->writeln($outputLines);
            if ($resultCode === 0) {
                $io->success("'magento setup:static-content:deploy -t $sanitizedThemeCode -f' has been successfully executed.");
            } else {
                $io->error("'magento setup:static-content:deploy -t $sanitizedThemeCode -f' failed. Please check the output for more details.");
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

    private function checkPackageJson(SymfonyStyle $io): bool
    {
        if (!file_exists('package.json')) {
            $io->warning("The 'package.json' file does not exist in the Magento root path.");
            if (!file_exists('package.json.sample')) {
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

    private function checkNodeModules(SymfonyStyle $io): bool
    {
        if (!is_dir('node_modules')) {
            $io->warning("The 'node_modules' folder does not exist in the Magento root path.");
            if ($io->confirm("Run 'npm install' to install the dependencies?", false)) {
                $io->section("Running 'npm install'... Please wait.");
                exec('npm install', $outputLines, $resultCode);
                if ($resultCode === 0) {
                    $io->success("'npm install' has been successfully executed.");
                } else {
                    $io->error("'npm install' failed. Please check the output for more details.");
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

    private function checkFile(SymfonyStyle $io, string $file, string $sampleFile): bool
    {
        if (!file_exists($file)) {
            $io->warning("The '$file' file does not exist in the Magento root path.");
            if (!file_exists($sampleFile)) {
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
