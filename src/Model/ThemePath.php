<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;

class ThemePath
{
    /**
     * Constructor
     *
     * @param ThemeList $themeList
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ThemeCollection $themeCollection
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly ThemeCollection $themeCollection
    ) {
    }

    /**
     * Get the path of a theme
     *
     * @param string $themeCode
     * @return string|null
     */
    public function getPath(string $themeCode): ?string
    {
        // First try standard Magento theme path
        // $themes = $this->themeList->getAllThemes();
        // foreach ($themes as $theme) {
        //     if ($theme->getCode() === $themeCode) {
        //         return $theme->getFullPath();
        //     }
        // }

        // Then try registered themes via ComponentRegistrar
        $registeredThemes = $this->componentRegistrar->getPaths(ComponentRegistrar::THEME);
        foreach ($registeredThemes as $code => $path) {
            if (str_contains($code, $themeCode)) {
                return $path;
            }
        }

        return null;
    }
}
