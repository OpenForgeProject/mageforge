<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory for InspectorHints decorator
 *
 * Defined manually so PHPStan can resolve the type without requiring generated code.
 * Magento respects manually created factory classes and will not overwrite them.
 */
class InspectorHintsFactory
{
    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    /**
     * Create a new InspectorHints instance
     *
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     * @return InspectorHints
     */
    public function create(array $data = []): InspectorHints
    {
        /** @var InspectorHints $instance */
        $instance = $this->objectManager->create(InspectorHints::class, $data);
        return $instance;
    }
}
