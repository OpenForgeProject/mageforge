<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Shell;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

class StaticContentDeployer
{
    public function __construct(
        private readonly Shell $shell
    ) {
    }

    public function deploy(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool {
        try {
            $sanitizedThemeCode = escapeshellarg($themeCode);
            $shellOutput = $this->shell->execute(
                "php bin/magento setup:static-content:deploy -t %s -f -q",
                [$sanitizedThemeCode]
            );

            if ($isVerbose) {
                $output->writeln($shellOutput);
                $io->success(sprintf(
                    "'magento setup:static-content:deploy -t %s -f' has been successfully executed.",
                    $sanitizedThemeCode
                ));
            }

            return true;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return false;
        }
    }
}
