<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Shell;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

class GruntTaskRunner
{
    private const GRUNT_PATH = 'node_modules/.bin/grunt';

    public function __construct(
        private readonly Shell $shell
    ) {
    }

    public function runTasks(
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool {
        try {
            foreach (['clean', 'less'] as $task) {
                $shellOutput = $this->shell->execute(self::GRUNT_PATH . ' ' . $task . ' --quiet');
                if ($isVerbose) {
                    $output->writeln($shellOutput);
                    $io->success("'grunt $task' has been successfully executed.");
                }
            }
            return true;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return false;
        }
    }
}
