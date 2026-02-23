<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service for cleaning symlinks in theme CSS directories
 *
 * In developer mode, symlinks in {theme}/web/css/ can cause issues during
 * the build process. This service detects and removes all symlinks before
 * the build starts to ensure a clean workflow.
 */
class SymlinkCleaner
{
    /**
     * @param File $fileDriver
     * @param State $state
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly State $state
    ) {
    }

    /**
     * Remove all symlinks from {theme}/web/css/ directory in developer mode
     *
     * @param string $themePath Path to theme directory
     * @param SymfonyStyle $io Symfony style output
     * @param bool $isVerbose Whether to show verbose output
     * @return bool True on success or if no action needed, false on error
     */
    public function cleanSymlinks(
        string $themePath,
        SymfonyStyle $io,
        bool $isVerbose
    ): bool {
        try {
            // Only clean symlinks in developer mode
            if ($this->state->getMode() !== State::MODE_DEVELOPER) {
                return true;
            }

            $cssPath = rtrim($themePath, '/') . '/web/css';

            // Nothing to clean if directory doesn't exist
            if (!$this->fileDriver->isDirectory($cssPath)) {
                return true;
            }

            $items = $this->fileDriver->readDirectory($cssPath);
            $deletedCount = 0;

            foreach ($items as $item) {
                // Check if item is a symlink
                if (is_link($item)) {
                    $this->fileDriver->deleteFile($item);
                    $deletedCount++;

                    if ($isVerbose) {
                        $io->writeln(sprintf(
                            '  <fg=yellow>⚠</> Removed symlink: %s',
                            basename($item)
                        ));
                    }
                }
            }

            if ($deletedCount > 0 && $isVerbose) {
                $io->success(sprintf(
                    'Removed %d symlink(s) from web/css/',
                    $deletedCount
                ));
            }

            return true;
        } catch (\Exception $e) {
            // Don't fail the build process if symlink cleanup fails
            // Just warn the user and continue
            if ($isVerbose) {
                $io->warning(sprintf(
                    'Could not clean symlinks: %s',
                    $e->getMessage()
                ));
            }
            return true;
        }
    }
}
