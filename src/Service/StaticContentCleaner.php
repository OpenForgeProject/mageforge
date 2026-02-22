<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\App\State;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service for automatically cleaning static content before theme builds
 *
 * In developer mode, Magento generates static files on-demand which can
 * conflict with theme builder outputs. This service automatically detects
 * and cleans such files before build/watch operations.
 */
class StaticContentCleaner
{
    /**
     * @param State $state
     * @param ThemeCleaner $themeCleaner
     */
    public function __construct(
        private readonly State $state,
        private readonly ThemeCleaner $themeCleaner
    ) {
    }

    /**
     * Clean static content for a theme if in developer mode and files exist
     *
     * @param string $themeCode Theme code (e.g., "Magento/luma")
     * @param SymfonyStyle $io Symfony style output
     * @param OutputInterface $output Console output interface
     * @param bool $isVerbose Whether to show verbose output
     * @return bool True if cleaning was performed or not needed, false on error
     */
    public function cleanIfNeeded(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool {
        try {
            // Only clean in developer mode
            if ($this->state->getMode() !== State::MODE_DEVELOPER) {
                return true;
            }

            // Check if static files exist for this theme
            if (!$this->themeCleaner->hasStaticFiles($themeCode)) {
                return true;
            }

            // Notify user
            if ($isVerbose) {
                $io->note(sprintf(
                    "Developer mode detected: Cleaning existing static files for theme '%s'...",
                    $themeCode
                ));
            }

            // Clean the static files and preprocessed views using ThemeCleaner
            $cleanedStatic = $this->themeCleaner->cleanPubStatic($themeCode, $io, false, $isVerbose);
            $cleanedPreprocessed = $this->themeCleaner->cleanViewPreprocessed($themeCode, $io, false, $isVerbose);

            return ($cleanedStatic > 0 || $cleanedPreprocessed > 0);
        } catch (\Exception $e) {
            $io->error('Failed to check/clean static content: ' . $e->getMessage());
            return false;
        }
    }
}
