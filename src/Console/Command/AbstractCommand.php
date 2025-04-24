<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Abstract base class for MageForge commands
 *
 * Provides common functionality and standardized structure for all commands
 */
abstract class AbstractCommand extends Command
{
    /**
     * @var SymfonyStyle
     */
    protected SymfonyStyle $io;

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        try {
            return $this->executeCommand($input, $output);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Execute the command logic
     *
     * Each child class must implement this with their specific command logic
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

    /**
     * Get if the output is in verbose mode
     *
     * @param OutputInterface $output
     * @return bool
     */
    protected function isVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Get if the output is in very verbose mode
     *
     * @param OutputInterface $output
     * @return bool
     */
    protected function isVeryVerbose(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * Get if the output is in debug mode
     *
     * @param OutputInterface $output
     * @return bool
     */
    protected function isDebug(OutputInterface $output): bool
    {
        return $output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
    }
}
