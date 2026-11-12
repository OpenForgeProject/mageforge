<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHintsFactory;
use PHPUnit\Framework\TestCase;

class InspectorHintsFactoryTest extends TestCase
{
    public function testCreatePassesDataToObjectManager(): void
    {
        $instance = $this->createStub(InspectorHints::class);
        $data = ['subject' => 'foo', 'showBlockHints' => true];

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('create')
            ->with(InspectorHints::class, $data)
            ->willReturn($instance);

        $factory = new InspectorHintsFactory($objectManager);

        $this->assertSame($instance, $factory->create($data));
    }

    public function testCreateDefaultsToEmptyData(): void
    {
        $instance = $this->createStub(InspectorHints::class);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('create')
            ->with(InspectorHints::class, [])
            ->willReturn($instance);

        $factory = new InspectorHintsFactory($objectManager);

        $this->assertSame($instance, $factory->create());
    }
}
