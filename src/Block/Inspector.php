<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Block;

use Magento\Developer\Helper\Data as DevHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Block for MageForge Inspector
 *
 * Only renders inspector assets when in developer mode, enabled in config, and from allowed IP
 */
class Inspector extends Template
{
    private const XML_PATH_INSPECTOR_ENABLED = 'dev/mageforge_inspector/enabled';

    public function __construct(
        Context $context,
        private readonly State $state,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DevHelper $devHelper,
        array $data = []
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
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_INSPECTOR_ENABLED)) {
            return false;
        }

        // Check if current IP is allowed
        if (!$this->devHelper->isDevAllowed()) {
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
     * Get JS file URL
     *
     * @return string
     */
    public function getJsUrl(): string
    {
        return $this->getViewFileUrl('OpenForgeProject_MageForge::js/inspector.js');
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
