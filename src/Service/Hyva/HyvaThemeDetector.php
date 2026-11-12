<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Decides whether a theme directory belongs to a Hyvä theme
 *
 * Shared by the Hyvä and TailwindCSS builders so that their detection is mutually exclusive
 * regardless of the order they are registered in the builder pool.
 */
class HyvaThemeDetector
{
    /**
     * @param File $fileDriver
     */
    public function __construct(
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Check theme.xml and composer.json for a Hyvä declaration
     *
     * @param string $themePath
     * @return bool
     */
    public function isHyvaTheme(string $themePath): bool
    {
        $themePath = rtrim($themePath, '/');

        if ($this->fileDriver->isExists($themePath . '/theme.xml')) {
            $themeXmlContent = $this->fileDriver->fileGetContents($themePath . '/theme.xml');
            if (stripos($themeXmlContent, 'hyva') !== false) {
                return true;
            }
        }

        if ($this->fileDriver->isExists($themePath . '/composer.json')) {
            $composerContent = $this->fileDriver->fileGetContents($themePath . '/composer.json');
            $composerJson = json_decode($composerContent, true);
            $packageName = is_array($composerJson) ? $composerJson['name'] ?? null : null;
            if (is_string($packageName) && str_contains($packageName, 'hyva')) {
                return true;
            }
        }

        return false;
    }
}
