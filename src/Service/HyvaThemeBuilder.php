<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Shell;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Filesystem\Driver\File;

class HyvaThemeBuilder
{
    private const TAILWIND_DIR = 'web/tailwind';
    private const NODE_MODULES = 'node_modules';

    public function __construct(
        private readonly Shell $shell,
        private readonly File $fileDriver
    ) {
    }

    public function build(
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool {
        try {
            $tailwindPath = rtrim($themePath, '/') . '/' . self::TAILWIND_DIR;

            if (!$this->fileDriver->isDirectory($tailwindPath)) {
                $io->error("Tailwind directory not found in: $tailwindPath");
                return false;
            }

            // Change to tailwind directory
            $currentDir = getcwd();
            chdir($tailwindPath);

            if ($isVerbose) {
                $io->section("Building Hyvä theme in: $tailwindPath");
            }

            // Remove node_modules if exists
            if ($this->fileDriver->isDirectory(self::NODE_MODULES)) {
                if ($isVerbose) {
                    $io->text('Removing node_modules directory...');
                }
                $this->fileDriver->deleteDirectory(self::NODE_MODULES);
            }

            // Run npm ci
            if ($isVerbose) {
                $io->text('Running npm ci...');
            }
            $this->shell->execute('npm ci --quiet');

            // Run npm run build
            if ($isVerbose) {
                $io->text('Running npm run build...');
            }
            $this->shell->execute('npm run build --quiet');

            // Change back to original directory
            chdir($currentDir);

            if ($isVerbose) {
                $io->success('Hyvä theme build completed successfully.');
            }

            return true;
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            // Make sure we change back to original directory even if an error occurs
            if (isset($currentDir)) {
                chdir($currentDir);
            }

            return false;
        }
    }
}
