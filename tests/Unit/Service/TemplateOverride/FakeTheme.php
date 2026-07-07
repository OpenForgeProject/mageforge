<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\View\Design\ThemeInterface;

/**
 * Minimal ThemeInterface implementation for template override tests
 */
class FakeTheme implements ThemeInterface
{
    public function __construct(
        private readonly string $code,
        private readonly string $area = 'frontend',
    ) {
    }

    public function getArea()
    {
        return $this->area;
    }

    public function getThemePath()
    {
        return $this->code;
    }

    public function getFullPath()
    {
        return $this->area . '/' . $this->code;
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
}
