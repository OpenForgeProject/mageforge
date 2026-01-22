<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints;

/**
 * Factory for InspectorHints decorator
 */
class InspectorHintsFactory
{
    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Create InspectorHints instance
     *
     * @param array<string, mixed> $data
     * @return InspectorHints
     */
    public function create(array $data = []): InspectorHints
    {
        /** @var InspectorHints $instance */
        $instance = $this->objectManager->create(InspectorHints::class, $data);
        return $instance;
    }
}
