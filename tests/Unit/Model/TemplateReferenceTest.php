<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model;

use OpenForgeProject\MageForge\Model\TemplateReference;
use PHPUnit\Framework\TestCase;

class TemplateReferenceTest extends TestCase
{
    public function testExposesModuleNameAndTemplatePath(): void
    {
        $reference = new TemplateReference('Magento_Catalog', 'product/view/details.phtml');

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testBuildsTemplateIdInModuleNotation(): void
    {
        $reference = new TemplateReference('Magento_Checkout', 'cart/item/default.phtml');

        $this->assertSame('Magento_Checkout::cart/item/default.phtml', $reference->getTemplateId());
    }
}
