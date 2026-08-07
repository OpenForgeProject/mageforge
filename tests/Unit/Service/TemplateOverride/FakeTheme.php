<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\View\Design\ThemeInterface;

/**
 * Minimal ThemeInterface implementation for template override tests
 */
class FakeTheme implements ThemeInterface
{
    /**
     * Constructor.
     *
     * @param string $code
     * @param string $area
     */
    public function __construct(
        private readonly string $code,
        private readonly string $area = 'frontend',
    ) {
    }

    /**
     * Get area code.
     *
     * @return string
     */
    public function getArea()
    {
        return $this->area;
    }

    /**
     * Get theme path.
     *
     * @return string
     */
    public function getThemePath()
    {
        return $this->code;
    }

    /**
     * Get full theme path including area.
     *
     * @return string
     */
    public function getFullPath()
    {
        return $this->area . '/' . $this->code;
    }

    /**
     * Get parent theme.
     *
     * @return ThemeInterface|null
     */
    public function getParentTheme()
    {
        return null;
    }

    /**
     * Get theme code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Check whether theme is physical.
     *
     * @return bool
     */
    public function isPhysical()
    {
        return true;
    }

    /**
     * Get inherited themes.
     *
     * @return array<int, ThemeInterface>
     */
    public function getInheritedThemes()
    {
        return [];
    }

    /**
     * Get theme id.
     *
     * @return int
     */
    public function getId()
    {
        return 1;
    }
}
