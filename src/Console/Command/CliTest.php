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
        for ($i = 5; $i >= 1; $i--) {
            $output->writeln('Start npm in ' . $i);
            sleep(1);
        }

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

        return Command::SUCCESS;
    }

}
