<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\DirectoryList;
use OpenForgeProject\MageForge\Service\VendorFileMapper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

/**
 * Test VendorFileMapper service
 *
 * Note: Most tests pass theme area explicitly to mapToThemePath().
 * The method can also extract area from theme path, but explicit passing
 * is recommended for clarity and when theme path doesn't contain area info.
 */
class VendorFileMapperTest extends TestCase
{
    private VendorFileMapper $vendorFileMapper;
    private ComponentRegistrarInterface|MockObject $componentRegistrar;
    private DirectoryList|MockObject $directoryList;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->directoryList->method('getRoot')->willReturn('/var/www/html/magento');

        $this->vendorFileMapper = new VendorFileMapper(
            $this->componentRegistrar,
            $this->directoryList
        );
    }

    /**
     * Test mapping from module view/frontend to frontend theme
     */
    public function testMapFrontendFileToFrontendTheme(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Catalog' => '/var/www/html/magento/vendor/magento/module-catalog'
            ]);

        $sourcePath = 'vendor/magento/module-catalog/view/frontend/templates/product/list.phtml';
        $themePath = '/var/www/html/magento/app/design/frontend/Magento/luma';
        $themeArea = 'frontend';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath, $themeArea);

        $this->assertEquals(
            '/var/www/html/magento/app/design/frontend/Magento/luma/Magento_Catalog/templates/product/list.phtml',
            $result
        );
    }

    /**
     * Test mapping from module view/base to frontend theme (base is compatible)
     */
    public function testMapBaseFileToFrontendTheme(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Theme' => '/var/www/html/magento/vendor/magento/module-theme'
            ]);

        $sourcePath = 'vendor/magento/module-theme/view/base/web/css/styles.css';
        $themePath = '/var/www/html/magento/app/design/frontend/Magento/luma';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/frontend/Magento/luma/Magento_Theme/web/css/styles.css',
            $result
        );
    }

    /**
     * Test mapping from module view/adminhtml to adminhtml theme
     */
    public function testMapAdminhtmlFileToAdminhtmlTheme(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Backend' => '/var/www/html/magento/vendor/magento/module-backend'
            ]);

        $sourcePath = 'vendor/magento/module-backend/view/adminhtml/templates/dashboard.phtml';
        $themePath = '/var/www/html/magento/app/design/adminhtml/Magento/backend';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/adminhtml/Magento/backend/Magento_Backend/templates/dashboard.phtml',
            $result
        );
    }

    /**
     * Test mapping from module view/base to adminhtml theme (base is compatible)
     */
    public function testMapBaseFileToAdminhtmlTheme(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Ui' => '/var/www/html/magento/vendor/magento/module-ui'
            ]);

        $sourcePath = 'vendor/magento/module-ui/view/base/web/js/grid/columns/column.js';
        $themePath = '/var/www/html/magento/app/design/adminhtml/Magento/backend';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/adminhtml/Magento/backend/Magento_Ui/web/js/grid/columns/column.js',
            $result
        );
    }

    /**
     * Test that adminhtml files cannot be mapped to frontend themes
     */
    public function testAdminhtmlFileToFrontendThemeThrowsException(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Backend' => '/var/www/html/magento/vendor/magento/module-backend'
            ]);

        $sourcePath = 'vendor/magento/module-backend/view/adminhtml/templates/dashboard.phtml';
        $themePath = '/var/www/html/magento/app/design/frontend/Magento/luma';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot map file from area 'adminhtml' to frontend theme");

        $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);
    }

    /**
     * Test that frontend files cannot be mapped to adminhtml themes
     */
    public function testFrontendFileToAdminhtmlThemeThrowsException(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Catalog' => '/var/www/html/magento/vendor/magento/module-catalog'
            ]);

        $sourcePath = 'vendor/magento/module-catalog/view/frontend/templates/product/list.phtml';
        $themePath = '/var/www/html/magento/app/design/adminhtml/Magento/backend';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot map file from area 'frontend' to adminhtml theme");

        $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);
    }

    /**
     * Test that files outside view/ directory throw exception
     */
    public function testNonViewFileThrowsException(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Catalog' => '/var/www/html/magento/vendor/magento/module-catalog'
            ]);

        $sourcePath = 'vendor/magento/module-catalog/etc/di.xml';
        $themePath = '/var/www/html/magento/app/design/frontend/Magento/luma';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("File is not under a view/ directory");

        $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);
    }

    /**
     * Test nested module pattern (e.g., from Hyva compatibility modules)
     */
    public function testNestedModulePattern(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([]);

        $sourcePath = 'vendor/hyva-themes/magento2-hyva-checkout/src/view/frontend/Magento_Checkout/templates/cart.phtml';
        $themePath = '/var/www/html/magento/app/design/frontend/Hyva/default';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/frontend/Hyva/default/Magento_Checkout/templates/cart.phtml',
            $result
        );
    }

    /**
     * Test nested module pattern with area validation
     */
    public function testNestedModulePatternWithWrongArea(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([]);

        $sourcePath = 'vendor/some-vendor/module/src/view/adminhtml/Magento_Backend/templates/test.phtml';
        $themePath = '/var/www/html/magento/app/design/frontend/Magento/luma';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot map file from area 'adminhtml' to frontend theme");

        $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);
    }

    /**
     * Test absolute path normalization
     */
    public function testAbsolutePathNormalization(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Catalog' => '/var/www/html/magento/vendor/magento/module-catalog'
            ]);

        $sourcePath = '/var/www/html/magento/vendor/magento/module-catalog/view/frontend/templates/product/list.phtml';
        $themePath = '/var/www/html/magento/app/design/frontend/Magento/luma';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/frontend/Magento/luma/Magento_Catalog/templates/product/list.phtml',
            $result
        );
    }

    /**
     * Test Hyvä theme with base file
     */
    public function testHyvaThemeWithBaseFile(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Hyva_Theme' => '/var/www/html/magento/vendor/hyva-themes/magento2-default-theme'
            ]);

        $sourcePath = 'vendor/hyva-themes/magento2-default-theme/view/base/web/tailwind/tailwind.css';
        $themePath = '/var/www/html/magento/app/design/frontend/Hyva/default';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/frontend/Hyva/default/Hyva_Theme/web/tailwind/tailwind.css',
            $result
        );
    }

    /**
     * Test custom theme (Tailwind-based without Hyvä)
     */
    public function testCustomTailwindTheme(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Theme' => '/var/www/html/magento/vendor/magento/module-theme'
            ]);

        $sourcePath = 'vendor/magento/module-theme/view/frontend/layout/default.xml';
        $themePath = '/var/www/html/magento/app/design/frontend/Custom/tailwind';

        $result = $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);

        $this->assertEquals(
            '/var/www/html/magento/app/design/frontend/Custom/tailwind/Magento_Theme/layout/default.xml',
            $result
        );
    }

    /**
     * Test that theme path without area throws exception
     */
    public function testThemePathWithoutAreaThrowsException(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'Magento_Catalog' => '/var/www/html/magento/vendor/magento/module-catalog'
            ]);

        $sourcePath = 'vendor/magento/module-catalog/view/frontend/templates/test.phtml';
        $themePath = '/var/www/html/magento/app/design/Magento/luma'; // Missing frontend/adminhtml

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not determine theme area from path");

        $this->vendorFileMapper->mapToThemePath($sourcePath, $themePath);
    }
}
