<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use OpenForgeProject\MageForge\Model\Config\Inspector as InspectorConfig;
use OpenForgeProject\MageForge\Service\DeveloperAccessChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeveloperAccessCheckerTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;
    /**
     * @var RemoteAddress&MockObject
     */
    private $remoteAddress;
    /**
     * @var Header&MockObject
     */
    private $httpHeader;
    /**
     * @var DeveloperAccessChecker
     */
    private DeveloperAccessChecker $checker;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->remoteAddress = $this->createMock(RemoteAddress::class);
        $this->httpHeader = $this->createMock(Header::class);

        $this->checker = new DeveloperAccessChecker(
            $this->scopeConfig,
            $this->remoteAddress,
            $this->httpHeader,
        );
    }

    public function testAllowsAccessWhenNoAllowedIpsAreConfigured(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $this->remoteAddress->expects($this->never())->method('getRemoteAddress');

        $this->assertTrue($this->checker->isDevAllowed());
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
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('dev/restrict/allow_ips', InspectorConfig::SCOPE_STORE, '2')
            ->willReturn('');

        $this->checker->isDevAllowed('2');
    }
}
