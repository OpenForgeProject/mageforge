<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder;

use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderFactory;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BuilderFactoryTest extends TestCase
{
    private BuilderFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new BuilderFactory();
    }

    public function testCreateReturnsRegisteredBuilder(): void
    {
        $builder = $this->createNamedBuilder('tailwind');
        $this->factory->addBuilder($builder);

        $this->assertSame($builder, $this->factory->create('tailwind'));
    }

    public function testCreateThrowsForUnknownBuilder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Builder unknown not found');

        $this->factory->create('unknown');
    }

    public function testAddBuilderOverwritesExistingName(): void
    {
        $first = $this->createNamedBuilder('grunt');
        $second = $this->createNamedBuilder('grunt');
        $this->factory->addBuilder($first);
        $this->factory->addBuilder($second);

        $this->assertSame($second, $this->factory->create('grunt'));
        $this->assertSame(['grunt'], $this->factory->getAvailableBuilders());
    }

    public function testGetAvailableBuildersListsNamesInRegistrationOrder(): void
    {
        $this->factory->addBuilder($this->createNamedBuilder('grunt'));
        $this->factory->addBuilder($this->createNamedBuilder('tailwind'));

        $this->assertSame(['grunt', 'tailwind'], $this->factory->getAvailableBuilders());
    }

    public function testGetAvailableBuildersIsEmptyByDefault(): void
    {
        $this->assertSame([], $this->factory->getAvailableBuilders());
    }

    private function createNamedBuilder(string $name): BuilderInterface&MockObject
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn($name);

        return $builder;
    }
}
