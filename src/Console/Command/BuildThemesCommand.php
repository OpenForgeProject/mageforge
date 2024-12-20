<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuildThemesCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mageforge:themes:build')
            ->setDescription('Builds a Magento theme')
            ->addArgument('themeCodes', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The codes of the themes to build');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeCodes = $input->getArgument('themeCodes');

        $io->title(count($themeCodes) > 1 ? 'Check themes' : 'Check theme');
        foreach ($themeCodes as $themeCode) {
            $io->section("Check $themeCode ...");
        }

        return Command::SUCCESS;
    }
}
