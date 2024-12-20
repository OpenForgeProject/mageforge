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

        # $io->confirm(question: "Build all " . $themesCount . " Themes?", false);

        $io->title(count($themeCodes) > 1
            ? 'Build ' . $themesCount . ' themes! This can take a while, please wait.'
            : 'Build the theme.');
        foreach ($themeCodes as $themeCode) {
            $themePath = $this->themePath->getPath($themeCode);
            if ($themePath === null) {
                $io->error("Theme $themeCode is not installed.");
                continue;
            }
            $io->section("Theme Code: $themeCode");

            # Check package.json file
            if (!file_exists('package.json')) {
                $io->warning("The 'package.json' file does not exist in the Magento root path.");
                if (!file_exists('package.json.sample')) {
                    $io->warning("The 'package.json.sample' file does not exist in the Magento root path.");
                    $io->error("Skip this theme build.");
                    continue;
                } else {
                    $io->success("The 'package.json.sample' file found.");
                    if ($io->confirm("Do you want to copy 'package.json.sample' to 'package.json'?", false)) {
                        copy('package.json.sample', 'package.json');
                        $io->success("'package.json.sample' has been copied to 'package.json'.");
                    }
                }
            } else {
                $io->success("The 'package.json' file found.");
            }

            # Check node_modules folder
            if (!is_dir('node_modules')) {
                $io->warning("The 'node_modules' folder does not exist in the Magento root path.");
                if ($io->confirm("Do you want to run 'npm install' to install the dependencies? ", false)) {
                    $io->section("Running 'npm install'... This can take a while, please wait.");
                    exec('npm install', $outputLines, $resultCode);
                    if ($resultCode === 0) {
                        $io->success("'npm install' has been successfully executed.");
                    } else {
                        $io->error("'npm install' failed. Please check the output for more details.");
                        continue;
                    }
                } else {
                    $io->error("Skip this theme build.");
                    continue;
                }
            } else {
                $io->success("The 'node_modules' folder found.");
            }
        }
        return Command::SUCCESS;
    }
}
