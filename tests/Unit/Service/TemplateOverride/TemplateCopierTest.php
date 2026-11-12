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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TemplateCopierTest extends TestCase
{
    /**
     * @var File&Stub
     */
    private Stub $fileDriver;

    /**
     * @var ScopeConfigInterface&Stub
     */
    private Stub $scopeConfig;

    /**
     * @var PackageInfo&Stub
     */
    private Stub $packageInfo;

    /**
     * @var DirectoryList&Stub
     */
    private Stub $directoryList;

    /**
     * @var ComponentRegistrarInterface&Stub
     */
    private Stub $componentRegistrar;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createStub(File::class);
        $this->scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $this->packageInfo = $this->createStub(PackageInfo::class);
        $this->directoryList = $this->createStub(DirectoryList::class);
        $this->componentRegistrar = $this->createStub(ComponentRegistrarInterface::class);
        $this->directoryList->method('getRoot')->willReturn('/magento');
        $this->componentRegistrar->method('getPaths')->willReturnMap([[ComponentRegistrar::MODULE, []]]);
    }

    private function createCopier(
        ?File $fileDriver = null,
        ?PackageInfo $packageInfo = null,
        ?ComponentRegistrarInterface $componentRegistrar = null,
    ): TemplateCopier {
        return new TemplateCopier(
            $fileDriver ?? $this->fileDriver,
            $this->scopeConfig,
            $packageInfo ?? $this->packageInfo,
            new CommentStyle(CommentStyle::NONE),
            $this->directoryList,
            $componentRegistrar ?? $this->componentRegistrar,
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private function createModuleRegistrar(array $paths): ComponentRegistrarInterface&MockObject
    {
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn($paths);

        return $componentRegistrar;
    }

    public function testCreatesMissingTargetDirectoryBeforeCopying(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $fileDriver = $this->createMock(File::class);
        $fileDriver
            ->expects($this->once())
            ->method('getParentDirectory')
            ->with('/theme/M/templates/a/b.phtml')
            ->willReturn('/theme/M/templates/a');
        $fileDriver->expects($this->once())->method('isDirectory')->with('/theme/M/templates/a')->willReturn(false);
        $fileDriver->expects($this->once())->method('createDirectory')->with('/theme/M/templates/a');
        $fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with('/module/templates/a/b.phtml', '/theme/M/templates/a/b.phtml')
            ->willReturn(true);
        $copier = $this->createCopier(fileDriver: $fileDriver);

        $copier->copy('/module/templates/a/b.phtml', '/theme/M/templates/a/b.phtml');
    }

    public function testDoesNotCreateExistingTargetDirectory(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/M/templates');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->expects($this->never())->method('createDirectory');
        $fileDriver->expects($this->once())->method('copy')->willReturn(true);
        $copier = $this->createCopier(fileDriver: $fileDriver);

        $copier->copy('/source.phtml', '/theme/M/templates/target.phtml');
    }

    public function testAddsHeaderWhenEnabled(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Magento_Catalog')->willReturn('1.2.3');
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/Magento_Catalog/templates');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver
            ->expects($this->once())
            ->method('fileGetContents')
            ->with('/module/view/frontend/templates/product/view/details.phtml')
            ->willReturn('<div>original</div>');
        $fileDriver
            ->expects($this->once())
            ->method('filePutContents')
            ->with(
                '/theme/Magento_Catalog/templates/product/view/details.phtml',
                $this->matchesRegularExpression('/@mageforge-template-override/'),
            );
        $fileDriver->expects($this->never())->method('copy');
        $copier = $this->createCopier(
            fileDriver: $fileDriver,
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Magento_Catalog' => '/module']),
        );

        $copier->copy(
            '/module/view/frontend/templates/product/view/details.phtml',
            '/theme/Magento_Catalog/templates/product/view/details.phtml',
            'Magento_Catalog',
        );
    }

    public function testHeaderIncludesRelativeSourcePathAndModuleVersion(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('4.5.6');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy('/magento/vendor/module/web/js/source.js', '/theme/target.js', 'Vendor_Module');

        $this->assertStringContainsString('Source: vendor/module/web/js/source.js', $captured ?? '');
        $this->assertStringNotContainsString('Source: /magento/', $captured ?? '');
        $this->assertStringContainsString('Source Module: Vendor_Module', $captured ?? '');
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
        $copier = $this->createCopier(
            componentRegistrar: $this->createModuleRegistrar(['Unknown_Module' => '/magento/vendor/unknown']),
        );

        $copier->copy('/magento/vendor/unknown/source.phtml', '/theme/target.phtml', 'Unknown_Module');

        $this->assertStringContainsString('@module Unknown_Module', $captured ?? '');
        $this->assertStringNotContainsString('@module-version', $captured ?? '');
    }

    public function testHeaderFormatIsExactlyAsExpectedForTemplateStartingWithHtml(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
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
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
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
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
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
        $copier = $this->createCopier(
            componentRegistrar: $this->createModuleRegistrar([
                'Hyva_MageWorxFaq' => '/magento/vendor/hyva-themes/magento2-mageworx-faq/src',
            ]),
        );

        $copier->copy(
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
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->expects($this->once())->method('copy')->with(
            '/module/web/images/logo.png',
            '/theme/dir/logo.png',
        );
        $fileDriver->expects($this->never())->method('fileGetContents');
        $fileDriver->expects($this->never())->method('filePutContents');
        $copier = $this->createCopier(fileDriver: $fileDriver);

        $copier->copy('/module/web/images/logo.png', '/theme/dir/logo.png', 'Magento_Theme');
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

        $copier = $this->createCopier(componentRegistrar: $this->createModuleRegistrar([]));

        $copier->copy('/source.html', '/theme/dir/target.html', 'Vendor_Module');

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
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
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
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->never())->method('getVersion');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
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
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
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
        $copier = $this->createCopier(
            componentRegistrar: $this->createModuleRegistrar([
                'Hyva_MageWorxFaq' => '/magento/vendor/hyva-themes/magento2-mageworx-faq/src',
            ]),
        );

        $copier->copy(
            '/magento/vendor/hyva-themes/magento2-mageworx-faq/src/view/frontend/templates/faq/list.phtml',
            '/theme/dir/widget.phtml',
            'MageWorx_Faq',
        );

        $this->assertStringContainsString('@module Hyva_MageWorxFaq', $captured ?? '');
        $this->assertStringContainsString('@module-version 1.0.6', $captured ?? '');
        $this->assertStringNotContainsString('@override-for', $captured ?? '');
    }

    #[DataProvider('formatToggleProvider')]
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
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->expects($this->once())->method('copy')->with($sourcePath, $targetPath);
        $fileDriver->expects($this->never())->method('fileGetContents');
        $fileDriver->expects($this->never())->method('filePutContents');
        $copier = $this->createCopier(fileDriver: $fileDriver);

        $copier->copy($sourcePath, $targetPath, 'Vendor_Module');
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

    #[DataProvider('enabledFormatProvider')]
    public function testAddsHeaderForEachEnabledFileFormat(
        string $sourcePath,
        string $targetPath,
        string $headerMarker,
    ): void {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('content');
        $fileDriver->expects($this->never())->method('copy');
        $captured = null;
        $fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });
        $copier = $this->createCopier(
            fileDriver: $fileDriver,
            componentRegistrar: $this->createModuleRegistrar([]),
        );

        $copier->copy($sourcePath, $targetPath, 'Vendor_Module');

        $this->assertStringContainsString($headerMarker, $captured ?? '');
    }

    /**
     * Every extension of every match arm must map to its format toggle,
     * including the alias extensions (php, htm, xhtml, ...).
     *
     * @return array<string, array{string, string, string}>
     */
    public static function enabledFormatProvider(): array
    {
        $plainMarker = 'MageForge Template Override';

        return [
            'phtml' => [
                '/module/view/frontend/templates/widget.phtml',
                '/theme/dir/widget.phtml',
                '@mageforge-template-override',
            ],
            'php' => ['/module/Block/Widget.php', '/theme/dir/Widget.php', '@mageforge-template-override'],
            'html' => ['/module/view/frontend/templates/mail.html', '/theme/dir/mail.html', $plainMarker],
            'htm' => ['/module/view/frontend/templates/mail.htm', '/theme/dir/mail.htm', $plainMarker],
            'xml' => ['/module/view/frontend/layout/default.xml', '/theme/dir/default.xml', $plainMarker],
            'xhtml' => ['/module/view/frontend/templates/page.xhtml', '/theme/dir/page.xhtml', $plainMarker],
            'svg' => ['/module/web/images/icon.svg', '/theme/dir/icon.svg', $plainMarker],
            'css' => ['/module/web/css/styles.css', '/theme/dir/styles.css', $plainMarker],
            'js' => ['/module/web/js/source.js', '/theme/dir/source.js', $plainMarker],
            'less' => ['/module/web/css/styles.less', '/theme/dir/styles.less', $plainMarker],
            'scss' => ['/module/web/css/styles.scss', '/theme/dir/styles.scss', $plainMarker],
            'sass' => ['/module/web/css/styles.sass', '/theme/dir/styles.sass', $plainMarker],
            'ts' => ['/module/web/ts/source.ts', '/theme/dir/source.ts', $plainMarker],
            'sh' => ['/module/web/scripts/deploy.sh', '/theme/dir/deploy.sh', $plainMarker],
            'bash' => ['/module/web/scripts/deploy.bash', '/theme/dir/deploy.bash', $plainMarker],
            'zsh' => ['/module/web/scripts/deploy.zsh', '/theme/dir/deploy.zsh', $plainMarker],
            'fish' => ['/module/web/scripts/deploy.fish', '/theme/dir/deploy.fish', $plainMarker],
        ];
    }

    public function testUppercaseExtensionIsMatchedCaseInsensitively(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->method('fileGetContents')->willReturn('content');
        $fileDriver->expects($this->never())->method('copy');
        $captured = null;
        $fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });
        $copier = $this->createCopier(
            fileDriver: $fileDriver,
            componentRegistrar: $this->createModuleRegistrar([]),
        );

        $copier->copy(
            '/module/view/frontend/templates/widget.phtml',
            '/theme/dir/WIDGET.PHTML',
            'Vendor_Module',
        );

        $this->assertStringContainsString('@mageforge-template-override', $captured ?? '');
    }

    public function testSkipsHeaderWhenAddHeaderDisabledButFormatEnabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_ADD_HEADER => false,
                default => true,
            });
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $fileDriver->method('isDirectory')->willReturn(true);
        $fileDriver->expects($this->once())->method('copy')->with(
            '/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
        );
        $fileDriver->expects($this->never())->method('fileGetContents');
        $fileDriver->expects($this->never())->method('filePutContents');
        $copier = $this->createCopier(fileDriver: $fileDriver);

        $copier->copy(
            '/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );
    }

    public function testPlainHeaderContainsAllDetailsForUnresolvableModule(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('4.5.6');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar([]),
        );

        $copier->copy('/module/web/js/source.js', '/theme/dir/source.js', 'Vendor_Module');

        $this->assertStringContainsString('MageForge Template Override', $captured ?? '');
        $this->assertStringContainsString('Date: ' . date('Y-m-d'), $captured ?? '');
        $this->assertStringContainsString('Source: /module/web/js/source.js', $captured ?? '');
        $this->assertStringContainsString('Override For: Vendor_Module', $captured ?? '');
        $this->assertStringContainsString('Module-Version: 4.5.6', $captured ?? '');
        $this->assertStringNotContainsString('Source Module:', $captured ?? '');
    }

    public function testPlainHeaderOmitsDateWhenDisabled(): void
    {
        $this->scopeConfig
            ->method('isSetFlag')
            ->willReturnCallback(static fn(string $path): bool => match ($path) {
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_DATE => false,
                default => true,
            });
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

        $copier = $this->createCopier(componentRegistrar: $this->createModuleRegistrar([]));

        $copier->copy('/source.html', '/theme/dir/target.html', 'Vendor_Module');

        $this->assertStringContainsString('MageForge Template Override', $captured ?? '');
        $this->assertStringNotContainsString('Date:', $captured ?? '');
    }

    public function testPhpDocHeaderFallsBackToLogicalModuleWhenSourceNotResolvable(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('9.9.9');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar([]),
        );

        $copier->copy('/module/view/frontend/templates/widget.phtml', '/theme/dir/widget.phtml', 'Vendor_Module');

        $this->assertStringContainsString('@module Vendor_Module', $captured ?? '');
        $this->assertStringContainsString('@module-version 9.9.9', $captured ?? '');
        $this->assertStringNotContainsString('@override-for', $captured ?? '');
    }

    public function testPlainHeaderOmitsOverrideForWhenNoModuleNameGiven(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
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

        $copier = $this->createCopier(componentRegistrar: $this->createModuleRegistrar([]));

        $copier->copy('/module/web/js/source.js', '/theme/dir/source.js');

        $this->assertStringContainsString('MageForge Template Override', $captured ?? '');
        $this->assertStringNotContainsString('Override For', $captured ?? '');
        $this->assertStringNotContainsString('Source Module', $captured ?? '');
    }

    public function testPlainHeaderOmitsOverrideForWhenModuleNameEmpty(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
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

        $copier = $this->createCopier(componentRegistrar: $this->createModuleRegistrar([]));

        $copier->copy('/module/web/js/source.js', '/theme/dir/source.js', '');

        $this->assertStringContainsString('MageForge Template Override', $captured ?? '');
        $this->assertStringNotContainsString('Override For', $captured ?? '');
        $this->assertStringNotContainsString('Module-Version', $captured ?? '');
    }

    public function testPhpDocHeaderOmitsModuleLinesWhenNoModuleNameGiven(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy('/magento/vendor/module/view/frontend/templates/widget.phtml', '/theme/dir/widget.phtml');

        $this->assertStringContainsString('@module Vendor_Module', $captured ?? '');
        $this->assertStringNotContainsString('@override-for', $captured ?? '');
    }

    public function testNoOverrideForWhenActualModuleMatchesLogicalModule(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => '/magento/vendor/module']),
        );

        $copier->copy(
            '/magento/vendor/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertStringContainsString('@module Vendor_Module', $captured ?? '');
        $this->assertStringNotContainsString('@override-for', $captured ?? '');
    }

    public function testResolvesModuleWithWindowsStylePaths(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $packageInfo = $this->createMock(PackageInfo::class);
        $packageInfo->expects($this->once())->method('getVersion')->with('Vendor_Module')->willReturn('1.2.3');
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
        $copier = $this->createCopier(
            packageInfo: $packageInfo,
            componentRegistrar: $this->createModuleRegistrar(['Vendor_Module' => 'C:\\magento\\vendor\\module\\']),
        );

        $copier->copy(
            'C:\\magento\\vendor\\module\\web\\js\\source.js',
            '/theme/dir/source.js',
            'Vendor_Module',
        );

        $this->assertStringContainsString('Source Module: Vendor_Module', $captured ?? '');
    }

    public function testHeaderIsPrependedWhenPhpTagAppearsLaterInTemplate(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn("<div>\n<?php echo 'x';\n</div>");
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $copier = $this->createCopier(componentRegistrar: $this->createModuleRegistrar([]));

        $copier->copy(
            '/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertStringStartsWith("<?php\n/**\n * @mageforge-template-override", $captured ?? '');
        $this->assertStringEndsWith("<div>\n<?php echo 'x';\n</div>", $captured ?? '');
    }

    public function testHeaderIsInjectedAfterPhpOpenTagPrecededByBlankLine(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->fileDriver->method('getParentDirectory')->willReturn('/theme/dir');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn("\n<?php echo 'x';\n");
        $captured = null;
        $this->fileDriver
            ->method('filePutContents')
            ->willReturnCallback(static function (string $path, string $content) use (&$captured): bool {
                $captured = $content;
                return true;
            });

        $copier = $this->createCopier(componentRegistrar: $this->createModuleRegistrar([]));

        $copier->copy(
            '/module/view/frontend/templates/widget.phtml',
            '/theme/dir/widget.phtml',
            'Vendor_Module',
        );

        $this->assertStringStartsWith("\n<?php\n/**\n * @mageforge-template-override", $captured ?? '');
        $this->assertStringEndsWith("echo 'x';\n", $captured ?? '');
    }
}
