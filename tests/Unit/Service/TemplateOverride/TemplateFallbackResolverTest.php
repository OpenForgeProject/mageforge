<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Design\Fallback\Rule\RuleInterface;
use Magento\Framework\View\Design\Fallback\RulePool;
use Magento\Framework\View\DesignInterface;
use OpenForgeProject\MageForge\Model\TemplateReference;
use OpenForgeProject\MageForge\Model\TemplateType;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateFallbackResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateFallbackResolverTest extends TestCase
{
    private ObjectManagerInterface&MockObject $objectManager;
    private DesignInterface&MockObject $design;
    private ComponentRegistrarInterface&MockObject $componentRegistrar;
    private File&MockObject $fileDriver;
    private TemplateFallbackResolver $resolver;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->design = $this->createMock(DesignInterface::class);
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->resolver = new TemplateFallbackResolver(
            $this->objectManager,
            $this->design,
            $this->componentRegistrar,
            $this->fileDriver,
        );
    }

    public function testGetFallbackDirsUsesMagentoRuleAndSetsDesignTheme(): void
    {
        $theme = new FakeTheme('Vendor/theme');
        $reference = new TemplateReference('Magento_Catalog', 'product/view/details.phtml');

        $rule = $this->createMock(RuleInterface::class);
        $rule->expects($this->once())
            ->method('getPatternDirs')
            ->with([
                'area' => 'frontend',
                'theme' => $theme,
                'module_name' => 'Magento_Catalog',
                'file' => 'product/view/details.phtml',
            ])
            ->willReturn([
                '/theme/Magento_Catalog/templates',
                '/compat/view/frontend/templates',
                '/module/view/frontend/templates',
                '/theme/Magento_Catalog/templates',
            ]);

        $rulePool = $this->createMock(RulePool::class);
        $rulePool->method('getRule')->with(RulePool::TYPE_TEMPLATE_FILE)->willReturn($rule);

        $this->objectManager->method('create')->with(RulePool::class)->willReturn($rulePool);
        $this->design->expects($this->once())->method('setDesignTheme')->with($theme, 'frontend');

        $dirs = $this->resolver->getFallbackDirs($reference, $theme);

        $this->assertSame(
            [
                '/theme/Magento_Catalog/templates',
                '/compat/view/frontend/templates',
                '/module/view/frontend/templates',
            ],
            $dirs,
            'Duplicate directories must be removed while preserving the fallback order',
        );
    }

    public function testGetFallbackDirsUsesEmailRuleForEmailTemplates(): void
    {
        $theme = new FakeTheme('Vendor/theme');
        $reference = new TemplateReference('Magento_Sales', 'order/new.html', TemplateType::EMAIL);

        $rule = $this->createMock(RuleInterface::class);
        $rule->expects($this->once())
            ->method('getPatternDirs')
            ->with([
                'area' => 'frontend',
                'theme' => $theme,
                'module_name' => 'Magento_Sales',
                'file' => 'order/new.html',
            ])
            ->willReturn([
                '/theme/Magento_Sales/email',
                '/module/view/frontend/email',
            ]);

        $rulePool = $this->createMock(RulePool::class);
        $rulePool->method('getRule')->with(RulePool::TYPE_EMAIL_TEMPLATE)->willReturn($rule);

        $this->objectManager->method('create')->with(RulePool::class)->willReturn($rulePool);
        $this->design->expects($this->once())->method('setDesignTheme')->with($theme, 'frontend');

        $dirs = $this->resolver->getFallbackDirs($reference, $theme);

        $this->assertSame(
            [
                '/theme/Magento_Sales/email',
                '/module/view/frontend/email',
            ],
            $dirs,
        );
    }

    public function testGetFallbackDirsUsesStaticRuleForStaticFiles(): void
    {
        $theme = new FakeTheme('Vendor/theme');
        $reference = new TemplateReference('Magento_Theme', 'css/source/_module.less', TemplateType::STATIC);

        $rule = $this->createMock(RuleInterface::class);
        $rule->expects($this->once())
            ->method('getPatternDirs')
            ->with([
                'area' => 'frontend',
                'theme' => $theme,
                'module_name' => 'Magento_Theme',
                'file' => 'css/source/_module.less',
            ])
            ->willReturn([
                '/theme/Magento_Theme/web',
                '/module/view/frontend/web',
            ]);

        $rulePool = $this->createMock(RulePool::class);
        $rulePool->method('getRule')->with(RulePool::TYPE_STATIC_FILE)->willReturn($rule);

        $this->objectManager->method('create')->with(RulePool::class)->willReturn($rulePool);
        $this->design->expects($this->once())->method('setDesignTheme')->with($theme, 'frontend');

        $dirs = $this->resolver->getFallbackDirs($reference, $theme);

        $this->assertSame(
            [
                '/theme/Magento_Theme/web',
                '/module/view/frontend/web',
            ],
            $dirs,
        );
    }

    public function testGetFallbackDirsFailsWhenRulePoolCannotBeCreated(): void
    {
        $this->objectManager->method('create')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->resolver->getFallbackDirs(new TemplateReference('A_B', 'x.phtml'), new FakeTheme('Vendor/theme'));
    }

    public function testFindFirstExistingFileReturnsFirstMatch(): void
    {
        $this->fileDriver
            ->method('isFile')
            ->willReturnCallback(static fn (string $path): bool => str_starts_with($path, '/module/'));

        $result = $this->resolver->findFirstExistingFile(
            ['/theme/Magento_Catalog/templates', '/module/view/frontend/templates'],
            'product/view/details.phtml',
        );

        $this->assertSame('/module/view/frontend/templates/product/view/details.phtml', $result);
    }

    public function testFindFirstExistingFileSkipsExcludedDirectory(): void
    {
        $this->fileDriver->method('isFile')->willReturn(true);

        $result = $this->resolver->findFirstExistingFile(
            ['/theme/Magento_Catalog/templates', '/module/view/frontend/templates'],
            'details.phtml',
            '/theme/Magento_Catalog/templates',
        );

        $this->assertSame('/module/view/frontend/templates/details.phtml', $result);
    }

    public function testFindFirstExistingFileReturnsNullWhenNothingExists(): void
    {
        $this->fileDriver->method('isFile')->willReturn(false);

        $result = $this->resolver->findFirstExistingFile(['/a', '/b'], 'details.phtml');

        $this->assertNull($result);
    }

    public function testGetThemeTargetDirReturnsDirInsideTheme(): void
    {
        $theme = new FakeTheme('Vendor/theme');
        $this->componentRegistrar
            ->method('getPath')
            ->with(ComponentRegistrar::THEME, 'frontend/Vendor/theme')
            ->willReturn('/app/design/frontend/Vendor/theme');

        $result = $this->resolver->getThemeTargetDir(
            [
                '/app/design/frontend/Vendor/theme/Magento_Catalog/templates',
                '/module/view/frontend/templates',
            ],
            $theme,
        );

        $this->assertSame('/app/design/frontend/Vendor/theme/Magento_Catalog/templates', $result);
    }

    public function testGetThemeTargetDirReturnsNullForUnregisteredTheme(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn(null);

        $this->assertNull($this->resolver->getThemeTargetDir(['/some/dir'], new FakeTheme('Vendor/theme')));
    }

    public function testGetThemeTargetDirReturnsNullWhenNoDirIsInsideTheme(): void
    {
        $this->componentRegistrar->method('getPath')->willReturn('/app/design/frontend/Vendor/theme');

        $result = $this->resolver->getThemeTargetDir(
            ['/module/view/frontend/templates', '/app/design/frontend/Vendor/other-theme/Magento_Catalog/templates'],
            new FakeTheme('Vendor/theme'),
        );

        $this->assertNull($result);
    }
}
