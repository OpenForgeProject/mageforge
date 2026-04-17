<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service\ThemeBuilder;

use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use PHPUnit\Framework\TestCase;

class BuilderPoolTest extends TestCase
{
    public function testGetBuilderReturnsFirstMatchingBuilder(): void
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('detect')->willReturn(true);

        $pool = new BuilderPool([$builder]);

        $result = $pool->getBuilder('/some/theme/path');

        $this->assertSame($builder, $result);
    }

    public function testGetBuilderReturnsNullWhenNoBuilderMatches(): void
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('detect')->willReturn(false);

        $pool = new BuilderPool([$builder]);

        $result = $pool->getBuilder('/some/theme/path');

        $this->assertNull($result);
    }

    public function testGetBuilderReturnsNullForEmptyPool(): void
    {
        $pool = new BuilderPool([]);

        $result = $pool->getBuilder('/some/theme/path');

        $this->assertNull($result);
    }

    public function testGetBuilderReturnsFirstMatchWhenMultipleBuildersMatch(): void
    {
        $firstBuilder = $this->createMock(BuilderInterface::class);
        $firstBuilder->method('detect')->willReturn(true);

        $secondBuilder = $this->createMock(BuilderInterface::class);
        $secondBuilder->method('detect')->willReturn(true);

        $pool = new BuilderPool([$firstBuilder, $secondBuilder]);

        $result = $pool->getBuilder('/some/theme/path');

        $this->assertSame($firstBuilder, $result);
        // Second builder's detect should never be called
        $secondBuilder->expects($this->never())->method('detect');
    }

    public function testGetBuilderSkipsNonMatchingBuilders(): void
    {
        $nonMatchingBuilder = $this->createMock(BuilderInterface::class);
        $nonMatchingBuilder->method('detect')->willReturn(false);

        $matchingBuilder = $this->createMock(BuilderInterface::class);
        $matchingBuilder->method('detect')->willReturn(true);

        $pool = new BuilderPool([$nonMatchingBuilder, $matchingBuilder]);

        $result = $pool->getBuilder('/some/theme/path');

        $this->assertSame($matchingBuilder, $result);
    }

    public function testGetBuildersReturnsAllRegisteredBuilders(): void
    {
        $builderA = $this->createMock(BuilderInterface::class);
        $builderB = $this->createMock(BuilderInterface::class);

        $pool = new BuilderPool([$builderA, $builderB]);

        $builders = $pool->getBuilders();

        $this->assertCount(2, $builders);
        $this->assertSame($builderA, $builders[0]);
        $this->assertSame($builderB, $builders[1]);
    }

    public function testGetBuildersReturnsEmptyArrayForEmptyPool(): void
    {
        $pool = new BuilderPool([]);

        $this->assertSame([], $pool->getBuilders());
    }

    public function testDetectIsCalledWithCorrectThemePath(): void
    {
        $themePath = '/var/www/magento/app/design/frontend/Vendor/Theme';

        $builder = $this->createMock(BuilderInterface::class);
        $builder->expects($this->once())
            ->method('detect')
            ->with($themePath)
            ->willReturn(false);

        $pool = new BuilderPool([$builder]);
        $pool->getBuilder($themePath);
    }
}
