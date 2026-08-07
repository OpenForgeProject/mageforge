<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\PackageInfo;
use OpenForgeProject\MageForge\Model\Config\TemplateOverride as TemplateOverrideConfig;
use OpenForgeProject\MageForge\Service\TemplateOverride\CommentStyle;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateCopier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateCopierTest extends TestCase
{
    /**
     * @var File&MockObject
     */
    private MockObject $fileDriver;

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private MockObject $scopeConfig;

    /**
     * @var PackageInfo&MockObject
     */
    private MockObject $packageInfo;

    /**
     * @var TemplateCopier
     */
    private TemplateCopier $copier;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->packageInfo = $this->createMock(PackageInfo::class);
        $this->copier = new TemplateCopier(
            $this->fileDriver,
            $this->scopeConfig,
            $this->packageInfo,
            new CommentStyle(CommentStyle::NONE),
        );
    }

    public function testCreatesMissingTargetDirectoryBeforeCopying(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->fileDriver->method('getParentDirectory')->with('/theme/M/templates/a/b.phtml')
            ->willReturn('/theme/M/templates/a');
        $this->fileDriver->method('isDirectory')->with('/theme/M/templates/a')->willReturn(false);
        $this->fileDriver->expects($this->once())->method('createDirectory')->with('/theme/M/templates/a');
        $this->fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('/module/templates/a/b.phtml', '/theme/M/templates/a/b.phtml')
            ->willReturn(true);

        $this->copier->copy('/module/templates/a/b.phtml', '/theme/M/templates/a/b.phtml');
    }

    public function testDoesNotCreateExistingTargetDirectory(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/M/templates');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->expects($this->never())->method('createDirectory');
        $this->fileDriver->expects($this->once())->method('copy')->willReturn(true);

        $this->copier->copy('/source.phtml', '/theme/M/templates/target.phtml');
    }

    public function testAddsHeaderWhenEnabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->with(TemplateOverrideConfig::XML_PATH_ADD_HEADER, TemplateOverrideConfig::SCOPE_STORE)
            ->willReturn(true);
        $this->packageInfo->method('getVersion')->with('Magento_Catalog')->willReturn('1.2.3');
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/Magento_Catalog/templates');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver
            ->method('fileGetContents')
            ->with('/module/view/frontend/templates/product/view/details.phtml')
            ->willReturn('<div>original</div>');
        $this->fileDriver
            ->expects($this->once())
            ->method('filePutContents')
            ->with(
                '/theme/Magento_Catalog/templates/product/view/details.phtml',
                $this->matchesRegularExpression('/MageForge Template Override from \d{4}-\d{2}-\d{2}/'),
            );
        $this->fileDriver->expects($this->never())->method('copy');

        $this->copier->copy(
            '/module/view/frontend/templates/product/view/details.phtml',
            '/theme/Magento_Catalog/templates/product/view/details.phtml',
            'Magento_Catalog',
        );
    }

    public function testHeaderIncludesSourceModuleVersion(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->packageInfo->method('getVersion')->with('Vendor_Module')->willReturn('4.5.6');
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('content');
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy('/source.js', '/theme/target.js', 'Vendor_Module');

        $this->assertStringContainsString('Source: /source.js', $captured ?? '');
        $this->assertStringContainsString('Source Module-Version: 4.5.6', $captured ?? '');
    }

    public function testSkipsModuleVersionWhenUnknown(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->packageInfo->method('getVersion')->willReturn('');
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('content');
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy('/source.phtml', '/theme/target.phtml', 'Unknown_Module');

        $this->assertStringNotContainsString('Source Module-Version', $captured ?? '');
    }

    public function testHeaderFormatIsExactlyAsExpected(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->packageInfo->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('<div>content</div>');
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy('/module/view/frontend/templates/widget.phtml', '/theme/dir/widget.phtml', 'Vendor_Module');

        $this->assertSame(
            "<?php\n"
            . "/**\n"
            . " * MageForge Template Override from " . date('Y-m-d') . "\n"
            . " * Source: /module/view/frontend/templates/widget.phtml\n"
            . " * Source Module-Version: 1.2.3\n"
            . " */\n"
            . "\n"
            . "<div>content</div>",
            $captured,
        );
    }

    public function testSkipsHeaderForUnsupportedFileTypes(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->expects($this->once())->method('copy')->with(
            '/module/web/images/logo.png',
            '/theme/dir/logo.png',
        );
        $this->fileDriver->expects($this->never())->method('fileGetContents');
        $this->fileDriver->expects($this->never())->method('filePutContents');

        $this->copier->copy('/module/web/images/logo.png', '/theme/dir/logo.png', 'Magento_Theme');
    }

    public function testUsesHtmlCommentForEmailTemplates(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('<p>order</p>');
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy('/source.html', '/theme/dir/target.html', 'Vendor_Module');

        $this->assertStringStartsWith('<!--', $captured ?? '');
        $this->assertStringEndsWith("-->\n\n<p>order</p>", $captured ?? '');
    }
}
