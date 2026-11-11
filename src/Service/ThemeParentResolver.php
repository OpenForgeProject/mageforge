<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use OpenForgeProject\MageForge\Model\ThemeAncestor;
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
        $parents = [];
        foreach ($this->getInheritanceChain($themeCode) as $ancestor) {
            if ($ancestor->skipReason === null) {
                array_unshift($parents, $ancestor->code);
            }
        }

        return $parents;
    }

    /**
     * Get all ancestors of a theme, direct parent first, with the reason a build is skipped (if any).
     *
     * @param string $themeCode
     * @return array<ThemeAncestor>
     */
    public function getInheritanceChain(string $themeCode): array
    {
        $theme = null;
        foreach ($this->themeList->getAllThemes() as $candidate) {
            if ($candidate->getCode() === $themeCode) {
                $theme = $candidate;
                break;
            }
        }

        $chain = [];
        $visited = [$themeCode => true];
        $parent = $theme?->getParentTheme();

        while ($parent !== null) {
            $code = $parent->getCode();
            if (isset($visited[$code])) {
                break;
            }
            $visited[$code] = true;

            $path = $this->themePath->getPath($code);
            $skipReason = match (true) {
                $path === null => 'not installed',
                $this->isVendorTheme($path) => 'vendor',
                $this->builderPool->getBuilder($path) === null => 'no builder',
                default => null,
            };
            $chain[] = new ThemeAncestor($code, $skipReason);

            $parent = $parent->getParentTheme();
        }

        return $chain;
    }

    /**
     * Check whether a theme path lies inside the Composer vendor directory.
     *
     * @param string $path
     * @return bool
     */
    private function isVendorTheme(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), '/vendor/');
    }
}
