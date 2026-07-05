<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\TemplateEngine\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\View\TemplateEngineFactory;
use Magento\Framework\View\TemplateEngineInterface;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHintsFactory;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;

/**
 * Plugin for the template engine factory to activate MageForge Inspector hints
 *
 * Only active in developer mode for allowed IPs when inspector is enabled in configuration
 */
class InspectorHints
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param DeveloperAccessChecker $developerAccessChecker
     * @param InspectorHintsFactory $inspectorHintsFactory
     * @param State $state
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DeveloperAccessChecker $developerAccessChecker,
        private readonly InspectorHintsFactory $inspectorHintsFactory,
        private readonly State $state,
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
        TemplateEngineInterface $invocationResult,
    ): TemplateEngineInterface {
        // Only activate in developer mode
        if ($this->state->getMode() !== State::MODE_DEVELOPER) {
            return $invocationResult;
        }

        // Check if inspector is enabled in configuration for the current scope
        $isEnabled = $this->scopeConfig->isSetFlag(InspectorConfig::XML_PATH_ENABLED, InspectorConfig::SCOPE_STORE);
        if (!$isEnabled) {
            return $invocationResult;
        }

        // Check if current IP is allowed
        if (!$this->developerAccessChecker->isDevAllowed()) {
            return $invocationResult;
        }

        // All checks passed - wrap with inspector decorator
        return $this->inspectorHintsFactory->create([
            'subject' => $invocationResult,
            'showBlockHints' => true,
        ]);
    }
}
