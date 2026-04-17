<?php

declare(strict_types=1);

namespace Magento\Framework\Component;

/**
 * Stub interface for Magento\Framework\Component\ComponentRegistrarInterface
 */
interface ComponentRegistrarInterface
{
    /**
     * @return array<string, string>
     */
    public function getPaths(string $type): array;

    public function getPath(string $type, string $componentName): ?string;
}
