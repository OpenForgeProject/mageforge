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
            if ($isVerbose) {
                $io->text('Running grunt clean...');
                $output->writeln($this->shell->execute(self::GRUNT_PATH . ' clean'));
            } else {
                $this->shell->execute(self::GRUNT_PATH . ' clean --quiet');
            }

            if ($isVerbose) {
                $io->text('Running grunt less...');
                $output->writeln($this->shell->execute(self::GRUNT_PATH . ' less'));
            } else {
                $this->shell->execute(self::GRUNT_PATH . ' less --quiet');
            }

            if ($isVerbose) {
                $io->success('Grunt tasks completed successfully.');
            }
            return true;
        } catch (\Exception $e) {
            $io->error('Failed to run grunt tasks: ' . $e->getMessage());
            return false;
        }
    }
}
