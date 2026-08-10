<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model\Config;

use OpenForgeProject\MageForge\Model\Config\Inspector;
use PHPUnit\Framework\TestCase;

class InspectorTest extends TestCase
{
    public function testConfigPathConstants(): void
    {
        $this->assertSame('dev/mageforge_inspector/enabled', Inspector::XML_PATH_ENABLED);
        $this->assertSame('mageforge/inspector/show_button_labels', Inspector::XML_PATH_SHOW_BUTTON_LABELS);
        $this->assertSame('mageforge/inspector/theme', Inspector::XML_PATH_THEME);
        $this->assertSame('mageforge/inspector/position', Inspector::XML_PATH_POSITION);
        $this->assertSame(
            'mageforge/inspector/keyboard_shortcuts_enabled',
            Inspector::XML_PATH_KEYBOARD_SHORTCUTS_ENABLED,
        );
        $this->assertSame('mageforge/inspector/toolbar_shortcut', Inspector::XML_PATH_TOOLBAR_SHORTCUT);
        $this->assertSame(
            'mageforge/inspector/inspector_shortcut',
            Inspector::XML_PATH_INSPECTOR_SHORTCUT,
        );
    }

    public function testDefaultValueConstants(): void
    {
        $this->assertSame('dark', Inspector::DEFAULT_THEME);
        $this->assertSame('bottom-left', Inspector::DEFAULT_POSITION);
        $this->assertSame('Ctrl+Shift+A', Inspector::DEFAULT_TOOLBAR_SHORTCUT);
        $this->assertSame('Ctrl+Shift+I', Inspector::DEFAULT_INSPECTOR_SHORTCUT);
    }
}
