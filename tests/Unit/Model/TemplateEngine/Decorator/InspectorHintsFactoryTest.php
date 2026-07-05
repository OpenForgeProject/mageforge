<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\TemplateEngine\Decorator;

use Magento\Framework\ObjectManagerInterface;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHintsFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InspectorHintsFactoryTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;
    private InspectorHintsFactory $factory;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->factory = new InspectorHintsFactory($this->objectManager);
    }

    public function testCreatePassesDataToObjectManager(): void
    {
        $instance = $this->createMock(InspectorHints::class);
        $data = ['subject' => 'foo', 'showBlockHints' => true];

        $this->objectManager->expects($this->once())
            ->method('create')
            ->with(InspectorHints::class, $data)
            ->willReturn($instance);

        $this->assertSame($instance, $this->factory->create($data));
    }

    public function testCreateDefaultsToEmptyData(): void
    {
        $instance = $this->createMock(InspectorHints::class);

        $this->objectManager->expects($this->once())
            ->method('create')
            ->with(InspectorHints::class, [])
            ->willReturn($instance);

        $this->assertSame($instance, $this->factory->create());
    }
}
