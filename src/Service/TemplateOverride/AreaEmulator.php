<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Loads the dependency injection configuration of a given area into the CLI object manager
 *
 * CLI commands run without any area specific DI configuration. View file fallback plugins
 * (for example Hyvä's compat module fallback, registered in etc/frontend/di.xml) only take
 * effect after the area's DI configuration has been loaded.
 */
class AreaEmulator
{
    /**
     * @param State $appState
     * @param ConfigLoaderInterface $configLoader
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        private readonly State $appState,
        private readonly ConfigLoaderInterface $configLoader,
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    /**
     * Set the area code (if not set yet) and load the area's DI configuration
     *
     * @param string $areaCode
     * @return void
     */
    public function emulate(string $areaCode): void
    {
        try {
            $currentArea = $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $currentArea = null;
        }

        if ($currentArea === null) {
            $this->appState->setAreaCode($areaCode);
        }

        $this->objectManager->configure($this->configLoader->load($areaCode));
    }
}
