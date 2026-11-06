<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Composer\InstalledVersions;

/**
 * Detects the installed Hyvä version from Composer
 *
 * The Hyvä default theme defines the version. The theme module is only used as a fallback, because it can be
 * updated independently of the default theme.
 */
class HyvaVersionDetector
{
    private const PACKAGES = ['hyva-themes/magento2-default-theme', 'hyva-themes/magento2-theme-module'];

    /**
     * Get the installed Hyvä version, e.g. "1.4.0"
     *
     * @return string|null Null if Hyvä is not installed via Composer or the version is not numeric (dev branches)
     */
    public function getVersion(): ?string
    {
        foreach (self::PACKAGES as $package) {
            $installed = $this->getInstalledVersion($package);
            if ($installed === null) {
                continue;
            }

            return preg_match('/^v?(\d+(?:\.\d+){0,2})/', $installed, $matches) === 1 ? $matches[1] : null;
        }

        return null;
    }

    /**
     * Get the installed version of a Composer package
     *
     * @param string $package
     * @return string|null
     */
    protected function getInstalledVersion(string $package): ?string
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($package);
    }
}
