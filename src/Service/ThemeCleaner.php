<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service for cleaning theme-related directories and cache
 *
 * Provides reusable methods for cleaning static content, preprocessed files,
 * cache directories, and generated code related to themes.
 */
class ThemeCleaner
{
    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Clean var/view_preprocessed directory for the theme
     *
     * @param string $themeCode Theme code (e.g., "Magento/luma")
     * @param SymfonyStyle $io Symfony style output
     * @param bool $dryRun If true, only show what would be cleaned
     * @param bool $isVerbose Whether to show verbose output
     * @return int Number of directories cleaned
     */
    public function cleanViewPreprocessed(
        string $themeCode,
        SymfonyStyle $io,
        bool $dryRun = false,
        bool $isVerbose = false
    ): int {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        $themeParts = $this->parseThemeName($themeCode);
        if ($themeParts === null) {
            return 0;
        }

        [$vendor, $theme] = $themeParts;

        if (!$varDirectory->isDirectory('view_preprocessed')) {
            return 0;
        }

        $pathsToClean = [
            sprintf('view_preprocessed/css/frontend/%s/%s', $vendor, $theme),
            sprintf('view_preprocessed/source/frontend/%s/%s', $vendor, $theme),
        ];

        foreach ($pathsToClean as $path) {
            if ($varDirectory->isDirectory($path)) {
                try {
                    if (!$dryRun) {
                        $varDirectory->delete($path);
                    }
                    if ($isVerbose) {
                        $action = $dryRun ? 'Would clean' : 'Cleaned';
                        $io->writeln(sprintf('  <fg=green>✓</> %s: var/%s', $action, $path));
                    }
                    $cleaned++;
                } catch (\Exception $e) {
                    if ($isVerbose) {
                        $io->writeln(sprintf(
                            '  <fg=red>✗</> Failed to clean: var/%s - %s',
                            $path,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean pub/static directory for the theme
     *
     * @param string $themeCode Theme code (e.g., "Magento/luma")
     * @param SymfonyStyle $io Symfony style output
     * @param bool $dryRun If true, only show what would be cleaned
     * @param bool $isVerbose Whether to show verbose output
     * @return int Number of directories cleaned
     */
    public function cleanPubStatic(
        string $themeCode,
        SymfonyStyle $io,
        bool $dryRun = false,
        bool $isVerbose = false
    ): int {
        $cleaned = 0;
        $staticDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);

        $themeParts = $this->parseThemeName($themeCode);
        if ($themeParts === null) {
            return 0;
        }

        [$vendor, $theme] = $themeParts;

        if (!$staticDirectory->isDirectory('frontend')) {
            return 0;
        }

        $pathToClean = sprintf('frontend/%s/%s', $vendor, $theme);

        if ($staticDirectory->isDirectory($pathToClean)) {
            try {
                if (!$dryRun) {
                    $staticDirectory->delete($pathToClean);
                }
                if ($isVerbose) {
                    $action = $dryRun ? 'Would clean' : 'Cleaned';
                    $io->writeln(sprintf('  <fg=green>✓</> %s: pub/static/%s', $action, $pathToClean));
                }
                $cleaned++;
            } catch (\Exception $e) {
                if ($isVerbose) {
                    $io->writeln(sprintf(
                        '  <fg=red>✗</> Failed to clean: pub/static/%s - %s',
                        $pathToClean,
                        $e->getMessage()
                    ));
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean var/page_cache directory
     *
     * @param SymfonyStyle $io Symfony style output
     * @param bool $dryRun If true, only show what would be cleaned
     * @param bool $isVerbose Whether to show verbose output
     * @return int Number of directories cleaned
     */
    public function cleanPageCache(
        SymfonyStyle $io,
        bool $dryRun = false,
        bool $isVerbose = false
    ): int {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if ($varDirectory->isDirectory('page_cache')) {
            try {
                if (!$dryRun) {
                    $varDirectory->delete('page_cache');
                }
                if ($isVerbose) {
                    $action = $dryRun ? 'Would clean' : 'Cleaned';
                    $io->writeln(sprintf('  <fg=green>✓</> %s: var/page_cache', $action));
                }
                $cleaned++;
            } catch (\Exception $e) {
                if ($isVerbose) {
                    $io->writeln(sprintf(
                        '  <fg=red>✗</> Failed to clean: var/page_cache - %s',
                        $e->getMessage()
                    ));
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean var/tmp directory
     *
     * @param SymfonyStyle $io Symfony style output
     * @param bool $dryRun If true, only show what would be cleaned
     * @param bool $isVerbose Whether to show verbose output
     * @return int Number of directories cleaned
     */
    public function cleanVarTmp(
        SymfonyStyle $io,
        bool $dryRun = false,
        bool $isVerbose = false
    ): int {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if ($varDirectory->isDirectory('tmp')) {
            try {
                if (!$dryRun) {
                    $varDirectory->delete('tmp');
                }
                if ($isVerbose) {
                    $action = $dryRun ? 'Would clean' : 'Cleaned';
                    $io->writeln(sprintf('  <fg=green>✓</> %s: var/tmp', $action));
                }
                $cleaned++;
            } catch (\Exception $e) {
                if ($isVerbose) {
                    $io->writeln(sprintf(
                        '  <fg=red>✗</> Failed to clean: var/tmp - %s',
                        $e->getMessage()
                    ));
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean generated directory
     *
     * @param SymfonyStyle $io Symfony style output
     * @param bool $dryRun If true, only show what would be cleaned
     * @param bool $isVerbose Whether to show verbose output
     * @return int Number of directories cleaned
     */
    public function cleanGenerated(
        SymfonyStyle $io,
        bool $dryRun = false,
        bool $isVerbose = false
    ): int {
        $cleaned = 0;
        $generatedDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::GENERATED);

        try {
            $deletedCount = 0;

            if ($generatedDirectory->isDirectory('code')) {
                try {
                    if (!$dryRun) {
                        $generatedDirectory->delete('code');
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    if ($isVerbose) {
                        $io->writeln(sprintf(
                            '  <fg=red>✗</> Failed to clean: generated/code - %s',
                            $e->getMessage()
                        ));
                    }
                }
            }

            if ($generatedDirectory->isDirectory('metadata')) {
                try {
                    if (!$dryRun) {
                        $generatedDirectory->delete('metadata');
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    if ($isVerbose) {
                        $io->writeln(sprintf(
                            '  <fg=red>✗</> Failed to clean: generated/metadata - %s',
                            $e->getMessage()
                        ));
                    }
                }
            }

            if ($deletedCount > 0) {
                if ($isVerbose) {
                    $action = $dryRun ? 'Would clean' : 'Cleaned';
                    $io->writeln(sprintf('  <fg=green>✓</> %s: generated/*', $action));
                }
                $cleaned++;
            }
        } catch (\Exception $e) {
            if ($isVerbose) {
                $io->writeln(sprintf(
                    '  <fg=red>✗</> Failed to clean: generated - %s',
                    $e->getMessage()
                ));
            }
        }

        return $cleaned;
    }

    /**
     * Check if static files exist for the theme in pub/static
     *
     * @param string $themeCode Theme code (e.g., "Magento/luma")
     * @return bool True if static files exist
     */
    public function hasStaticFiles(string $themeCode): bool
    {
        $themeParts = $this->parseThemeName($themeCode);
        if ($themeParts === null) {
            return false;
        }

        [$vendor, $theme] = $themeParts;
        $staticDirectory = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);

        $themePath = sprintf('frontend/%s/%s', $vendor, $theme);
        return $staticDirectory->isDirectory($themePath);
    }

    /**
     * Parse theme name into vendor and theme parts
     *
     * @param string $themeCode Theme code (e.g., "Magento/luma")
     * @return array{0: string, 1: string}|null Array with [vendor, theme] or null if invalid
     */
    private function parseThemeName(string $themeCode): ?array
    {
        $themeParts = explode('/', $themeCode);
        if (count($themeParts) !== 2) {
            return null;
        }

        return $themeParts;
    }
}
