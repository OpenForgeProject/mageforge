<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DeveloperAccessCheckerTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&Stub
     */
    private $scopeConfig;
    /**
     * @var RemoteAddress&Stub
     */
    private $remoteAddress;
    /**
     * @var Header&Stub
     */
    private $httpHeader;
    /**
     * @var DeveloperAccessChecker
     */
    private DeveloperAccessChecker $checker;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $this->remoteAddress = $this->createStub(RemoteAddress::class);
        $this->httpHeader = $this->createStub(Header::class);

        $this->checker = $this->createChecker();
    }

    public function testAllowsAccessWhenNoAllowedIpsAreConfigured(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->expects($this->never())->method('getRemoteAddress');

        $this->assertTrue($this->createChecker(remoteAddress: $remoteAddress)->isDevAllowed());
    }

    public function testAllowsAccessWhenConfiguredValueIsNotAString(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertTrue($this->checker->isDevAllowed());
    }

    public function testAllowsAccessWhenRemoteAddressCannotBeDetermined(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('127.0.0.1');
        $this->remoteAddress->method('getRemoteAddress')->willReturn(false);

        $this->assertTrue($this->checker->isDevAllowed());
    }

    public function testAllowsMatchingRemoteAddress(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('10.0.0.1, 10.0.0.2');
        $this->remoteAddress->method('getRemoteAddress')->willReturn('10.0.0.2');

        $this->assertTrue($this->checker->isDevAllowed());
    }

    public function testAllowsMatchingHttpHostWhenRemoteAddressDoesNotMatch(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('example.test');
        $this->remoteAddress->method('getRemoteAddress')->willReturn('10.0.0.9');
        $this->httpHeader->method('getHttpHost')->willReturn('example.test');

        $this->assertTrue($this->checker->isDevAllowed());
    }

    public function testDeniesAccessWhenNeitherRemoteAddressNorHostMatch(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('10.0.0.1');
        $this->remoteAddress->method('getRemoteAddress')->willReturn('10.0.0.9');
        $this->httpHeader->method('getHttpHost')->willReturn('other.test');

        $this->assertFalse($this->checker->isDevAllowed());
    }

    public function testPassesStoreIdAndScopeToScopeConfig(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('dev/restrict/allow_ips', InspectorConfig::SCOPE_STORE, '2')
            ->willReturn('');

        $this->createChecker(scopeConfig: $scopeConfig)->isDevAllowed('2');
    }

    private function createChecker(
        ?ScopeConfigInterface $scopeConfig = null,
        ?RemoteAddress $remoteAddress = null,
        ?Header $httpHeader = null,
    ): DeveloperAccessChecker {
        return new DeveloperAccessChecker(
            $scopeConfig ?? $this->scopeConfig,
            $remoteAddress ?? $this->remoteAddress,
            $httpHeader ?? $this->httpHeader,
        );
    }
}
