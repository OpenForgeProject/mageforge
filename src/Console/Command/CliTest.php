<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

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
        foreach ($npmOutput as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }

}
