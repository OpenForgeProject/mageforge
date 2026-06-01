<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;

class InspectorHintsFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data = []): InspectorHints
    {
        /** @var InspectorHints $instance */
        $instance = $this->objectManager->create(InspectorHints::class, $data);
        return $instance;
    }
}
