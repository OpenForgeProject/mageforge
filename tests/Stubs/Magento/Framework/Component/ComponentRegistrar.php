<?php

declare(strict_types=1);

namespace Magento\Framework\Component;

/**
 * Stub class for Magento\Framework\Component\ComponentRegistrar
 */
class ComponentRegistrar
{
    public const THEME = 'theme';
    public const MODULE = 'module';

    /**
     * @param array<string, string> $registeredPaths
     */
    public static function register(string $type, string $componentName, string $componentPath): void
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getPaths(string $type): array
    {
        return [];
    }
}
