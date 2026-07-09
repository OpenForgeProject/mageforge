<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use OpenForgeProject\MageForge\Model\Config\Source\ToolbarPosition;
use PHPUnit\Framework\TestCase;

class ToolbarPositionTest extends TestCase
{
    public function testImplementsOptionSourceInterface(): void
    {
        $this->assertInstanceOf(OptionSourceInterface::class, new ToolbarPosition());
    }

    public function testReturnsAllToolbarPositions(): void
    {
        $options = (new ToolbarPosition())->toOptionArray();

        $this->assertSame(
            ['bottom-left', 'bottom-right', 'top-left', 'top-right'],
            array_column($options, 'value'),
        );
    }

    public function testReturnsExpectedLabels(): void
    {
        $labels = array_column((new ToolbarPosition())->toOptionArray(), 'label');

        $this->assertSame(['Bottom Left', 'Bottom Right', 'Top Left', 'Top Right'], $labels);
    }
}
