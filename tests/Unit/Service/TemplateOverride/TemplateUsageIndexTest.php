<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateUsageIndex;
use PHPUnit\Framework\TestCase;

class TemplateUsageIndexTest extends TestCase
{
    /**
     * @var string
     */
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mageforge_usage_' . uniqid();
        mkdir($this->directory . '/view/frontend/layout', 0777, true);
        mkdir($this->directory . '/node_modules/x', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testCollectsReferencesFromLayoutAndPhpAndSkipsIgnoredDirectories(): void
    {
        file_put_contents(
            $this->directory . '/view/frontend/layout/default.xml',
            '<block template="Magento_Catalog::product/view/details.phtml"/>',
        );
        file_put_contents($this->directory . '/Block.php', "<?php \$t = 'Vendor_Mod::foo.phtml';");
        file_put_contents($this->directory . '/node_modules/x/a.xml', 'Ignored_Mod::skip.phtml');

        $index = new TemplateUsageIndex($this->createMock(ComponentRegistrarInterface::class), new File());
        $references = $index->collectFromDirectories([$this->directory, $this->directory . '/missing']);

        $keys = array_keys($references);
        sort($keys);

        $this->assertSame(['Magento_Catalog::product/view/details.phtml', 'Vendor_Mod::foo.phtml'], $keys);
    }
}
