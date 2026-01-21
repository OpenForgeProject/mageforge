<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory for InspectorHints decorator
 */
class InspectorHintsFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Create InspectorHints instance
     *
     * @param array $data
     * @return InspectorHints
     */
    public function create(array $data = []): InspectorHints
    {
        return $this->objectManager->create(InspectorHints::class, $data);
    }
}
