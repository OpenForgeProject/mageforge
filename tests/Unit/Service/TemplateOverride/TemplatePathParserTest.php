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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplatePathParserTest extends TestCase
{
    /**
     * @var ComponentRegistrarInterface&MockObject
     */
    private MockObject $componentRegistrar;

    /**
     * @var CompatModuleResolver&MockObject
     */
    private MockObject $compatModuleResolver;

    /**
     * @var File&MockObject
     */
    private MockObject $fileDriver;

    /**
     * @var DirectoryList&MockObject
     */
    private MockObject $directoryList;

    /**
     * @var TemplatePathParser
     */
    private TemplatePathParser $parser;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->compatModuleResolver = $this->createMock(CompatModuleResolver::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->directoryList->method('getRoot')->willReturn('/magento');
        $this->parser = new TemplatePathParser(
            $this->componentRegistrar,
            $this->compatModuleResolver,
            $this->fileDriver,
            $this->directoryList,
        );
    }

    public function testParsesModuleNotation(): void
    {
        $this->componentRegistrar
            ->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'Magento_Catalog')
            ->willReturn('/magento/vendor/magento/module-catalog');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse('Magento_Catalog::product/view/details.phtml');

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
        $this->compatModuleResolver
            ->method('getOriginalModules')
            ->with('Hyva_AmastyLabelCompat')
            ->willReturn(['Amasty_Label']);

        $reference = $this->parser->parse('Hyva_AmastyLabelCompat::label/product.phtml');

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
        $this->fileDriver->method('isFile')->with($file)->willReturn(true);
        $this->fileDriver->method('getRealPath')->with($file)->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testParsesCompatModuleFilePathToOriginalModule(): void
    {
        $file = '/magento/vendor/hyva-themes/compat/src/view/frontend/templates/label/product.phtml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Hyva_AmastyLabelCompat' => '/magento/vendor/hyva-themes/compat/src']);
        $this->compatModuleResolver
            ->method('getOriginalModules')
            ->with('Hyva_AmastyLabelCompat')
            ->willReturn(['Amasty_Label']);

        $reference = $this->parser->parse($file);

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
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn([
                'Acme_Base' => '/magento/vendor/acme/module-base',
                'Acme_Sub' => '/magento/vendor/acme/module-base/sub-module',
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

        $this->assertSame('Acme_Sub', $reference->getModuleName());
        $this->assertSame('widget.phtml', $reference->getTemplatePath());
    }

    public function testResolvesRelativePathAgainstMagentoRoot(): void
    {
        $relative = 'vendor/magento/module-catalog/view/frontend/templates/product/view/details.phtml';
        $absolute = '/magento/' . $relative;
        $this->fileDriver
            ->method('isFile')
            ->willReturnCallback(static fn (string $path): bool => $path === $absolute);
        $this->fileDriver->method('getRealPath')->with($absolute)->willReturn($absolute);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($relative);

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
        $this->fileDriver->method('isFile')->with($file)->willReturn(true);
        $this->fileDriver->method('getRealPath')->with($file)->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Sales' => '/magento/vendor/magento/module-sales']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

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

    public function testRejectsMissingFile(): void
    {
        $this->fileDriver->method('isFile')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Template file '\/nowhere\/file\.phtml' not found/");

        $this->parser->parse('/nowhere/file.phtml');
    }

    public function testParsesStaticModuleFilePath(): void
    {
        $file = '/magento/vendor/magento/module-theme/view/frontend/web/css/source/_module.less';
        $this->fileDriver->method('isFile')->with($file)->willReturn(true);
        $this->fileDriver->method('getRealPath')->with($file)->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Theme' => '/magento/vendor/magento/module-theme']);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

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

    public function testRejectsModuleFileOutsideTemplatesDirectory(): void
    {
        $file = '/magento/vendor/magento/module-catalog/view/frontend/layout/default.xml';
        $this->fileDriver->method('isFile')->willReturn(true);
        $this->fileDriver->method('getRealPath')->willReturn($file);
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::MODULE)
            ->willReturn(['Magento_Catalog' => '/magento/vendor/magento/module-catalog']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/not inside a view\/<area>\/templates, view\/<area>\/email or view\/<area>\/web directory/',
        );

        $this->parser->parse($file);
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
        $this->componentRegistrar
            ->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'Magento_Catalog')
            ->willReturn('/magento/vendor/magento/module-catalog');
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse(' Magento_Catalog ::product/view/details.phtml ');

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('product/view/details.phtml', $reference->getTemplatePath());
    }

    public function testParsesFilePathWithBackslashes(): void
    {
        $file = '\\magento\\vendor\\magento\\module-catalog\\view\\frontend\\templates\\widget.phtml';
        $normalized = '/magento/vendor/magento/module-catalog/view/frontend/templates/widget.phtml';
        $this->fileDriver->method('isFile')->willReturnCallback(static fn(string $path): bool => $path === $normalized);
        $this->fileDriver->method('getRealPath')->with($normalized)->willReturn($normalized);
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturnMap([
                [ComponentRegistrar::MODULE, ['Magento_Catalog' => '/magento/vendor/magento/module-catalog']],
                [ComponentRegistrar::THEME, []],
            ]);
        $this->compatModuleResolver->method('getOriginalModules')->willReturn([]);

        $reference = $this->parser->parse($file);

        $this->assertSame('Magento_Catalog', $reference->getModuleName());
        $this->assertSame('widget.phtml', $reference->getTemplatePath());
    }

    public function testFileNotFoundMessageContainsBothHints(): void
    {
        $this->fileDriver->method('isFile')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Pass an existing file path.*Module_Name::path\/to\/template\.phtml/s');

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
