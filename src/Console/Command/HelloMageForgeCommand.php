<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelloMageForgeCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mageforge:hello');
        $this->setDescription('Says Hello from MageForge');
    }

    /**
     * @inheritDoc
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $output->writeln('Hello from MageForge!');

        return Cli::RETURN_SUCCESS;
    }
}
