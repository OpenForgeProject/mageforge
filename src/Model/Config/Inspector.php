<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\Config;

class Inspector
{
    public const XML_PATH_ENABLED = 'dev/mageforge_inspector/enabled';
    public const XML_PATH_SHOW_BUTTON_LABELS = 'mageforge/inspector/show_button_labels';
    public const XML_PATH_THEME = 'mageforge/inspector/theme';
    public const XML_PATH_POSITION = 'mageforge/inspector/position';
    public const DEFAULT_THEME = 'dark';
    public const DEFAULT_POSITION = 'bottom-left';

    /**
     * Store scope type.
     *
     * Equivalent to \Magento\Store\Model\ScopeInterface::SCOPE_STORE, duplicated here so
     * MageForge does not need a hard dependency on the Magento_Store module just for this
     * string constant.
     */
    public const SCOPE_STORE = 'store';
}
