<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

/**
 * Stand-in for Hyvä's CompatModuleRegistry, which is not available in the unit test
 * dependency set (Hyvä is an optional dependency resolved dynamically at runtime).
 */
class FakeCompatModuleRegistry
{
    /**
     * @param array<string,array<int,string>> $origToCompatModules
     */
    public function __construct(
        private readonly array $origToCompatModules = [],
    ) {
    }

    /**
     * Get all original module names with registered compat modules
     *
     * @return array<int,string>
     */
    public function getOrigModules(): array
    {
        return array_keys($this->origToCompatModules);
    }

    /**
     * Get the compat modules registered for an original module
     *
     * @param string $originalModule
     * @return array<int,string>
     */
    public function getCompatModulesFor(string $originalModule): array
    {
        return $this->origToCompatModules[$originalModule] ?? [];
    }
}
