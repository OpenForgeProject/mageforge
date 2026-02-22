<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InspectorTheme implements OptionSourceInterface
{
    /**
     * Return available inspector themes as options.
     *
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'dark', 'label' => (string) __('Dark')],
            ['value' => 'light', 'label' => (string) __('Light')],
            ['value' => 'auto', 'label' => (string) __('Auto (System Preference)')],
        ];
    }
}
