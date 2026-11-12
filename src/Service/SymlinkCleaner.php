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
        private readonly State $state,
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
    public function cleanSymlinks(string $themePath, SymfonyStyle $io, bool $isVerbose): bool
    {
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

            $realCssPath = $this->fileDriver->getRealPath($cssPath);
            if (!is_string($realCssPath)) {
                return true;
            }
            $realCssPath = rtrim($realCssPath, '/');

            $items = $this->fileDriver->readDirectory($cssPath);
            $deletedCount = 0;

            foreach ($items as $item) {
                // Check if item is a symlink
                if ($this->isSymlink($item, $realCssPath)) {
                    $this->fileDriver->deleteFile($item);
                    $deletedCount++;

                    if ($isVerbose) {
                        $io->writeln(sprintf('  <fg=yellow>⚠</> Removed symlink: %s', $this->getBasename($item)));
                    }
                }
            }

            if ($deletedCount > 0 && $isVerbose) {
                $io->success(sprintf('Removed %d symlink(s) from web/css/', $deletedCount));
            }

            return true;
        } catch (\Exception $e) {
            // Don't fail the build process if symlink cleanup fails
            // Just warn the user and continue
            if ($isVerbose) {
                $io->warning(sprintf('Could not clean symlinks: %s', $e->getMessage()));
            }
            return true;
        }
    }

    /**
     * Check if a directory entry is a symlink by comparing its resolved path with its expected location.
     *
     * The file driver offers no lstat(), and stat() follows links, so the file mode cannot be used.
     * An entry whose real path differs from "<real directory>/<name>" (or cannot be resolved,
     * i.e. a broken link) is a symlink.
     *
     * @param string $path
     * @param string $realDirectory Resolved path of the directory containing the entry
     * @return bool
     */
    private function isSymlink(string $path, string $realDirectory): bool
    {
        try {
            $realPath = $this->fileDriver->getRealPath($path);
        } catch (\Exception $e) {
            return false;
        }

        if (!is_string($realPath)) {
            return true;
        }

        return (
            $this->normalizeSeparators($realPath)
            !== $this->normalizeSeparators($realDirectory) . '/' . $this->getBasename($path)
        );
    }

    /**
     * Get basename without using basename().
     *
     * A backslash only separates path segments on Windows; on other platforms it is a valid filename character.
     *
     * @param string $path
     * @return string
     */
    private function getBasename(string $path): string
    {
        $trimmed = rtrim($this->normalizeSeparators($path), '/');
        $pos = strrpos($trimmed, '/');
        if ($pos === false) {
            return $trimmed;
        }
        return substr($trimmed, $pos + 1);
    }

    /**
     * Convert platform-specific directory separators to forward slashes
     *
     * @param string $path
     * @return string
     */
    private function normalizeSeparators(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('\\', '/', $path) : $path;
    }
}
