<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Enum\Inspector;

enum XmlPath: string
{
    case Enabled = 'dev/mageforge_inspector/enabled';
    case ShowButtonLabels = 'mageforge/inspector/show_button_labels';
    case Theme = 'mageforge/inspector/theme';
    case Position = 'mageforge/inspector/position';
}
