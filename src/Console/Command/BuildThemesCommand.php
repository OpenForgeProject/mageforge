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
        $io->confirm("Build all " . $themesCount . " Themes?", false);

        $io->title(count($themeCodes) > 1
            ? 'Build ' . $themesCount . ' themes! This can take a while, please wait.'
            : 'Build the theme.'
        );
        foreach ($themeCodes as $themeCode) {
            $themePath = $this->themePath->getPath($themeCode);
            $io->section("Theme Code: $themeCode");
            $io->text("Theme Path: $themePath");
        }

        return Command::SUCCESS;
    }
}
