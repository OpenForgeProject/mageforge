<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\TemplateEngine\Decorator;

use Magento\Framework\View\Element\BlockInterface;

/**
 * Minimal named class used to give module-name extraction a stable, real class name to parse.
 * Implements BlockInterface directly (rather than extending AbstractBlock) so it can be
 * instantiated without a layout/context.
 */
class FakeVendorModuleBlock2 implements BlockInterface
{
    public function toHtml()
    {
        return '';
    }
}
