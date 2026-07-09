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

    public function testReturnsExpectedLabels(): void
    {
        $labels = array_column((new InspectorTheme())->toOptionArray(), 'label');

        $this->assertSame(['Dark', 'Light', 'Auto (System Preference)'], $labels);
    }
}
