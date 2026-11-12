<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Model\TemplateType;
use OpenForgeProject\MageForge\Service\TemplateOverride\CompatModuleResolver;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplatePathParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TemplatePathParserTest extends TestCase
{
    /**
     * @var ComponentRegistrarInterface&Stub
     */
    private Stub $componentRegistrar;

    /**
     * @var CompatModuleResolver&Stub
     */
    private Stub $compatModuleResolver;

    /**
     * @var File&Stub
     */
    private Stub $fileDriver;

    /**
     * @var DirectoryList&Stub
     */
    private Stub $directoryList;

    /**
     * @var TemplatePathParser
     */
    private TemplatePathParser $parser;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createStub(ComponentRegistrarInterface::class);
        $this->compatModuleResolver = $this->createStub(CompatModuleResolver::class);
        $this->fileDriver = $this->createStub(File::class);
        $this->directoryList = $this->createStub(DirectoryList::class);
        $this->directoryList->method('getRoot')->willReturn('/magento');
        $this->parser = $this->createParser();
    }

    private function createParser(
        ?ComponentRegistrarInterface $componentRegistrar = null,
        ?CompatModuleResolver $compatModuleResolver = null,
        ?File $fileDriver = null,
    ): TemplatePathParser {
        return new TemplatePathParser(
            $componentRegistrar ?? $this->componentRegistrar,
            $compatModuleResolver ?? $this->compatModuleResolver,
            $fileDriver ?? $this->fileDriver,
            $this->directoryList,
        );
    }

    public function testParsesModuleNotation(): void
    {
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'Magento_Catalog')
            ->willReturn('/magento/vendor/magento/module-catalog');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar);

        $reference = $parser->parse('Magento_Catalog::product/view/details.phtml');

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testNormalizesLeadingSlashesAndBackslashesInModuleNotation(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Vendor_Module::/product\\view/details.phtml');

        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template reference must not be empty.');

        $this->parser->parse('   ');
    }

    public function testRejectsModuleNotationWithoutPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Expected format: Module_Name::path\/to\/template\.phtml/');

        $this->parser->parse('Magento_Catalog::');
    }

    public function testRejectsUnknownModule(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Module 'Unknown_Module' is not registered/");

        $this->parser->parse('Unknown_Module::some/template.phtml');
    }

    public function testRejectsRelativePathSegments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must not contain relative path segments/');

        $this->parser->parse('Magento_Catalog::../../../etc/env.phtml');
    }

    public function testRejectsTrailingParentDirectorySegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must not contain relative path segments/');

        $this->parser->parse('Magento_Catalog::product/..');
    }

    public function testRejectsCurrentDirectorySegment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must not contain relative path segments/');

        $this->parser->parse('Magento_Catalog::product/./details.phtml');
    }

    public function testCollapsesRepeatedSlashesInTemplatePath(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Magento_Catalog::product//view///details.phtml');

        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testMapsCompatModuleNotationToOriginalModule(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/compat-module');
        $compatModuleResolver = $this->createMock(CompatModuleResolver::class);
        $compatModuleResolver
            ->expects($this->once())
            ->method('getOriginalModules')
            ->with('Hyva_AmastyLabelCompat')
            ->willReturn(['Amasty_Label']);
        $parser = $this->createParser(compatModuleResolver: $compatModuleResolver);

        $reference = $parser->parse('Hyva_AmastyLabelCompat::label/product.phtml');

        $this->assertSame('Amasty_Label', $reference->getModuleName());
        $this->assertSame('label/product.phtml', $reference->getTemplatePath());
        $this->assertSame('Amasty_Label::label/product.phtml', $reference->getTemplateId());
    }

    public function testStripsOriginalModuleDirectoryFromCompatTemplatePath(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/compat-module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn(['Amasty_Label']);

        $reference = $this->parser->parse('Hyva_AmastyLabelCompat::Amasty_Label/label/product.phtml');

        $this->assertSame('Amasty_Label', $reference->getModuleName());
        $this->assertSame('label/product.phtml', $reference->getTemplatePath());
    }

    public function testRejectsAmbiguousCompatModuleWithoutModuleDirectory(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/compat-module');
        $this->compatModuleResolver
            ->method('getOriginalModules')
            ->willReturn(['Vendor_ModuleA', 'Vendor_ModuleB']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/compatibility module for several modules/');

        $this->parser->parse('Hyva_SharedCompat::some/template.phtml');
    }

    public function testResolvesAmbiguousCompatModuleViaModuleDirectoryPrefix(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/compat-module');
        $this->compatModuleResolver
            ->method('getOriginalModules')
            ->willReturn(['Vendor_ModuleA', 'Vendor_ModuleB']);

        $reference = $this->parser->parse('Hyva_SharedCompat::Vendor_ModuleB/some/template.phtml');

        $this->assertSame('Vendor_ModuleB', $reference->getModuleName());
        $this->assertSame('some/template.phtml', $reference->getTemplatePath());
    }

    public function testParsesModuleFilePath(): void
    {
        $file = '/magento/vendor/magento/module-catalog/view/frontend/templates/product/view/details.phtml';
        $fileDriver = $this->createMock(File::class);
        $fileDriver->expects($this->once())->method('isFile')->with($file)->willReturn(true);
        $fileDriver->expects($this->once())->method('getRealPath')->with($file)->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar, fileDriver: $fileDriver);

        $reference = $parser->parse($file);

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testParsesCompatModuleFilePathToOriginalModule(): void
    {
        $file = '/magento/vendor/hyva-themes/compat/src/view/frontend/templates/label/product.phtml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Hyva_AmastyLabelCompat' => '/magento/vendor/hyva-themes/compat/src']);
        $compatModuleResolver = $this->createMock(CompatModuleResolver::class);
        $compatModuleResolver
            ->expects($this->once())
            ->method('getOriginalModules')
            ->with('Hyva_AmastyLabelCompat')
            ->willReturn(['Amasty_Label']);
        $parser = $this->createParser(
            componentRegistrar: $componentRegistrar,
            compatModuleResolver: $compatModuleResolver,
        );

        $reference = $parser->parse($file);

        $this->assertSame('Amasty_Label::label/product.phtml', $reference->getTemplateId());
    }

    public function testParsesThemeFilePath(): void
    {
        $file = '/magento/app/design/frontend/Vendor/theme/Magento_Catalog/templates/product/view/details.phtml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, []],
                [ComponentRegistrar::THEME, ['frontend/Vendor/theme' => '/magento/app/design/frontend/Vendor/theme']],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testUsesLongestComponentPathMatch(): void
    {
        $file = '/magento/vendor/acme/module-base/sub-module/view/frontend/templates/widget.phtml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn([
                'Acme_Base' => '/magento/vendor/acme/module-base',
                'Acme_Sub' => '/magento/vendor/acme/module-base/sub-module',
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar);

        $reference = $parser->parse($file);

        $this->assertSame('Acme_Sub', $reference->getModuleName());
        $this->assertSame('widget.phtml', $reference->getTemplatePath());
    }

    public function testResolvesRelativePathAgainstMagentoRoot(): void
    {
        $relative = 'vendor/magento/module-catalog/view/frontend/templates/product/view/details.phtml';
        $absolute = '/magento/' . $relative;
        $fileDriver = $this->createMock(File::class);
        $fileDriver
            ->method('isFile')
            ->willReturnCallback(static fn (string $path): bool => $path === $absolute);
        $fileDriver->expects($this->once())->method('getRealPath')->with($absolute)->willReturn($absolute);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar, fileDriver: $fileDriver);

        $reference = $parser->parse($relative);

        $this->assertSame('Magento_Catalog::product/view/details.phtml', $reference->getTemplateId());
    }

    public function testParsesEmailTemplateFromModuleNotation(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Magento_Sales::order/new.html');

        $this->assertSame('Magento_Sales', $reference->getModuleName());
        $this->assertSame('order/new.html', $reference->getTemplatePath());
        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }

    public function testParsesPhtmlAsBlockTemplateInModuleNotation(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Magento_Sales::order/success.phtml');

        $this->assertSame(TemplateType::TEMPLATE, $reference->getType());
    }

    public function testParsesStaticFileFromModuleNotation(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Magento_Theme::css/source/_module.less');

        $this->assertSame('Magento_Theme', $reference->getModuleName());
        $this->assertSame('css/source/_module.less', $reference->getTemplatePath());
        $this->assertSame(TemplateType::STATIC, $reference->getType());
    }

    public function testParsesEmailModuleFilePath(): void
    {
        $file = '/magento/vendor/magento/module-sales/view/frontend/email/order/new.html';
        $fileDriver = $this->createMock(File::class);
        $fileDriver->expects($this->once())->method('isFile')->with($file)->willReturn(true);
        $fileDriver->expects($this->once())->method('getRealPath')->with($file)->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Sales' => '/magento/vendor/magento/module-sales']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar, fileDriver: $fileDriver);

        $reference = $parser->parse($file);

        $this->assertSame('Magento_Sales', $reference->getModuleName());
        $this->assertSame('order/new.html', $reference->getTemplatePath());
        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }

    public function testParsesEmailThemeFilePath(): void
    {
        $file = '/magento/app/design/frontend/Vendor/theme/Magento_Sales/email/order/new.html';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, []],
                [ComponentRegistrar::THEME, ['frontend/Vendor/theme' => '/magento/app/design/frontend/Vendor/theme']],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

        $this->assertSame('Magento_Sales', $reference->getModuleName());
        $this->assertSame('order/new.html', $reference->getTemplatePath());
        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }

    public function testParsesModuleDirectoryPath(): void
    {
        $directory = '/magento/vendor/magento/module-catalog/view/frontend/templates';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->with($directory)->willReturn(true);
        $this->fileDriver->method('getRealPath')->with($directory)->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('', $reference->getTemplatePath());
        $this->assertSame(TemplateType::TEMPLATE, $reference->getType());
    }

    public function testParsesThemeSubdirectoryPath(): void
    {
        $directory = '/magento/app/design/frontend/Vendor/theme/Magento_Sales/email/order';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, []],
                [ComponentRegistrar::THEME, ['frontend/Vendor/theme' => '/magento/app/design/frontend/Vendor/theme']],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('Magento_Sales', $reference->getModuleName());
        $this->assertSame('order', $reference->getTemplatePath());
        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }

    public function testParsesModuleSubdirectoryPathWithTrailingSlashFromRealPath(): void
    {
        $directory = '/magento/vendor/magento/module-catalog/view/frontend/templates/product/view';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory . '/');
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('product/view', $reference->getTemplatePath());
    }

    public function testParsesThemeDirectoryRootWithoutSubPath(): void
    {
        $directory = '/magento/app/design/frontend/Vendor/theme/Magento_Sales/email';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, []],
                [ComponentRegistrar::THEME, ['frontend/Vendor/theme' => '/magento/app/design/frontend/Vendor/theme']],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('', $reference->getTemplatePath());
        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }

    public function testMapsCompatModuleDirectoryNamedAfterOriginalModuleToItsRoot(): void
    {
        $directory = '/magento/vendor/hyva-themes/compat/view/frontend/templates/Magento_Catalog';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Hyva_CompatCatalog' => '/magento/vendor/hyva-themes/compat']);
        $this->compatModuleResolver
            ->method('getOriginalModules')
            ->with('Hyva_CompatCatalog')
            ->willReturn(['Magento_Catalog', 'Magento_Checkout']);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('', $reference->getTemplatePath());
    }

    public function testStripsOriginalModuleDirectoryFromCompatSubdirectoryPath(): void
    {
        $directory = '/magento/vendor/hyva-themes/compat/view/frontend/templates/Magento_Catalog/product';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Hyva_CompatCatalog' => '/magento/vendor/hyva-themes/compat']);
        $this->compatModuleResolver
            ->method('getOriginalModules')
            ->willReturn(['Magento_Catalog', 'Magento_Checkout']);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product', $reference->getTemplatePath());
    }

    public function testMapsSingleCompatModuleDirectoryToOriginalModule(): void
    {
        $directory = '/magento/vendor/hyva-themes/compat/view/frontend/templates/product';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Hyva_CompatCatalog' => '/magento/vendor/hyva-themes/compat']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn(['Magento_Catalog']);

        $reference = $this->parser->parse($directory);

        $this->assertTrue($reference->isDirectory());
        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product', $reference->getTemplatePath());
    }

    #[DataProvider('invalidModuleDirectoryProvider')]
    public function testRejectsModuleDirectoriesOutsideViewDirectories(string $relativePath): void
    {
        $directory = '/magento/vendor/magento/module-catalog/' . $relativePath;
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/is not inside a view/');

        $this->parser->parse($directory);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidModuleDirectoryProvider(): array
    {
        return [
            'prefix before view' => ['Block/view/frontend/templates'],
            'suffix after view dir' => ['view/frontend/templates-old'],
            'view area only' => ['view/frontend'],
        ];
    }

    #[DataProvider('invalidThemeDirectoryProvider')]
    public function testRejectsThemeDirectoriesOutsideModuleViewDirectories(string $relativePath): void
    {
        $directory = '/magento/app/design/frontend/Vendor/theme/' . $relativePath;
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, []],
                [ComponentRegistrar::THEME, ['frontend/Vendor/theme' => '/magento/app/design/frontend/Vendor/theme']],
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/is not a module template override/');

        $this->parser->parse($directory);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidThemeDirectoryProvider(): array
    {
        return [
            'prefix before module' => ['web/Magento_Catalog/templates'],
            'suffix after view dir' => ['Magento_Catalog/templates-old'],
            'module root only' => ['Magento_Catalog'],
        ];
    }

    public function testRejectsModuleDirectoryOutsideViewDirectories(): void
    {
        $directory = '/magento/vendor/magento/module-catalog/Model';
        $this->fileDriver->method('isFile')->willReturn(false);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($directory);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/is not inside a view/');

        $this->parser->parse($directory);
    }

    public function testRejectsMissingFile(): void
    {
        $this->fileDriver->method('isFile')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Template file or directory '\/nowhere\/file\.phtml' not found/");

        $this->parser->parse('/nowhere/file.phtml');
    }

    public function testParsesStaticModuleFilePath(): void
    {
        $file = '/magento/vendor/magento/module-theme/view/frontend/web/css/source/_module.less';
        $fileDriver = $this->createMock(File::class);
        $fileDriver->expects($this->once())->method('isFile')->with($file)->willReturn(true);
        $fileDriver->expects($this->once())->method('getRealPath')->with($file)->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Theme' => '/magento/vendor/magento/module-theme']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar, fileDriver: $fileDriver);

        $reference = $parser->parse($file);

        $this->assertSame('Magento_Theme', $reference->getModuleName());
        $this->assertSame('css/source/_module.less', $reference->getTemplatePath());
        $this->assertSame(TemplateType::STATIC, $reference->getType());
    }

    public function testParsesStaticThemeFilePath(): void
    {
        $file = '/magento/app/design/frontend/Vendor/theme/Magento_Theme/web/css/source/_module.less';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, []],
                [ComponentRegistrar::THEME, ['frontend/Vendor/theme' => '/magento/app/design/frontend/Vendor/theme']],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

        $this->assertSame('Magento_Theme', $reference->getModuleName());
        $this->assertSame('css/source/_module.less', $reference->getTemplatePath());
        $this->assertSame(TemplateType::STATIC, $reference->getType());
    }

    public function testParsesModuleLayoutXmlFile(): void
    {
        $file = '/magento/vendor/magento/module-catalog/view/frontend/layout/default.xml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar);

        $reference = $parser->parse($file);

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('default.xml', $reference->getTemplatePath());
        $this->assertSame(TemplateType::LAYOUT, $reference->getType());
    }

    public function testRejectsModuleFileOutsideTemplatesDirectory(): void
    {
        $file = '/magento/vendor/magento/module-catalog/etc/module.xml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/not inside a view\/<area>\/templates, view\/<area>\/email, '
            . 'view\/<area>\/web or view\/<area>\/layout directory/',
        );

        $parser->parse($file);
    }

    public function testRejectsFileOutsideAnyComponent(): void
    {
        $file = '/magento/pub/media/some-file.phtml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $this->componentRegistrar->method('getPaths')->willReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not belong to a registered module or theme/');

        $this->parser->parse($file);
    }

    public function testParsesModuleNotationWithWhitespaceAroundModuleName(): void
    {
        $componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $componentRegistrar
            ->expects($this->once())
            ->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'Magento_Catalog')
            ->willReturn('/magento/vendor/magento/module-catalog');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(componentRegistrar: $componentRegistrar);

        $reference = $parser->parse(' Magento_Catalog ::product/view/details.phtml ');

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testParsesFilePathWithBackslashes(): void
    {
        $file = '\\magento\\vendor\\magento\\module-catalog\\view\\frontend\\templates\\widget.phtml';
        $normalized = '/magento/vendor/magento/module-catalog/view/frontend/templates/widget.phtml';
        $fileDriver = $this->createMock(File::class);
        $fileDriver->method('isFile')->willReturnCallback(static fn(string $path): bool => $path === $normalized);
        $fileDriver->expects($this->once())->method('getRealPath')->with($normalized)->willReturn($normalized);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, ['Magento_Catalog' => '/magento/vendor/magento/module-catalog']],
                [ComponentRegistrar::THEME, []],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);
        $parser = $this->createParser(fileDriver: $fileDriver);

        $reference = $parser->parse($file);

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('widget.phtml', $reference->getTemplatePath());
    }

    public function testFileNotFoundMessageContainsBothHints(): void
    {
        $this->fileDriver->method('isFile')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/Pass an existing file or directory path.*Module_Name::path\/to\/template\.phtml/s',
        );

        $this->parser->parse('/nowhere/file.phtml');
    }

    public function testParsesUppercaseHtmlExtensionAsEmail(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/path/to/module');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Magento_Sales::order/new.HTML');

        $this->assertSame(TemplateType::EMAIL, $reference->getType());
    }
}
