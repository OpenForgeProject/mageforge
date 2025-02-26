<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Serialize\SerializerInterface;

class HyvaThemeDetector
{
    private const THEME_XML = 'theme.xml';
    private const TAILWIND_DIR = 'web/tailwind';
    private const COMPOSER_JSON = 'composer.json';

    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly ReadFactory $readFactory,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Multiple checks to determine if a theme is a Hyva theme
     */
    public function isHyvaTheme(string $themePath): bool
    {
        // normalize path
        $themePath = rtrim($themePath, '/');

        // First check for tailwind directory in theme folder
        if (!file_exists(filename: $themePath . '/' . self::TAILWIND_DIR)) {
            return false;
        }

        // Then check composer.json for Hyva module dependency
        if (file_exists($themePath . '/' . self::COMPOSER_JSON)) {
            $composerContent = file_get_contents($themePath . '/' . self::COMPOSER_JSON);
            if ($composerContent) {
                $composerJson = json_decode($composerContent, true);
                if (isset($composerJson['name']) && str_contains($composerJson['name'], 'hyva')) {
                    return true;
                }
            }
        }

        // check theme.xml for Hyva theme declaration
        if (file_exists($themePath . '/' . self::THEME_XML)) {
            $themeXmlContent = file_get_contents($themePath . '/' . self::THEME_XML);
            if ($themeXmlContent && str_contains($themeXmlContent, 'Hyva')) {
                return true;
            }
        }

        return false;
    }
}
