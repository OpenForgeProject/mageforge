<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service\ThemeBuilder;

use InvalidArgumentException;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderFactory;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use PHPUnit\Framework\TestCase;

class BuilderFactoryTest extends TestCase
{
    private BuilderFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new BuilderFactory();
    }

    public function testAddBuilderRegistersBuilder(): void
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('TestBuilder');

        $this->factory->addBuilder($builder);

        $this->assertContains('TestBuilder', $this->factory->getAvailableBuilders());
    }

    public function testCreateReturnsCorrectBuilderByName(): void
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('HyvaThemes');

        $this->factory->addBuilder($builder);

        $result = $this->factory->create('HyvaThemes');

        $this->assertSame($builder, $result);
    }

    public function testCreateThrowsExceptionForUnknownBuilderType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Builder UnknownBuilder not found');

        $this->factory->create('UnknownBuilder');
    }

    public function testGetAvailableBuildersReturnsEmptyArrayWhenNoBuilders(): void
    {
        $this->assertSame([], $this->factory->getAvailableBuilders());
    }

    public function testGetAvailableBuildersReturnsAllRegisteredNames(): void
    {
        $builderA = $this->createMock(BuilderInterface::class);
        $builderA->method('getName')->willReturn('BuilderA');

        $builderB = $this->createMock(BuilderInterface::class);
        $builderB->method('getName')->willReturn('BuilderB');

        $this->factory->addBuilder($builderA);
        $this->factory->addBuilder($builderB);

        $available = $this->factory->getAvailableBuilders();

        $this->assertCount(2, $available);
        $this->assertContains('BuilderA', $available);
        $this->assertContains('BuilderB', $available);
    }

    public function testAddBuilderOverwritesBuilderWithSameName(): void
    {
        $firstBuilder = $this->createMock(BuilderInterface::class);
        $firstBuilder->method('getName')->willReturn('MyBuilder');

        $secondBuilder = $this->createMock(BuilderInterface::class);
        $secondBuilder->method('getName')->willReturn('MyBuilder');

        $this->factory->addBuilder($firstBuilder);
        $this->factory->addBuilder($secondBuilder);

        $result = $this->factory->create('MyBuilder');

        $this->assertSame($secondBuilder, $result);
        $this->assertCount(1, $this->factory->getAvailableBuilders());
    }

    public function testCreateThrowsExceptionWithBuilderNameInMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Builder SomeSpecificBuilder not found');

        $this->factory->create('SomeSpecificBuilder');
    }
}
