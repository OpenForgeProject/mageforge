<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Decorator;

use Magento\Framework\Math\Random;
use Magento\Framework\View\TemplateEngineInterface;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints;

/**
 * Factory for InspectorHints decorator
 */
class InspectorHintsFactory
{
    /**
     * @param Random $random
     */
    public function __construct(
        private readonly Random $random
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
        $subject = $data['subject'] ?? null;
        $showBlockHints = $data['showBlockHints'] ?? false;

        if (!$subject instanceof TemplateEngineInterface) {
            throw new \InvalidArgumentException(
                'Instance of "' . TemplateEngineInterface::class . '" is expected.'
            );
        }

        // Extract random generator to satisfy PHPStan (readonly property usage detection)
        $randomGenerator = $this->random;

        return new InspectorHints(
            $subject,
            (bool)$showBlockHints,
            $randomGenerator
        );
    }
}
