<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model;

use OpenForgeProject\MageForge\Model\TemplateReference;
use OpenForgeProject\MageForge\Model\TemplateType;
use PHPUnit\Framework\TestCase;

class TemplateReferenceTest extends TestCase
{
    public function testExposesModuleNameAndTemplatePath(): void
    {
        $reference = new TemplateReference('Magento_Catalog', 'product/view/details.phtml');

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
        $this->assertSame(TemplateType::TEMPLATE, $reference->getType());
    }

    public function testBuildsTemplateIdInModuleNotation(): void
    {
        $reference = new TemplateReference('Magento_Checkout', 'cart/item/default.phtml');

        $this->assertSame('Magento_Checkout::cart/item/default.phtml', $reference->getTemplateId());
    }

    public function testExposesTemplateType(): void
    {
        $reference = new TemplateReference(
            'Magento_Sales',
            'order/new.html',
            TemplateType::EMAIL,
        );

        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }
}
