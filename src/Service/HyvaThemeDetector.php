<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Serialize\SerializerInterface;

class HyvaThemeDetector
{
    private const HYVA_MODULE = 'Hyva_Theme';
    private const THEME_XML = 'theme.xml';

    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly ReadFactory $readFactory,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function isHyvaTheme(string $themePath): bool
    {
        try {
            $themeDir = $this->readFactory->create($themePath);

            if (!$themeDir->isExist(self::THEME_XML)) {
                return false;
            }

            $themeXmlContent = $themeDir->readFile(self::THEME_XML);

            // Check if Hyva is mentioned in theme.xml
            if (str_contains($themeXmlContent, needle: self::HYVA_MODULE)) {
                return true;
            }

            // Check if composer.json exists and has Hyva dependency
            if ($themeDir->isExist('composer.json')) {
                $composerJson = $this->serializer->unserialize(
                    $themeDir->readFile('composer.json')
                );

                if (isset($composerJson['require'][strtolower(self::HYVA_MODULE)])) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
