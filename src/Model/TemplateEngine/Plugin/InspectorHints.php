<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Plugin;

use Magento\Developer\Helper\Data as DevHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\TemplateEngineFactory;
use Magento\Framework\View\TemplateEngineInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHintsFactory;

/**
 * Plugin for the template engine factory to activate MageForge Inspector hints
 *
 * Only active in developer mode for allowed IPs when inspector is enabled in configuration
 */
class InspectorHints
{
    private const XML_PATH_INSPECTOR_ENABLED = 'dev/mageforge_inspector/enabled';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param DevHelper $devHelper
     * @param InspectorHintsFactory $inspectorHintsFactory
     * @param State $state
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly DevHelper $devHelper,
        private readonly InspectorHintsFactory $inspectorHintsFactory,
        private readonly State $state
    ) {
    }

    /**
     * Wrap template engine instance with the inspector hints decorator
     *
     * @param TemplateEngineFactory $subject
     * @param TemplateEngineInterface $invocationResult
     * @return TemplateEngineInterface
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterCreate(
        TemplateEngineFactory $subject,
        TemplateEngineInterface $invocationResult
    ): TemplateEngineInterface {
        // Only activate in developer mode
        if ($this->state->getMode() !== State::MODE_DEVELOPER) {
            return $invocationResult;
        }

        // Check if inspector is enabled in configuration
        $storeCode = $this->storeManager->getStore()->getCode();
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_INSPECTOR_ENABLED, ScopeInterface::SCOPE_STORE, $storeCode)) {
            return $invocationResult;
        }

        // Check if current IP is allowed
        if (!$this->devHelper->isDevAllowed()) {
            return $invocationResult;
        }

        // All checks passed - wrap with inspector decorator
        return $this->inspectorHintsFactory->create([
            'subject' => $invocationResult,
            'showBlockHints' => true,
        ]);
    }
}
