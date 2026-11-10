<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Model\ObsoleteOverride;
use OpenForgeProject\MageForge\Model\ObsoleteReason;
use OpenForgeProject\MageForge\Service\TemplateOverride\OverrideRemover;
use PHPUnit\Framework\TestCase;

class OverrideRemoverTest extends TestCase
{
    public function testRemovesFileAndEmptyDirectoriesUpToModuleDirectory(): void
    {
        $theme = sys_get_temp_dir() . '/mageforge_remover_' . uniqid();
        mkdir($theme . '/Magento_Catalog/templates/product', 0777, true);
        file_put_contents($theme . '/Magento_Catalog/templates/product/a.phtml', 'a');

        $override = new ObsoleteOverride(
            'Vendor/theme',
            $theme . '/Magento_Catalog/templates/product/a.phtml',
            'Magento_Catalog::product/a.phtml',
            ObsoleteReason::IDENTICAL,
            '',
            true,
        );

        $this->assertTrue((new OverrideRemover(new File()))->remove($override));
        $this->assertDirectoryDoesNotExist($theme . '/Magento_Catalog');
        $this->assertDirectoryExists($theme);

        rmdir($theme);
    }

    public function testStillSucceedsWhenDirectoryCleanupFails(): void
    {
        $driver = $this->createMock(File::class);
        $driver->method('getParentDirectory')->willReturn('/theme/Magento_Catalog/templates');
        $driver->method('isDirectory')->willReturn(true);
        $driver->method('readDirectory')->willThrowException(new \RuntimeException('not readable'));

        $override = new ObsoleteOverride(
            'Vendor/theme',
            '/theme/a.phtml',
            'M_C::a.phtml',
            ObsoleteReason::IDENTICAL,
            '',
            true,
        );

        $this->assertTrue((new OverrideRemover($driver))->remove($override));
    }
}
