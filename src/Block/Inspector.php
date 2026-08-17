<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;

/**
 * Block for MageForge Inspector
 *
 * Only renders inspector assets when in developer mode, enabled in config, and from allowed IP
 */
class Inspector extends Template
{
    /**
     * @param Context $context
     * @param State $state
     * @param ScopeConfigInterface $scopeConfig
     * @param DeveloperAccessChecker $developerAccessChecker
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly State $state,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DeveloperAccessChecker $developerAccessChecker,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if inspector should be rendered
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        // Check developer mode
        if ($this->state->getMode() !== State::MODE_DEVELOPER) {
            return false;
        }

        // Check if inspector is enabled in configuration
        if (!$this->scopeConfig->isSetFlag(InspectorConfig::XML_PATH_ENABLED, InspectorConfig::SCOPE_STORE)) {
            return false;
        }

        // Check if current IP is allowed
        if (!$this->developerAccessChecker->isDevAllowed()) {
            return false;
        }

        return true;
    }

    /**
     * Get CSS file URL
     *
     * @return string
     */
    public function getCssUrl(): string
    {
        return $this->getViewFileUrl('OpenForgeProject_MageForge::css/inspector.css');
    }

    /**
     * Get Toolbar CSS file URL
     *
     * @return string
     */
    public function getToolbarCssUrl(): string
    {
        return $this->getViewFileUrl('OpenForgeProject_MageForge::css/toolbar.css');
    }

    /**
     * Get JS file URL
     *
     * @return string
     */
    public function getJsUrl(): string
    {
        return $this->getViewFileUrl('OpenForgeProject_MageForge::js/inspector.js');
    }

    /**
     * Get Toolbar JS file URL
     *
     * @return string
     */
    public function getToolbarJsUrl(): string
    {
        return $this->getViewFileUrl('OpenForgeProject_MageForge::js/toolbar.js');
    }

    /**
     * Get local Alpine.js fallback URL
     *
     * @return string
     */
    public function getAlpineJsUrl(): string
    {
        return $this->getViewFileUrl('OpenForgeProject_MageForge::js/lib/alpine.min.js');
    }

    /**
     * Whether button labels should be displayed in the toolbar
     *
     * @return bool
     */
    public function getShowButtonLabels(): bool
    {
        $value = $this->scopeConfig->getValue(
            InspectorConfig::XML_PATH_SHOW_BUTTON_LABELS,
            InspectorConfig::SCOPE_STORE,
        );
        // Default to true when not explicitly set to '0'
        return !is_string($value) || $value !== '0';
    }

    /**
     * Get configured theme
     *
     * @return string
     */
    public function getTheme(): string
    {
        $value = $this->scopeConfig->getValue(InspectorConfig::XML_PATH_THEME, InspectorConfig::SCOPE_STORE);
        return is_string($value) && $value !== '' ? $value : InspectorConfig::DEFAULT_THEME;
    }

    /**
     * Get configured toolbar position
     *
     * @return string
     */
    public function getPosition(): string
    {
        $value = $this->scopeConfig->getValue(InspectorConfig::XML_PATH_POSITION, InspectorConfig::SCOPE_STORE);
        return is_string($value) && $value !== '' ? $value : InspectorConfig::DEFAULT_POSITION;
    }

    /**
     * Whether keyboard shortcuts are enabled for toolbar and inspector
     *
     * @return bool
     */
    public function getKeyboardShortcutsEnabled(): bool
    {
        $value = $this->scopeConfig->getValue(
            InspectorConfig::XML_PATH_KEYBOARD_SHORTCUTS_ENABLED,
            InspectorConfig::SCOPE_STORE,
        );
        // Default to true when not explicitly set to '0'
        return !is_string($value) || $value !== '0';
    }

    /**
     * Get configured toolbar keyboard shortcut
     *
     * @return string
     */
    public function getToolbarShortcut(): string
    {
        $value = $this->scopeConfig->getValue(InspectorConfig::XML_PATH_TOOLBAR_SHORTCUT, InspectorConfig::SCOPE_STORE);
        return is_string($value) && $value !== '' ? $value : InspectorConfig::DEFAULT_TOOLBAR_SHORTCUT;
    }

    /**
     * Get configured inspector keyboard shortcut
     *
     * @return string
     */
    public function getInspectorShortcut(): string
    {
        $value = $this->scopeConfig->getValue(
            InspectorConfig::XML_PATH_INSPECTOR_SHORTCUT,
            InspectorConfig::SCOPE_STORE,
        );
        return is_string($value) && $value !== '' ? $value : InspectorConfig::DEFAULT_INSPECTOR_SHORTCUT;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }

        return parent::_toHtml();
    }
}
