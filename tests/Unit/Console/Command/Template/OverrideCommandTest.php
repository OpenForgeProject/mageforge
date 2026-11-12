<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Template;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Template\OverrideCommand;
use OpenForgeProject\MageForge\Model\TemplateReference;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\TemplateOverride\AreaEmulator;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateCopier;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplateFallbackResolver;
use OpenForgeProject\MageForge\Service\TemplateOverride\TemplatePathParser;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride\FakeTheme;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class OverrideCommandTest extends TestCase
{
    private const THEME_DIR = '/magento/app/design/frontend/Vendor/theme/Magento_Catalog/templates';
    private const MODULE_DIR = '/magento/vendor/magento/module-catalog/view/frontend/templates';

    /**
     * @var ThemeList&Stub
     */
    private Stub $themeList;

    /**
     * @var ThemeSuggester&Stub
     */
    private Stub $themeSuggester;

    /**
     * @var TemplatePathParser&Stub
     */
    private Stub $templatePathParser;

    /**
     * @var TemplateFallbackResolver&Stub
     */
    private Stub $fallbackResolver;

    /**
     * @var TemplateCopier&Stub
     */
    private Stub $templateCopier;

    /**
     * @var AreaEmulator&Stub
     */
    private Stub $areaEmulator;

    /**
     * @var CacheCleaner&Stub
     */
    private Stub $cacheCleaner;

    /**
     * @var DirectoryList&Stub
     */
    private Stub $directoryList;

    /**
     * @var OverrideCommand
     */
    private OverrideCommand $command;

    protected function setUp(): void
    {
        $this->themeList = $this->createStub(ThemeList::class);
        $this->themeSuggester = $this->createStub(ThemeSuggester::class);
        $this->templatePathParser = $this->createStub(TemplatePathParser::class);
        $this->fallbackResolver = $this->createStub(TemplateFallbackResolver::class);
        $this->templateCopier = $this->createStub(TemplateCopier::class);
        $this->areaEmulator = $this->createStub(AreaEmulator::class);
        $this->cacheCleaner = $this->createStub(CacheCleaner::class);
        $this->directoryList = $this->createStub(DirectoryList::class);
        $this->directoryList->method('getRoot')->willReturn('/magento');
        $this->command = $this->createCommand();
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:template:override', $this->command->getName());
        $this->assertSame(['template:override'], $this->command->getAliases());
    }

    public function testWarnsWhenNoFrontendThemesExist(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('No frontend themes found.', $tester->getDisplay());
    }

    public function testMissingTemplateArgumentShowsUsageInNonInteractiveMode(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([new FakeTheme('Vendor/theme')]);
        $templatePathParser = $this->createMock(TemplatePathParser::class);
        $templatePathParser->expects($this->never())->method('parse');

        $tester = new CommandTester($this->createCommand(templatePathParser: $templatePathParser));
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Usage: bin/magento mageforge:template:override', $display);
        $this->assertStringContainsString('Magento_Catalog::product/view/details.phtml', $display);
    }

    public function testMissingThemeOptionListsThemesInNonInteractiveMode(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeTheme('Vendor/theme'),
            new FakeTheme('Vendor/other'),
        ]);
        $areaEmulator = $this->createMock(AreaEmulator::class);
        $areaEmulator->expects($this->never())->method('emulate');

        $tester = new CommandTester($this->createCommand(areaEmulator: $areaEmulator));
        $exitCode = $tester->execute(['template' => 'Magento_Catalog::product/view/details.phtml']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No theme specified. Available frontend themes:', $display);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('Vendor/other', $display);
    }

    public function testUnknownThemeWithoutSuggestionsFails(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([new FakeTheme('Vendor/theme')]);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/unknown',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString("Theme 'Vendor/unknown' is not installed", $tester->getDisplay());
    }

    public function testAdminhtmlThemesAreNotOfferedAsTargets(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeTheme('Magento/backend', 'adminhtml'),
        ]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('No frontend themes found.', $tester->getDisplay());
    }

    public function testCopiesTemplateAndCleansCache(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver
            ->method('findFirstExistingFile')
            ->willReturnOnConsecutiveCalls(
                self::MODULE_DIR . '/product/view/details.phtml',
                self::THEME_DIR . '/product/view/details.phtml',
            );
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier
            ->expects($this->once())
            ->method('copy')
            ->with(
                self::MODULE_DIR . '/product/view/details.phtml',
                self::THEME_DIR . '/product/view/details.phtml',
                'Magento_Catalog',
            );
        $areaEmulator = $this->createMock(AreaEmulator::class);
        $areaEmulator->expects($this->once())->method('emulate')->with('frontend');
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->once())->method('clean')->willReturn(true);

        $tester = new CommandTester($this->createCommand(
            templateCopier: $templateCopier,
            areaEmulator: $areaEmulator,
            cacheCleaner: $cacheCleaner,
        ));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Magento_Catalog::product/view/details.phtml', $display);
        $this->assertStringContainsString(
            'app/design/frontend/Vendor/theme/Magento_Catalog/templates/product/view/details.phtml',
            $display,
        );
        $this->assertStringContainsString('Template override created:', $display);
    }

    public function testDryRunShowsFallbackOrderWithoutCopying(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver
            ->method('findFirstExistingFile')
            ->willReturn(self::MODULE_DIR . '/product/view/details.phtml');
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier->expects($this->never())->method('copy');
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->never())->method('clean');

        $tester = new CommandTester($this->createCommand(
            templateCopier: $templateCopier,
            cacheCleaner: $cacheCleaner,
        ));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
            '--dry-run' => true,
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Fallback search order', $display);
        $this->assertStringContainsString('override target', $display);
        $this->assertStringContainsString('current source', $display);
        $this->assertStringContainsString('Dry run: no files were copied.', $display);
    }

    public function testExistingOverrideIsNotReplacedWithoutForce(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver
            ->method('findFirstExistingFile')
            ->willReturn(self::THEME_DIR . '/product/view/details.phtml');
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier->expects($this->never())->method('copy');

        $tester = new CommandTester($this->createCommand(templateCopier: $templateCopier));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('already overridden in this theme', $display);
        $this->assertStringContainsString('Use --force', $display);
    }

    public function testForceReplacesExistingOverrideFromNextFallbackSource(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver
            ->method('findFirstExistingFile')
            ->willReturnOnConsecutiveCalls(
                self::THEME_DIR . '/product/view/details.phtml',
                self::MODULE_DIR . '/product/view/details.phtml',
                self::THEME_DIR . '/product/view/details.phtml',
            );
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier
            ->expects($this->once())
            ->method('copy')
            ->with(
                self::MODULE_DIR . '/product/view/details.phtml',
                self::THEME_DIR . '/product/view/details.phtml',
                'Magento_Catalog',
            );
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->once())->method('clean')->willReturn(true);

        $tester = new CommandTester($this->createCommand(
            templateCopier: $templateCopier,
            cacheCleaner: $cacheCleaner,
        ));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
            '--force' => true,
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Replacing the existing override (--force).', $display);
        $this->assertStringContainsString('Template override created:', $display);
    }

    public function testFailsWithWarningWhenCacheCleaningFails(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver
            ->method('findFirstExistingFile')
            ->willReturnOnConsecutiveCalls(
                self::MODULE_DIR . '/product/view/details.phtml',
                self::THEME_DIR . '/product/view/details.phtml',
            );
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier->expects($this->once())->method('copy');
        $this->cacheCleaner->method('clean')->willReturn(false);

        $tester = new CommandTester($this->createCommand(templateCopier: $templateCopier));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('cleaning the caches failed', $display);
        $this->assertStringNotContainsString('Template override created:', $display);
    }

    public function testFailsWhenTemplateIsNotFoundInAnyFallbackLocation(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver->method('findFirstExistingFile')->willReturn(null);
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier->expects($this->never())->method('copy');

        $tester = new CommandTester($this->createCommand(templateCopier: $templateCopier));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('was not found in any fallback location', $display);
        $this->assertStringContainsString('Fallback search order', $display);
    }

    public function testWarnsWhenVerificationDoesNotResolveToNewOverride(): void
    {
        $this->setUpResolvableTemplate();
        $this->fallbackResolver
            ->method('findFirstExistingFile')
            ->willReturn(self::MODULE_DIR . '/product/view/details.phtml');
        $templateCopier = $this->createMock(TemplateCopier::class);
        $templateCopier->expects($this->once())->method('copy');
        $cacheCleaner = $this->createMock(CacheCleaner::class);
        $cacheCleaner->expects($this->never())->method('clean');

        $tester = new CommandTester($this->createCommand(
            templateCopier: $templateCopier,
            cacheCleaner: $cacheCleaner,
        ));
        $exitCode = $tester->execute([
            'template' => 'Magento_Catalog::product/view/details.phtml',
            '--theme' => 'Vendor/theme',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('Magento resolves the template to', $tester->getDisplay());
    }

    public function testReportsParserErrors(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([new FakeTheme('Vendor/theme')]);
        $this->templatePathParser
            ->method('parse')
            ->willThrowException(new \InvalidArgumentException("Module 'Unknown_Module' is not registered."));

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            'template' => 'Unknown_Module::some/template.phtml',
            '--theme' => 'Vendor/theme',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString("Module 'Unknown_Module' is not registered.", $tester->getDisplay());
    }

    private function createCommand(
        ?ThemeList $themeList = null,
        ?ThemeSuggester $themeSuggester = null,
        ?TemplatePathParser $templatePathParser = null,
        ?TemplateFallbackResolver $fallbackResolver = null,
        ?TemplateCopier $templateCopier = null,
        ?AreaEmulator $areaEmulator = null,
        ?CacheCleaner $cacheCleaner = null,
        ?DirectoryList $directoryList = null,
    ): OverrideCommand {
        return new OverrideCommand(
            $themeList ?? $this->themeList,
            $themeSuggester ?? $this->themeSuggester,
            $templatePathParser ?? $this->templatePathParser,
            $fallbackResolver ?? $this->fallbackResolver,
            $templateCopier ?? $this->templateCopier,
            $areaEmulator ?? $this->areaEmulator,
            $cacheCleaner ?? $this->cacheCleaner,
            $directoryList ?? $this->directoryList,
        );
    }

    /**
     * Configure stubs for a template that resolves to the fake theme and module directories
     */
    private function setUpResolvableTemplate(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([new FakeTheme('Vendor/theme')]);
        $this->templatePathParser
            ->method('parse')
            ->willReturn(new TemplateReference('Magento_Catalog', 'product/view/details.phtml'));
        $this->fallbackResolver
            ->method('getFallbackDirs')
            ->willReturn([self::THEME_DIR, self::MODULE_DIR]);
        $this->fallbackResolver->method('getThemeTargetDir')->willReturn(self::THEME_DIR);
    }
}
