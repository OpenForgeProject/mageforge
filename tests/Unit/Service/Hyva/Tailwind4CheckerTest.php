<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\Hyva\Tailwind4Checker;
use OpenForgeProject\MageForge\Service\Hyva\Tailwind4Finding;
use OpenForgeProject\MageForge\Service\Hyva\Tailwind4Severity;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Tailwind4CheckerTest extends TestCase
{
    /**
     * @var Shell&MockObject
     */
    private $shell;
    /**
     * @var ThemeList&MockObject
     */
    private $themeList;
    /**
     * @var ThemePath&MockObject
     */
    private $themePath;
    /**
     * @var BuilderPool&MockObject
     */
    private $builderPool;
    /**
     * @var ComponentRegistrarInterface&MockObject
     */
    private $componentRegistrar;
    /**
     * @var Tailwind4Checker
     */
    private Tailwind4Checker $checker;
    /**
     * @var string
     */
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mageforge-tw4-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0o777, true);

        $this->shell = $this->createMock(Shell::class);
        $this->themeList = $this->createMock(ThemeList::class);
        $this->themePath = $this->createMock(ThemePath::class);
        $this->builderPool = $this->createMock(BuilderPool::class);
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->checker = new Tailwind4Checker(
            new File(),
            $this->shell,
            $this->themeList,
            $this->themePath,
            $this->builderPool,
            $this->componentRegistrar,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testReadyThemeHasNoFindings(): void
    {
        $this->writeReadyTheme($this->root . '/theme');

        $this->assertSame([], $this->checker->checkTheme($this->root . '/theme'));
    }

    public function testDetectsTailwind3SetupInTheme(): void
    {
        $tailwind = $this->root . '/theme/web/tailwind';
        $this->write($tailwind . '/package.json', json_encode([
            'devDependencies' => [
                'tailwindcss' => '^3.4.0',
                'postcss' => '^8.0.0',
                '@tailwindcss/forms' => '^0.5.0',
            ],
            'scripts' => ['build' => 'postcss tailwind-source.css -o ../css/styles.css'],
        ], JSON_THROW_ON_ERROR));
        $this->write($tailwind . '/postcss.config.js', 'module.exports = {};');
        $this->write($tailwind . '/tailwind.config.js', 'module.exports = {};');
        $this->write($tailwind . '/tailwind-source.css', "@tailwind base;\n@tailwind utilities;\n");
        $this->write($tailwind . '/components/button.css', "@screen md {\n}\n@import 'theme/colors.css';\n");

        $messages = $this->messages($this->checker->checkTheme($this->root . '/theme'));

        $this->assertContains('tailwindcss ^3.4.0 is declared, Tailwind CSS v4 is required.', $messages);
        $this->assertContains('Packages no longer needed: postcss, @tailwindcss/forms', $messages);
        $this->assertContains('@tailwindcss/cli is not listed in package.json.', $messages);
        $this->assertContains('@hyva-themes/hyva-modules is not listed in package.json.', $messages);
        $this->assertContains('The "generate" script is missing.', $messages);
        $this->assertContains('The "build" script still uses PostCSS or tailwind.config.js.', $messages);
        $this->assertContains('The "build" script does not run "generate" first.', $messages);
        $this->assertContains('postcss.config.js is obsolete with Tailwind CSS v4.', $messages);
        $this->assertContains('tailwind.config.js is obsolete with Tailwind CSS v4.', $messages);
        $this->assertContains('hyva.config.json not found.', $messages);
        $this->assertContains('@tailwind base was removed in Tailwind CSS v4.', $messages);
        $this->assertNotContains('@tailwind utilities was removed in Tailwind CSS v4.', $messages);
        $this->assertContains('The entry file does not import Tailwind CSS v4.', $messages);
        $this->assertContains('@screen was removed in Tailwind CSS v4.', $messages);
        $this->assertContains('@import "theme/colors.css" is not a relative path.', $messages);
    }

    public function testAcceptsSplitTailwindImportsAndUtilitiesPath(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $tailwind = $this->root . '/theme/web/tailwind';
        $this->write(
            $tailwind . '/tailwind-source.css',
            <<<'CSS'
@import "@hyva-themes/hyva-modules/css";
@import "tailwindcss/theme.css" layer(theme);
@import "tailwindcss/utilities.css" layer(utilities) source(none);
@source "../../**/*.phtml";
@import "./generated/hyva-source.css";
@import "./generated/hyva-tokens.css";
CSS,
        );

        $this->assertSame([], $this->checker->checkTheme($this->root . '/theme'));
    }

    public function testFlagsRemovedTailwindImportPaths(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $this->write(
            $this->root . '/theme/web/tailwind/base/reset.css',
            "@import \"tailwindcss/base\";\n@import \"tailwindcss/components.css\";\n",
        );

        $messages = $this->messages($this->checker->checkTheme($this->root . '/theme'));

        $this->assertSame(
            [
                '"tailwindcss/base" does not exist in Tailwind CSS v4.',
                '"tailwindcss/components.css" does not exist in Tailwind CSS v4.',
            ],
            $messages,
        );
    }

    public function testIgnoresDirectivesInMultilineAndInlineComments(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $this->write(
            $this->root . '/theme/web/tailwind/components/card.css',
            "/*\n@screen md {\n}\n@tailwind base;\n*/\n.a { color: red; } /* @import 'x.css'; */\n@screen sm {\n}\n",
        );

        $findings = $this->checker->checkTheme($this->root . '/theme');

        $this->assertCount(1, $findings);
        $this->assertSame('components/card.css:7', $findings[0]->getLocation());
    }

    public function testCommentedOutEntryImportIsNotCountedAsConfigured(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $this->write(
            $this->root . '/theme/web/tailwind/tailwind-source.css',
            "/* @import \"tailwindcss\" source(none); */\n@import \"@hyva-themes/hyva-modules/css\";\n"
            . "@import \"./generated/hyva-source.css\";\n@import \"./generated/hyva-tokens.css\";\n",
        );

        $messages = $this->messages($this->checker->checkTheme($this->root . '/theme'));

        $this->assertSame(['The entry file does not import Tailwind CSS v4.'], $messages);
    }

    public function testRangesThatCannotBeEvaluatedAreNotReportedAsReady(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $this->setTailwindRange($this->root . '/theme', '<4.0.0');

        $messages = $this->messages($this->checker->checkTheme($this->root . '/theme'));

        $this->assertSame(['The tailwindcss range "<4.0.0" cannot be evaluated.'], $messages);
    }

    public function testAcceptsLatestTagAndSimpleV4Ranges(): void
    {
        $this->writeReadyTheme($this->root . '/theme');

        foreach (['latest', '~4.0', '>=4.1.0', '4.1.x', 'v4.0.0'] as $range) {
            $this->setTailwindRange($this->root . '/theme', $range);
            $this->assertSame([], $this->checker->checkTheme($this->root . '/theme'), $range);
        }
    }

    public function testWarnsWhenGenerateIsNotRunByBuildAndWatch(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $this->removeScripts($this->root . '/theme', ['prebuild', 'prewatch']);

        $messages = $this->messages($this->checker->checkTheme($this->root . '/theme'));

        $this->assertSame(
            ['The "build" script does not run "generate" first.', 'The "watch" script does not run "generate" first.'],
            $messages,
        );
    }

    public function testReportsFileAndLineOfCssFindings(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $this->write($this->root . '/theme/web/tailwind/components/card.css', "/* @screen md */\n\n@screen md {\n}\n");

        $findings = $this->checker->checkTheme($this->root . '/theme');

        $this->assertCount(1, $findings);
        $this->assertSame('components/card.css:3', $findings[0]->getLocation());
    }

    public function testIgnoresGeneratedAndDependencyCss(): void
    {
        $this->writeReadyTheme($this->root . '/theme');
        $tailwind = $this->root . '/theme/web/tailwind';
        $this->write($tailwind . '/node_modules/pkg/index.css', '@screen md {}');
        $this->write($tailwind . '/generated/hyva-source.css', '@screen md {}');

        $this->assertSame([], $this->checker->checkTheme($this->root . '/theme'));
    }

    public function testFlagsThemeWithoutTailwindDirectory(): void
    {
        mkdir($this->root . '/theme', 0o777, true);

        $findings = $this->checker->checkTheme($this->root . '/theme');

        $this->assertCount(1, $findings);
        $this->assertSame(Tailwind4Severity::Error, $findings[0]->severity);
    }

    public function testChecksModuleTailwindCssOnly(): void
    {
        $this->write(
            $this->root . '/module/view/frontend/tailwind/tailwind-source.css',
            "@tailwind base;\n@screen md {}\n",
        );
        $this->write($this->root . '/module/view/frontend/tailwind/tailwind.config.js', 'module.exports = {};');

        $messages = $this->messages($this->checker->checkModule($this->root . '/module'));

        $this->assertSame(
            ['@tailwind base was removed in Tailwind CSS v4.', '@screen was removed in Tailwind CSS v4.'],
            $messages,
        );
    }

    public function testDetectsHyvaGrandchildThroughItsAncestors(): void
    {
        $this->writeReadyTheme($this->root . '/app/design/Vendor/grandchild');
        $this->givenHyvaThemes(
            ['Vendor/grandchild' => $this->root . '/app/design/Vendor/grandchild'],
            ['Vendor/grandchild' => 'Hyva/default'],
            'TailwindCSS',
        );
        $this->componentRegistrar->method('getPaths')->willReturn([]);
        $this->shell->method('execute')->willReturn('v22.0.0');

        $results = $this->checker->check();

        $this->assertSame(1, $results['themes']);
    }

    public function testIgnoresNonHyvaThemesWithTailwindDirectory(): void
    {
        $this->writeReadyTheme($this->root . '/app/design/Vendor/custom');
        $this->givenHyvaThemes(
            ['Vendor/custom' => $this->root . '/app/design/Vendor/custom'],
            ['Vendor/custom' => 'Vendor/base'],
            'TailwindCSS',
        );
        $this->componentRegistrar->method('getPaths')->willReturn([]);

        $this->assertSame(0, $this->checker->check()['themes']);
    }

    public function testModuleWithoutTailwindDirectoryHasNoFindings(): void
    {
        mkdir($this->root . '/module', 0o777, true);

        $this->assertSame([], $this->checker->checkModule($this->root . '/module'));
    }

    public function testCheckAggregatesThemesModulesAndNodeVersion(): void
    {
        $this->writeReadyTheme($this->root . '/app/design/Vendor/child');
        unlink($this->root . '/app/design/Vendor/child/web/tailwind/hyva.config.json');
        $this->write($this->root . '/vendor/hyva/default/web/tailwind/package.json', '{}');
        $this->write($this->root . '/app/code/Vendor/Module/view/frontend/tailwind/module.css', "@screen sm {}\n");
        mkdir($this->root . '/app/code/Vendor/Plain', 0o777, true);

        $this->givenHyvaThemes([
            'Vendor/child' => $this->root . '/app/design/Vendor/child',
            'Hyva/default' => $this->root . '/vendor/hyva/default',
        ]);
        $this->componentRegistrar->method('getPaths')->with(ComponentRegistrar::MODULE)->willReturn([
            'Vendor_Module' => $this->root . '/app/code/Vendor/Module',
            'Vendor_Plain' => $this->root . '/app/code/Vendor/Plain',
        ]);
        $this->shell->method('execute')->with('node --version')->willReturn("v18.19.0\n");

        $results = $this->checker->check();

        $this->assertSame(1, $results['themes']);
        $this->assertSame(1, $results['modules']);
        $this->assertSame(
            ['Environment', 'Theme Vendor/child', 'Module Vendor_Module'],
            array_keys($results['sections']),
        );
        $this->assertSame(2, $results['errors']);
        $this->assertSame(1, $results['warnings']);
    }

    public function testNodeVersionFailureIsAWarning(): void
    {
        $this->writeReadyTheme($this->root . '/app/design/Vendor/child');
        $this->givenHyvaThemes(['Vendor/child' => $this->root . '/app/design/Vendor/child']);
        $this->componentRegistrar->method('getPaths')->willReturn([]);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('node: not found'));

        $results = $this->checker->check();

        $this->assertSame(0, $results['errors']);
        $this->assertSame(1, $results['warnings']);
    }

    /**
     * @param array<string, string> $themes Theme code => path
     * @param array<string, string> $parents Theme code => code of its parent theme
     * @param string $builderName Name of the builder detected for all themes
     */
    private function givenHyvaThemes(array $themes, array $parents = [], string $builderName = 'HyvaThemes'): void
    {
        $objects = [];
        foreach (array_keys($themes) as $code) {
            $parent = isset($parents[$code]) ? $this->fakeTheme($parents[$code]) : null;
            $objects[$code] = $this->fakeTheme($code, $parent);
        }

        $this->themeList->method('getAllThemes')->willReturn($objects);
        $this->themePath
            ->method('getPath')
            ->willReturnCallback(static fn(string $code): ?string => $themes[$code] ?? null);

        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn($builderName);
        $this->builderPool->method('getBuilder')->willReturn($builder);
    }

    private function fakeTheme(string $code, ?object $parent = null): object
    {
        return new class ($code, $parent) {
            public function __construct(private readonly string $code, private readonly ?object $parent)
            {
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function getParentTheme(): ?object
            {
                return $this->parent;
            }
        };
    }

    private function writeReadyTheme(string $themePath): void
    {
        $tailwind = $themePath . '/web/tailwind';
        $this->write($tailwind . '/package.json', json_encode([
            'devDependencies' => [
                'tailwindcss' => '^4.1.0',
                '@tailwindcss/cli' => '^4.1.0',
                '@hyva-themes/hyva-modules' => '^1.0.0',
            ],
            'scripts' => [
                'generate' => 'npx hyva-sources && npx hyva-tokens',
                'prewatch' => 'npm run generate',
                'prebuild' => 'npm run generate',
                'watch' => 'npx tailwindcss -i tailwind-source.css -o ../css/styles.css --watch',
                'build' => 'npx tailwindcss -i tailwind-source.css -o ../css/styles.css --minify',
            ],
        ], JSON_THROW_ON_ERROR));
        $this->write($tailwind . '/hyva.config.json', '{}');
        $this->write(
            $tailwind . '/tailwind-source.css',
            <<<'CSS'
@import "@hyva-themes/hyva-modules/css";
@import "tailwindcss" source(none);
@source "../../**/*.phtml";
@import "./base";
@import "./generated/hyva-source.css";
@import "./generated/hyva-tokens.css";
CSS,
        );
        mkdir($tailwind . '/base', 0o777, true);
    }

    private function write(string $path, string $content): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }
        file_put_contents($path, $content);
    }

    private function setTailwindRange(string $themePath, string $range): void
    {
        $packageFile = $themePath . '/web/tailwind/package.json';
        /** @var array{devDependencies: array<string, string>} $package */
        $package = json_decode((string) file_get_contents($packageFile), true, 512, JSON_THROW_ON_ERROR);
        $package['devDependencies']['tailwindcss'] = $range;
        $this->write($packageFile, json_encode($package, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string> $scripts
     */
    private function removeScripts(string $themePath, array $scripts): void
    {
        $packageFile = $themePath . '/web/tailwind/package.json';
        /** @var array{scripts: array<string, string>} $package */
        $package = json_decode((string) file_get_contents($packageFile), true, 512, JSON_THROW_ON_ERROR);
        foreach ($scripts as $script) {
            unset($package['scripts'][$script]);
        }
        $this->write($packageFile, json_encode($package, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<Tailwind4Finding> $findings
     * @return array<string>
     */
    private function messages(array $findings): array
    {
        return array_map(static fn(Tailwind4Finding $finding): string => $finding->message, $findings);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }
        rmdir($path);
    }
}
