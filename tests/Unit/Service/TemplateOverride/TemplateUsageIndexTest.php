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

    public function testRecordsRelativeTemplatePathsWithoutModuleForAnyModule(): void
    {
        file_put_contents($this->directory . '/Block.php', "<?php protected \$_template = 'widget/list.phtml';");
        file_put_contents(
            $this->directory . '/view/frontend/layout/blog.xml',
            '<block template="sidebar/archive.phtml"><argument name="template" xsi:type="string">post/view.phtml'
            . '</argument><value>product/widget/link.phtml</value>'
            . '<x>Magento_Paypal::express/shortcut.phtml</x></block>',
        );

        $index = new TemplateUsageIndex($this->createMock(ComponentRegistrarInterface::class), new File());

        $references = $index->collectFromDirectories([$this->directory]);

        foreach (['widget/list', 'sidebar/archive', 'post/view', 'product/widget/link'] as $path) {
            $this->assertArrayHasKey(TemplateUsageIndex::ANY_MODULE . $path . '.phtml', $references);
        }
        $this->assertArrayHasKey('Magento_Paypal::express/shortcut.phtml', $references);
        $this->assertArrayNotHasKey(TemplateUsageIndex::ANY_MODULE . 'express/shortcut.phtml', $references);
    }
}
