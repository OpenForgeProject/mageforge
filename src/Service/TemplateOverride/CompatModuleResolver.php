<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\ObjectManagerInterface;

/**
 * Maps Hyvä compatibility modules to the original modules they provide templates for
 *
 * Hyvä compat modules register themselves in Hyvä's CompatModuleRegistry (frontend area DI).
 * Because Hyvä is an optional dependency, its registry class is looked up dynamically and an
 * empty result is returned when it is not installed. The frontend area DI configuration must
 * be loaded before using this service (see AreaEmulator), otherwise the registry is empty.
 */
class CompatModuleResolver
{
    // phpcs:ignore Magento2.PHP.LiteralNamespaces.LiteralClassUsage -- Hyvä is an optional dependency
    private const HYVA_COMPAT_REGISTRY = 'Hyva\CompatModuleFallback\Model\CompatModuleRegistry';

    /**
     * @param ObjectManagerInterface $objectManager
     * @param string|null $registryClassName Overridable for testing; defaults to Hyvä's registry
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly ?string $registryClassName = null,
    ) {
    }

    /**
     * Get the original module(s) a Hyvä compatibility module provides templates for
     *
     * Returns an empty array if Hyvä's compat module fallback is not installed or the given
     * module is not registered as a compatibility module.
     *
     * @param string $moduleName
     * @return array<int, string>
     */
    public function getOriginalModules(string $moduleName): array
    {
        $map = $this->getCompatToOriginalMap();

        return $map[$moduleName] ?? [];
    }

    /**
     * Build the map of compat module => original modules from Hyvä's registry
     *
     * @return array<string, array<int, string>>
     */
    private function getCompatToOriginalMap(): array
    {
        $registryClass = $this->registryClassName ?? self::HYVA_COMPAT_REGISTRY;
        if (!class_exists($registryClass)) {
            return [];
        }

        $registry = $this->objectManager->create($registryClass);
        if (!is_object($registry)) {
            return [];
        }
        if (!method_exists($registry, 'getOrigModules') || !method_exists($registry, 'getCompatModulesFor')) {
            return [];
        }

        $originalModules = $registry->getOrigModules();
        if (!is_array($originalModules)) {
            return [];
        }

        $map = [];
        foreach ($originalModules as $originalModule) {
            if (!is_string($originalModule)) {
                continue;
            }
            $compatModules = $registry->getCompatModulesFor($originalModule);
            if (!is_array($compatModules)) {
                continue;
            }
            foreach ($compatModules as $compatModule) {
                if (is_string($compatModule)) {
                    $map[$compatModule][] = $originalModule;
                }
            }
        }

        return $map;
    }
}
