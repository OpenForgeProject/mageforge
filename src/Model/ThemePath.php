<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

class ThemePath
{
    /**
     * Constructor
     *
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemeList $themeList
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
        $themes = $this->themeList->getAllThemes();
        foreach ($themes as $path => $theme) {
            if ($theme->getCode() === $themeCode) {
                return $path;
            }
        }
        return null;
    }
}
