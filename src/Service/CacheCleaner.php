<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Shell;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheCleaner
{
    public function __construct(
        private readonly Shell $shell
    ) {
    }

    public function clean(SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            if ($isVerbose) {
                $io->text('Cleaning cache...');
            }

            $this->shell->execute(
                'bin/magento cache:clean full_page block_html layout translate'
            );

            if ($isVerbose) {
                $io->success('Cache cleaned successfully.');
            }

            return true;
        } catch (\Exception $e) {
            $io->error('Failed to clean cache: ' . $e->getMessage());
            return false;
        }
    }
}
