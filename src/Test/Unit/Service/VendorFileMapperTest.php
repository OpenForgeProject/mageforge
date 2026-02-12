<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\DirectoryList;
use OpenForgeProject\MageForge\Service\VendorFileMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VendorFileMapperTest extends TestCase
{
    private VendorFileMapper $mapper;
    private ComponentRegistrarInterface|MockObject $componentRegistrarMock;
    private DirectoryList|MockObject $directoryListMock;

    protected function setUp(): void
    {
        $this->componentRegistrarMock = $this->getMockBuilder(ComponentRegistrarInterface::class)
            ->getMock();

        $this->directoryListMock = $this->getMockBuilder(DirectoryList::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Default root path for tests
        $this->directoryListMock->method('getRoot')
            ->willReturn('/var/www/html');

        $this->mapper = new VendorFileMapper(
            $this->componentRegistrarMock,
            $this->directoryListMock
        );
    }

    public function testMapToThemePathWithStandardModule(): void
    {
        $sourceFile = 'vendor/magento/module-catalog/view/frontend/templates/product/list.phtml';
        $themePath = 'app/design/frontend/My/Theme';

        // Mock ComponentRegistrar to find the module
        $this->componentRegistrarMock->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn([
                'Magento_Catalog' => '/var/www/html/vendor/magento/module-catalog'
            ]);

        $result = $this->mapper->mapToThemePath($sourceFile, $themePath);

        $this->assertEquals(
            'app/design/frontend/My/Theme/Magento_Catalog/templates/product/list.phtml',
            $result
        );
    }

    public function testMapToThemePathWithNestedCompatModule(): void
    {
        // Path simulates a Hyvä Compat module with nested target module folder
        $sourceFile = 'vendor/mollie/magento2-hyva-compatibility/src/Mollie_HyvaCompatibility/view/frontend/templates/Mollie_Payment/product/view/applepay.phtml';
        $themePath = 'app/design/frontend/My/Theme';

        // Regex detection should prioritize this, so ComponentRegistrar might not even be called
        // or if called, it shouldn't matter for the logic flow if regex matches first.

        $result = $this->mapper->mapToThemePath($sourceFile, $themePath);

        $this->assertEquals(
            'app/design/frontend/My/Theme/Mollie_Payment/templates/product/view/applepay.phtml',
            $result
        );
    }

    public function testMapToThemePathWithVendorTheme(): void
    {
        // Path simulates a Vendor Theme (e.g. Hyva Default)
        $sourceFile = 'vendor/hyva-themes/magento2-default-theme/Magento_GiftMessage/templates/php-cart/gift-options-body.phtml';
        $themePath = 'app/design/frontend/My/Theme';

        $result = $this->mapper->mapToThemePath($sourceFile, $themePath);

        $this->assertEquals(
            'app/design/frontend/My/Theme/Magento_GiftMessage/templates/php-cart/gift-options-body.phtml',
            $result
        );
    }

    public function testMapToThemePathWithAbsolutePaths(): void
    {
        $sourceFile = '/var/www/html/vendor/magento/module-customer/view/frontend/templates/account/dashboard.phtml';
        // Note: passing absolute theme path here
        $themePath = '/var/www/html/app/design/frontend/Vendor/Theme';

        $this->componentRegistrarMock->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn([
                'Magento_Customer' => '/var/www/html/vendor/magento/module-customer'
            ]);

        $result = $this->mapper->mapToThemePath($sourceFile, $themePath);

        // Expect absolute path return because input theme path was absolute (logic prepends theme path)
        $this->assertEquals(
            '/var/www/html/app/design/frontend/Vendor/Theme/Magento_Customer/templates/account/dashboard.phtml',
            $result
        );
    }

    public function testThrowsExceptionIfModuleNotFound(): void
    {
        $this->expectException(\RuntimeException::class);

        $sourceFile = 'vendor/unknown/package/somefile.txt';
        $themePath = 'app/design/frontend/My/Theme';

        $this->componentRegistrarMock->method('getPaths')
            ->willReturn([]); // No modules registered

        $this->mapper->mapToThemePath($sourceFile, $themePath);
    }
}
