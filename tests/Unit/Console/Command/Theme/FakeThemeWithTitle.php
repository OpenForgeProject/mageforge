<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use Magento\Framework\View\Design\ThemeInterface;

/**
 * Minimal ThemeInterface implementation exposing getThemeTitle(), as the real
 * Magento\Theme\Model\Theme class does (not available in this project's minimal
 * unit-test dependency set, which only requires magento/framework).
 */
class FakeThemeWithTitle implements ThemeInterface
{
    /**
     * @param string $code
     * @param string $themeTitle
     */
    public function __construct(
        private readonly string $code,
        private readonly string $themeTitle,
    ) {
    }

    /**
     * Returns the theme area.
     */
    public function getArea()
    {
        return 'frontend';
    }

    /**
     * Returns the theme path (its code).
     */
    public function getThemePath()
    {
        return $this->code;
    }

    /**
     * Returns the full theme path.
     */
    public function getFullPath()
    {
        return 'frontend/' . $this->code;
    }

    /**
     * Returns the parent theme (none).
     */
    public function getParentTheme()
    {
        return null;
    }

    /**
     * Returns the theme code.
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Returns whether the theme is physical.
     */
    public function isPhysical()
    {
        return true;
    }

    /**
     * Returns the inherited themes (none).
     */
    public function getInheritedThemes()
    {
        return [];
    }

    /**
     * Returns the theme id.
     */
    public function getId()
    {
        return 1;
    }

    /**
     * Returns the theme title.
     */
    public function getThemeTitle(): string
    {
        return $this->themeTitle;
    }
}
