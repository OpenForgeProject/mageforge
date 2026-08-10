<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
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
     * @var DirectoryList&MockObject
     */
    private MockObject $directoryList;

    /**
     * @var ComponentRegistrarInterface&MockObject
     */
    private MockObject $componentRegistrar;

    /**
     * @var array<string, string>
     */
    private array $modulePaths = [];

    /**
     * @var TemplateCopier
     */
    private TemplateCopier $copier;

    protected function setUp(): void
    {
        $this->modulePaths = [];
        $this->fileDriver = $this->createMock(File::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->packageInfo = $this->createMock(PackageInfo::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->directoryList->method('getRoot')->willReturn('/magento');
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturnCallback(fn(): array => $this->modulePaths);
        $this->copier = new TemplateCopier(
            $this->fileDriver,
            $this->scopeConfig,
            $this->packageInfo,
            new CommentStyle(CommentStyle::NONE),
            $this->directoryList,
            $this->componentRegistrar,
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private function registerModulePaths(array $paths): void
    {
        $this->modulePaths = $paths;
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
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Magento_Catalog' => '/module',
        ]);
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
                $this->matchesRegularExpression('/@mageforge-template-override/'),
            );
        $this->fileDriver->expects($this->never())->method('copy');

        $this->copier->copy(
            '/module/view/frontend/templates/product/view/details.phtml',
            '/theme/Magento_Catalog/templates/product/view/details.phtml',
            'Magento_Catalog',
        );
    }

    public function testHeaderIncludesRelativeSourcePathAndModuleVersion(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
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

        $this->copier->copy('/magento/vendor/module/web/js/source.js', '/theme/target.js', 'Vendor_Module');

        $this->assertStringContainsString('Source: vendor/module/web/js/source.js', $captured ?? '');
        $this->assertStringNotContainsString('Source: /magento/', $captured ?? '');
        $this->assertStringContainsString('Source Module: Vendor_Module', $captured ?? '');
        $this->assertStringContainsString('Source Module-Version: 4.5.6', $captured ?? '');
    }

    public function testSkipsModuleVersionWhenUnknown(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Unknown_Module' => '/magento/vendor/unknown',
        ]);
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

        $this->copier->copy('/magento/vendor/unknown/source.phtml', '/theme/target.phtml', 'Unknown_Module');

        $this->assertStringContainsString('@module Unknown_Module', $captured ?? '');
        $this->assertStringNotContainsString('@module-version', $captured ?? '');
    }

    public function testHeaderFormatIsExactlyAsExpectedForTemplateStartingWithHtml(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
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

        $this->copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertSame(
            "<?php\n"
            . "/**\n"
            . " * @mageforge-template-override\n"
            . " * @date " . date('Y-m-d') . "\n"
            . " * @source vendor/module/view/frontend/templates/widget.phtml\n"
            . " * @module Vendor_Module\n"
            . " * @module-version 1.2.3\n"
            . " */\n"
            . "?>\n"
            . "\n"
            . "<div>content</div>",
            $captured,
        );
    }

    public function testHeaderIsInjectedAfterExistingPhpOpenTag(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
        $this->packageInfo->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn("<?php\n\ndeclare(strict_types=1);\n\n// code\n");
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertSame(
            "<?php\n"
            . "/**\n"
            . " * @mageforge-template-override\n"
            . " * @date " . date('Y-m-d') . "\n"
            . " * @source vendor/module/view/frontend/templates/widget.phtml\n"
            . " * @module Vendor_Module\n"
            . " * @module-version 1.2.3\n"
            . " */\n"
            . "\n"
            . "declare(strict_types=1);\n\n// code\n",
            $captured,
        );
        $this->assertStringNotContainsString('?>', substr($captured, 0, 100));
    }

    public function testHeaderIsInjectedAfterPhpOpenTagOnSameLine(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
        $this->packageInfo->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn("<?php declare(strict_types=1);\n\n// code\n");
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertSame(
            "<?php\n"
            . "/**\n"
            . " * @mageforge-template-override\n"
            . " * @date " . date('Y-m-d') . "\n"
            . " * @source vendor/module/view/frontend/templates/widget.phtml\n"
            . " * @module Vendor_Module\n"
            . " * @module-version 1.2.3\n"
            . " */\n"
            . "\n"
            . "declare(strict_types=1);\n\n// code\n",
            $captured,
        );
    }

    public function testHeaderRecordsActualSourceModuleWhenLogicalModuleDiffers(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->registerModulePaths([
            'Hyva_MageWorxFaq' => '/magento/vendor/hyva-themes/magento2-mageworx-faq/src',
        ]);
        $this->packageInfo
            ->method('getVersion')
            ->willReturnCallback(static fn(string $module): string => match ($module) {
                'Hyva_MageWorxFaq' => '1.0.6',
                default => '',
            });
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn("<?php\n");
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $this->copier->copy(
            '/magento/vendor/hyva-themes/magento2-mageworx-faq/src/view/frontend/templates/faq/list.phtml',
            '/theme/dir/widget.phtml',
            'MageWorx_Faq',
        );

        $this->assertStringContainsString('@module Hyva_MageWorxFaq', $captured ?? '');
        $this->assertStringContainsString('@module-version 1.0.6', $captured ?? '');
        $this->assertStringContainsString('@override-for MageWorx_Faq', $captured ?? '');
        $this->assertStringNotContainsString('@module MageWorx_Faq', $captured ?? '');
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

    public function testPhtmlHeaderExcludesDateWhenDisabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_ADD_HEADER,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_PHTML,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_PATH,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_MODULE,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_MODULE_VERSION => true,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_DATE => false,
                default => false,
            });
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
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

        $this->copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertStringContainsString('@mageforge-template-override', $captured ?? '');
        $this->assertStringContainsString('@module-version 1.2.3', $captured ?? '');
        $this->assertStringNotContainsString('@date ', $captured ?? '');
    }

    public function testPhtmlHeaderExcludesModuleVersionWhenDisabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_ADD_HEADER,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_PHTML,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_PATH,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_MODULE,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_DATE => true,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_MODULE_VERSION => false,
                default => false,
            });
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
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

        $this->copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertStringContainsString('@date ' . date('Y-m-d'), $captured ?? '');
        $this->assertStringContainsString('@module Vendor_Module', $captured ?? '');
        $this->assertStringNotContainsString('@module-version', $captured ?? '');
    }

    public function testPhtmlHeaderExcludesSourcePathWhenDisabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_ADD_HEADER,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_PHTML,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_DATE,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_MODULE,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_MODULE_VERSION => true,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_PATH => false,
                default => false,
            });
        $this->registerModulePaths([
            'Vendor_Module' => '/magento/vendor/module',
        ]);
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

        $this->copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertStringContainsString('@module Vendor_Module', $captured ?? '');
        $this->assertStringContainsString('@module-version 1.2.3', $captured ?? '');
        $this->assertStringNotContainsString('@source ', $captured ?? '');
    }

    public function testPhtmlHeaderExcludesOverrideForWhenDisabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_ADD_HEADER,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_PHTML,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_DATE,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_PATH,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_MODULE,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_MODULE_VERSION => true,
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_OVERRIDE_FOR => false,
                default => false,
            });
        $this->registerModulePaths([
            'Hyva_MageWorxFaq' => '/magento/vendor/hyva-themes/magento2-mageworx-faq/src',
        ]);
        $this->packageInfo
            ->method('getVersion')
            ->willReturnCallback(static fn(string $module): string => match ($module) {
                'Hyva_MageWorxFaq' => '1.0.6',
                default => '',
            });
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

        $this->copier->copy(
            '/magento/vendor/hyva-themes/magento2-mageworx-faq/src/view/frontend/templates/faq/list.phtml',
            '/theme/dir/widget.phtml',
            'MageWorx_Faq',
        );

        $this->assertStringContainsString('@module Hyva_MageWorxFaq', $captured ?? '');
        $this->assertStringContainsString('@module-version 1.0.6', $captured ?? '');
        $this->assertStringNotContainsString('@override-for', $captured ?? '');
    }

    /**
     * @dataProvider formatToggleProvider
     * @param string $sourcePath
     * @param string $targetPath
     * @param string $enableConfigPath
     */
    public function testSkipsHeaderWhenFormatToggleDisabled(
        string $sourcePath,
        string $targetPath,
        string $enableConfigPath,
    ): void {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_ADD_HEADER => true,
                $enableConfigPath => false,
                default => true,
            });
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->expects($this->once())->method('copy')->with($sourcePath, $targetPath);
        $this->fileDriver->expects($this->never())->method('fileGetContents');
        $this->fileDriver->expects($this->never())->method('filePutContents');

        $this->copier->copy($sourcePath, $targetPath, 'Vendor_Module');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function formatToggleProvider(): array
    {
        return [
            'phtml disabled' => [
                '/module/view/frontend/templates/widget.phtml',
                '/theme/dir/widget.phtml',
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_PHTML,
            ],
            'html disabled' => [
                '/module/view/frontend/templates/mail.html',
                '/theme/dir/mail.html',
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_HTML,
            ],
            'xml disabled' => [
                '/module/view/frontend/layout/default.xml',
                '/theme/dir/default.xml',
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_XML,
            ],
            'web asset disabled' => [
                '/module/web/js/source.js',
                '/theme/dir/source.js',
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_WEB_ASSETS,
            ],
            'shell disabled' => [
                '/module/web/scripts/deploy.sh',
                '/theme/dir/deploy.sh',
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_SHELL,
            ],
        ];
    }
}
