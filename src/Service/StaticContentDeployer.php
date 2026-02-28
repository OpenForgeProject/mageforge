<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\App\State;
use Magento\Framework\Shell;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StaticContentDeployer
{
    /**
     * @param Shell $shell
     * @param State $state
     */
    public function __construct(
        private readonly Shell $shell,
        private readonly State $state,
    ) {
    }

    /**
     * Deploy static content for a theme when not in developer mode.
     *
     * @param string $themeCode
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function deploy(string $themeCode, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        try {
            // Only deploy if not in developer mode
            if ($this->state->getMode() === State::MODE_DEVELOPER) {
                if ($isVerbose) {
                    $io->info('Skipping static content deployment in developer mode.');
                }
                return true;
            }

            if ($isVerbose) {
                $io->text('Deploying static content...');
            }

            $shellOutput = $this->shell->execute('php bin/magento setup:static-content:deploy -t %s -f --quiet', [
                $themeCode,
            ]);

            if ($isVerbose) {
                $output->writeln($shellOutput);
                $io->success(sprintf("Static content deployed for theme '%s'.", $themeCode));
            }

            return true;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return false;
        }
    }
}
