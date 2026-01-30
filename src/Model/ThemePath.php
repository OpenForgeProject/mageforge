<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;

class ThemePath
{
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar
    ) {
    }

    public function getPath(string $themeCode): ?string
    {
        $registeredThemes = $this->componentRegistrar->getPaths(ComponentRegistrar::THEME);
        foreach ($registeredThemes as $code => $path) {
            if (str_contains($code, $themeCode)) {
                return $path;
            }
        }

        return null;
    }
}
