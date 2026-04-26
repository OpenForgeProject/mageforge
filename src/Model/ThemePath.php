<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;

class ThemePath
{
    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
    ) {
    }

    /**
     * Get the filesystem path for a theme code.
     *
     * @param string $themeCode
     * @return string|null
     */
    public function getPath(string $themeCode): ?string
    {
        $registeredThemes = $this->componentRegistrar->getPaths(ComponentRegistrar::THEME);

        return $registeredThemes['frontend/' . $themeCode]
            ?? $registeredThemes['adminhtml/' . $themeCode]
            ?? null;
    }
}
