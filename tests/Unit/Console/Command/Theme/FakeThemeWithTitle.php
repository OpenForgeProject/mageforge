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
    public function __construct(
        private readonly string $code,
        private readonly string $themeTitle,
    ) {
    }

    public function getArea()
    {
        return 'frontend';
    }

    public function getThemePath()
    {
        return $this->code;
    }

    public function getFullPath()
    {
        return 'frontend/' . $this->code;
    }

    public function getParentTheme()
    {
        return null;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function isPhysical()
    {
        return true;
    }

    public function getInheritedThemes()
    {
        return [];
    }

    public function getId()
    {
        return 1;
    }

    public function getThemeTitle(): string
    {
        return $this->themeTitle;
    }
}
