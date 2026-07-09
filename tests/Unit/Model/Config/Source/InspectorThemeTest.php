<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use OpenForgeProject\MageForge\Model\Config\Source\InspectorTheme;
use PHPUnit\Framework\TestCase;

class InspectorThemeTest extends TestCase
{
    public function testImplementsOptionSourceInterface(): void
    {
        $this->assertInstanceOf(OptionSourceInterface::class, new InspectorTheme());
    }

    public function testReturnsAllInspectorThemes(): void
    {
        $options = (new InspectorTheme())->toOptionArray();

        $this->assertSame(['dark', 'light', 'auto'], array_column($options, 'value'));
    }

    public function testEveryOptionHasNonEmptyLabel(): void
    {
        foreach ((new InspectorTheme())->toOptionArray() as $option) {
            $this->assertArrayHasKey('label', $option);
            // Runtime guard: toOptionArray() is statically typed string, but assert it anyway.
            // @phpstan-ignore method.alreadyNarrowedType
            $this->assertIsString($option['label']);
            $this->assertNotSame('', $option['label']);
        }
    }
}
