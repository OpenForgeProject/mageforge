<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;

/**
 * Resolves the buildable ancestors of a theme
 */
class ThemeParentResolver
{
    /**
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param BuilderPool $builderPool
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly BuilderPool $builderPool,
    ) {
    }

    /**
     * Get the codes of all parent themes that can be built, topmost ancestor first.
     *
     * Ancestors without a registered path or a matching builder are skipped, as are
     * vendor (Composer) themes, which are not meant to be built directly.
     *
     * @param string $themeCode
     * @return array<string>
     */
    public function getBuildableParents(string $themeCode): array
    {
        $theme = null;
        foreach ($this->themeList->getAllThemes() as $candidate) {
            if ($candidate->getCode() === $themeCode) {
                $theme = $candidate;
                break;
            }
        }

        $parents = [];
        $visited = [$themeCode => true];
        $parent = $theme?->getParentTheme();

        while ($parent !== null) {
            $code = $parent->getCode();
            if (isset($visited[$code])) {
                break;
            }
            $visited[$code] = true;

            $path = $this->themePath->getPath($code);
            if ($path !== null && !$this->isVendorTheme($path) && $this->builderPool->getBuilder($path) !== null) {
                array_unshift($parents, $code);
            }

            $parent = $parent->getParentTheme();
        }

        return $parents;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isVendorTheme(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), '/vendor/');
    }
}
