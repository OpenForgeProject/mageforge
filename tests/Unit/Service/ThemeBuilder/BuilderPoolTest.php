<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\ThemeBuilder;

use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BuilderPoolTest extends TestCase
{
    public function testReturnsFirstBuilderThatDetectsTheme(): void
    {
        $nonMatching = $this->createBuilder(detects: false);
        $matching = $this->createBuilder(detects: true);
        $neverAsked = $this->createMock(BuilderInterface::class);
        $neverAsked->expects($this->never())->method('detect');

        $pool = new BuilderPool([$nonMatching, $matching, $neverAsked]);

        $this->assertSame($matching, $pool->getBuilder('/path/to/theme'));
    }

    public function testReturnsNullWhenNoBuilderMatches(): void
    {
        $pool = new BuilderPool([$this->createBuilder(detects: false), $this->createBuilder(detects: false)]);

        $this->assertNull($pool->getBuilder('/path/to/theme'));
    }

    public function testReturnsNullForEmptyPool(): void
    {
        $pool = new BuilderPool();

        $this->assertNull($pool->getBuilder('/path/to/theme'));
    }

    public function testPassesThemePathToBuilders(): void
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->expects($this->once())->method('detect')->with('/expected/theme/path')->willReturn(true);

        (new BuilderPool([$builder]))->getBuilder('/expected/theme/path');
    }

    public function testReturnsAllRegisteredBuilders(): void
    {
        $builders = [$this->createBuilder(detects: false), $this->createBuilder(detects: true)];

        $this->assertSame($builders, (new BuilderPool($builders))->getBuilders());
        $this->assertSame([], (new BuilderPool())->getBuilders());
    }

    private function createBuilder(bool $detects): BuilderInterface&MockObject
    {
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('detect')->willReturn($detects);

        return $builder;
    }
}
