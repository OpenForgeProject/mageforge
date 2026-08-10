<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\Config;

class Inspector
{
    public const XML_PATH_ENABLED = 'dev/mageforge_inspector/enabled';
    public const XML_PATH_SHOW_BUTTON_LABELS = 'mageforge/inspector/show_button_labels';
    public const XML_PATH_THEME = 'mageforge/inspector/theme';
    public const XML_PATH_POSITION = 'mageforge/inspector/position';
    public const XML_PATH_KEYBOARD_SHORTCUTS_ENABLED = 'mageforge/inspector/keyboard_shortcuts_enabled';
    public const XML_PATH_TOOLBAR_SHORTCUT = 'mageforge/inspector/toolbar_shortcut';
    public const XML_PATH_INSPECTOR_SHORTCUT = 'mageforge/inspector/inspector_shortcut';
    public const DEFAULT_THEME = 'dark';
    public const DEFAULT_POSITION = 'bottom-left';
    public const DEFAULT_TOOLBAR_SHORTCUT = 'Ctrl+Shift+A';
    public const DEFAULT_INSPECTOR_SHORTCUT = 'Ctrl+Shift+I';

    /**
     * Store scope type.
     *
     * Equivalent to \Magento\Store\Model\ScopeInterface::SCOPE_STORE, duplicated here so
     * MageForge does not need a hard dependency on the Magento_Store module just for this
     * string constant.
     */
    public const SCOPE_STORE = 'store';
}
