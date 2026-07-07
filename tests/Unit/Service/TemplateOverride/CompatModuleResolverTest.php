<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\ObjectManagerInterface;
use OpenForgeProject\MageForge\Service\TemplateOverride\CompatModuleResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CompatModuleResolverTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
    }

    public function testReturnsEmptyArrayWhenHyvaIsNotInstalled(): void
    {
        $this->objectManager->expects($this->never())->method('create');
        $resolver = new CompatModuleResolver($this->objectManager);

        $this->assertSame([], $resolver->getOriginalModules('Hyva_MagentoCatalogCompat'));
    }

    public function testReturnsEmptyArrayWhenRegistryClassDoesNotExist(): void
    {
        $this->objectManager->expects($this->never())->method('create');
        $resolver = new CompatModuleResolver($this->objectManager, 'NonExistent\\Registry\\ClassName');

        $this->assertSame([], $resolver->getOriginalModules('Some_Module'));
    }

    public function testMapsCompatModuleToOriginalModules(): void
    {
        $registry = new FakeCompatModuleRegistry([
            'Amasty_Label' => ['Hyva_AmastyLabelCompat'],
            'Magento_Catalog' => ['Hyva_MagentoCatalogCompat'],
        ]);
        $this->objectManager
            ->method('create')
            ->with(FakeCompatModuleRegistry::class)
            ->willReturn($registry);
        $resolver = new CompatModuleResolver($this->objectManager, FakeCompatModuleRegistry::class);

        $this->assertSame(['Amasty_Label'], $resolver->getOriginalModules('Hyva_AmastyLabelCompat'));
        $this->assertSame(['Magento_Catalog'], $resolver->getOriginalModules('Hyva_MagentoCatalogCompat'));
    }

    public function testReturnsAllOriginalModulesForSharedCompatModule(): void
    {
        $registry = new FakeCompatModuleRegistry([
            'Vendor_ModuleA' => ['Hyva_SharedCompat'],
            'Vendor_ModuleB' => ['Hyva_SharedCompat'],
        ]);
        $this->objectManager->method('create')->willReturn($registry);
        $resolver = new CompatModuleResolver($this->objectManager, FakeCompatModuleRegistry::class);

        $this->assertSame(['Vendor_ModuleA', 'Vendor_ModuleB'], $resolver->getOriginalModules('Hyva_SharedCompat'));
    }

    public function testReturnsEmptyArrayForUnknownModule(): void
    {
        $registry = new FakeCompatModuleRegistry(['Vendor_Module' => ['Hyva_VendorCompat']]);
        $this->objectManager->method('create')->willReturn($registry);
        $resolver = new CompatModuleResolver($this->objectManager, FakeCompatModuleRegistry::class);

        $this->assertSame([], $resolver->getOriginalModules('Vendor_Module'));
        $this->assertSame([], $resolver->getOriginalModules('Unrelated_Module'));
    }

    public function testReturnsEmptyArrayWhenRegistryLacksExpectedMethods(): void
    {
        $this->objectManager->method('create')->willReturn(new \stdClass());
        $resolver = new CompatModuleResolver($this->objectManager, \stdClass::class);

        $this->assertSame([], $resolver->getOriginalModules('Some_Module'));
    }
}
