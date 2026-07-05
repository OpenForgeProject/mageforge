<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;

/**
 * Checks whether the current client is an allowed developer IP.
 *
 * Reimplements Magento_Developer's Helper\Data::isDevAllowed() using only magento/framework
 * classes, so MageForge does not need a hard dependency on the Magento_Developer module (and
 * its own test suite does not need it installed either) just for this single check.
 */
class DeveloperAccessChecker
{
    private const XML_PATH_DEV_ALLOW_IPS = 'dev/restrict/allow_ips';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param RemoteAddress $remoteAddress
     * @param Header $httpHeader
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RemoteAddress $remoteAddress,
        private readonly Header $httpHeader,
    ) {
    }

    /**
     * Check if the client remote address is an allowed developer IP.
     *
     * @param string|null $storeId
     * @return bool
     */
    public function isDevAllowed(?string $storeId = null): bool
    {
        $allowedIpsValue = $this->scopeConfig->getValue(
            self::XML_PATH_DEV_ALLOW_IPS,
            InspectorConfig::SCOPE_STORE,
            $storeId,
        );

        if (!is_string($allowedIpsValue) || $allowedIpsValue === '') {
            return true;
        }

        // getRemoteAddress() returns false when it cannot determine the client IP; the Elvis
        // operator normalizes that (and an empty string) to '' without a type-narrowing
        // comparison PHPStan would flag as impossible based on the (incomplete) upstream docblock.
        $remoteAddr = $this->remoteAddress->getRemoteAddress() ?: '';
        if ($remoteAddr === '') {
            return true;
        }

        $allowedIps = preg_split('#\s*,\s*#', $allowedIpsValue, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array($remoteAddr, $allowedIps, true)
            || in_array($this->httpHeader->getHttpHost(), $allowedIps, true);
    }
}
