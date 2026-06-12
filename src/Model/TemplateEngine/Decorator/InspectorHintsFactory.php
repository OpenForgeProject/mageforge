<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;

/**
 * Factory for creating InspectorHints instances via the ObjectManager.
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

    // phpcs:disable Magento2.Annotation.MethodArguments.ArgumentMissing
    /**
     * Create a new InspectorHints instance.
     *
     * @param array<string, mixed> $data
     * @return InspectorHints
     */
    // phpcs:enable Magento2.Annotation.MethodArguments.ArgumentMissing
    public function create(array $data = []): InspectorHints
    {
        /** @var InspectorHints $instance */
        $instance = $this->objectManager->create(InspectorHints::class, $data);

        return $instance;
    }
}
