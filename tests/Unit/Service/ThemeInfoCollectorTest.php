<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\HyvaVersionDetector;
use OpenForgeProject\MageForge\Service\ThemeInfoCollector;
use PHPUnit\Framework\TestCase;

class ThemeInfoCollectorTest extends TestCase
{
    /**
     * @var string
     */
    private string $themePath;

    protected function setUp(): void
    {
        $this->themePath = sys_get_temp_dir() . '/mageforge_theme_' . uniqid();
        mkdir($this->themePath . '/Magento_Catalog/templates/product', 0777, true);
        mkdir($this->themePath . '/Magento_Catalog/layout', 0777, true);
        mkdir($this->themePath . '/Magento_Theme/web/css', 0777, true);
        mkdir($this->themePath . '/web/css', 0777, true);
        touch($this->themePath . '/Magento_Catalog/templates/product/view.phtml');
        touch($this->themePath . '/Magento_Catalog/layout/catalog_product_view.xml');
        touch($this->themePath . '/Magento_Catalog/requirejs-config.js');
        touch($this->themePath . '/Magento_Theme/web/css/custom.less');
        touch($this->themePath . '/web/css/ignored.css');
    }

    protected function tearDown(): void
    {
        (new File())->deleteDirectory($this->themePath);
    }

    public function testCountsOverridesOnlyInsideModuleDirectories(): void
    {
        $counts = $this->createCollector()->getOverrideCounts($this->themePath);

        $this->assertSame(
            ['modules' => 2, 'templates' => 1, 'layouts' => 1, 'requirejs' => 1, 'webAssets' => 1],
            $counts,
        );
    }

    public function testReportsThemeWithoutComposerJsonAsLocal(): void
    {
        $info = $this->createCollector()->getComposerInfo($this->themePath);

        $this->assertNull($info['package']);
        $this->assertNull($info['version']);
        $this->assertSame('local path', $info['source']);
    }

    public function testDetectsVendorAndAppDesignSources(): void
    {
        $collector = $this->createCollector();

        $this->assertSame('vendor', $collector->getComposerInfo('/var/www/vendor/acme/theme')['source']);
        $this->assertSame('app/design', $collector->getComposerInfo('/var/www/app/design/frontend/Acme/x')['source']);
    }

    public function testReadsPackageNameFromComposerJson(): void
    {
        file_put_contents($this->themePath . '/composer.json', '{"name": "vendor/not-installed-theme"}');

        $info = $this->createCollector()->getComposerInfo($this->themePath);

        $this->assertSame('vendor/not-installed-theme', $info['package']);
        $this->assertNull($info['version']);
    }

    public function testListsDeployedLocalesSorted(): void
    {
        $directory = $this->createMock(ReadInterface::class);
        $directory->method('isDirectory')->willReturn(true);
        $directory
            ->method('read')
            ->with('frontend/Vendor/theme')
            ->willReturn(['frontend/Vendor/theme/en_US', 'frontend/Vendor/theme/de_DE']);

        $this->assertSame(
            ['de_DE', 'en_US'],
            $this->createCollector($directory)->getDeployedLocales('frontend', 'Vendor/theme'),
        );
    }

    public function testReturnsNoLocalesWhenThemeIsNotDeployed(): void
    {
        $directory = $this->createMock(ReadInterface::class);
        $directory->method('isDirectory')->willReturn(false);

        $this->assertSame([], $this->createCollector($directory)->getDeployedLocales('frontend', 'Vendor/theme'));
    }

    private function createCollector(?ReadInterface $directory = null): ThemeInfoCollector
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($directory ?? $this->createMock(ReadInterface::class));

        return new ThemeInfoCollector(new File(), $filesystem, $this->createMock(HyvaVersionDetector::class));
    }
}
