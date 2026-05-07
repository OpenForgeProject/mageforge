<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ToolbarPosition implements OptionSourceInterface
{
    /**
     * Return available toolbar positions as options.
     *
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bottom-left',  'label' => (string) __('Bottom Left')],
            ['value' => 'bottom-right', 'label' => (string) __('Bottom Right')],
            ['value' => 'top-left',     'label' => (string) __('Top Left')],
            ['value' => 'top-right',    'label' => (string) __('Top Right')],
        ];
    }
}
