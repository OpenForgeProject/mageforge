<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;

/**
 * Checks Hyvä themes and modules for readiness for Tailwind CSS v4 (Hyvä 1.4)
 *
 * The check is read-only. For themes it inspects the web/tailwind directory, for modules
 * the CSS files in view/frontend/tailwind.
 *
 * @phpstan-type Tailwind4Results array{
 *     sections: array<string, array<Tailwind4Finding>>,
 *     themes: int,
 *     modules: int,
 *     errors: int,
 *     warnings: int
 * }
 */
class Tailwind4Checker
{
    private const THEME_TAILWIND_DIR = 'web/tailwind';
    private const MODULE_TAILWIND_DIR = 'view/frontend/tailwind';
    private const ENTRY_FILE = 'tailwind-source.css';
    private const HYVA_BUILDER_NAME = 'HyvaThemes';
    private const HYVA_THEME_PREFIX = 'Hyva/';
    private const ENVIRONMENT_SECTION = 'Environment';
    private const MIN_NODE_MAJOR = 20;
    private const MIN_TAILWIND_MAJOR = 4;

    private const LEGACY_PACKAGES = [
        'postcss',
        'postcss-import',
        'postcss-preset-env',
        '@tailwindcss/forms',
        '@tailwindcss/typography',
    ];

    private const REQUIRED_PACKAGES = ['@tailwindcss/cli', '@hyva-themes/hyva-modules'];

    private const LEGACY_FILES = [
        'postcss.config.js' => 'Delete it; Tailwind CSS v4 no longer runs as a PostCSS plugin in Hyvä.',
        'postcss.config.cjs' => 'Delete it; Tailwind CSS v4 no longer runs as a PostCSS plugin in Hyvä.',
        'tailwind.config.js' => 'Move colours and spacing to hyva.config.json (design tokens) or @theme in CSS.',
        'tailwind.config.cjs' => 'Move colours and spacing to hyva.config.json (design tokens) or @theme in CSS.',
    ];

    private const IGNORED_DIRECTORIES = ['node_modules', 'generated', 'backup'];
    private const STRUCTURE_DIRECTORIES = ['base', 'components', 'utilities'];

    /**
     * @param File $fileDriver
     * @param Shell $shell
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param BuilderPool $builderPool
     * @param ComponentRegistrarInterface $componentRegistrar
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly Shell $shell,
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly BuilderPool $builderPool,
        private readonly ComponentRegistrarInterface $componentRegistrar,
    ) {
    }

    /**
     * Check all Hyvä themes and modules with Tailwind sources
     *
     * @param bool $thirdPartyOnly Skip Magento_* modules
     * @param bool $excludeVendor Skip themes and modules from the vendor/ directory
     * @return array<string, mixed>
     * @phpstan-return Tailwind4Results
     */
    public function check(bool $thirdPartyOnly = true, bool $excludeVendor = true): array
    {
        $sections = [];
        $themes = $this->getHyvaThemePaths($excludeVendor);

        foreach ($themes as $themeCode => $themePath) {
            $findings = $this->checkTheme($themePath);
            if ($findings !== []) {
                $sections['Theme ' . $themeCode] = $findings;
            }
        }

        if ($themes !== []) {
            $environment = $this->checkNodeVersion();
            if ($environment !== []) {
                $sections = [self::ENVIRONMENT_SECTION => $environment] + $sections;
            }
        }

        $modulesChecked = 0;
        /** @var array<string, string> $modules */
        $modules = $this->componentRegistrar->getPaths(ComponentRegistrar::MODULE);
        foreach ($modules as $moduleName => $modulePath) {
            if (!$this->shouldCheckModule($moduleName, $modulePath, $thirdPartyOnly, $excludeVendor)) {
                continue;
            }

            $modulesChecked++;
            $findings = $this->checkModule($modulePath);
            if ($findings !== []) {
                $sections['Module ' . $moduleName] = $findings;
            }
        }

        $errors = 0;
        $warnings = 0;
        foreach ($sections as $findings) {
            foreach ($findings as $finding) {
                $finding->severity === Tailwind4Severity::Error ? $errors++ : $warnings++;
            }
        }

        return [
            'sections' => $sections,
            'themes' => count($themes),
            'modules' => $modulesChecked,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if a module has Tailwind sources and passes the vendor and core filters
     *
     * @param string $moduleName
     * @param string $modulePath
     * @param bool $thirdPartyOnly
     * @param bool $excludeVendor
     * @return bool
     */
    private function shouldCheckModule(
        string $moduleName,
        string $modulePath,
        bool $thirdPartyOnly,
        bool $excludeVendor,
    ): bool {
        if ($excludeVendor && str_contains($modulePath, '/vendor/')) {
            return false;
        }

        if ($thirdPartyOnly && str_starts_with($moduleName, 'Magento_')) {
            return false;
        }

        return $this->fileDriver->isDirectory($modulePath . '/' . self::MODULE_TAILWIND_DIR);
    }

    /**
     * Check a Hyvä theme's web/tailwind directory
     *
     * @param string $themePath
     * @return array<Tailwind4Finding>
     */
    public function checkTheme(string $themePath): array
    {
        $tailwindPath = rtrim($themePath, '/') . '/' . self::THEME_TAILWIND_DIR;

        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Error,
                    'Tailwind directory not found: ' . $tailwindPath,
                    'The theme has no web/tailwind directory.',
                ),
            ];
        }

        return [
            ...$this->checkPackageJson($tailwindPath),
            ...$this->checkLegacyFiles($tailwindPath),
            ...$this->checkHyvaConfig($tailwindPath),
            ...$this->checkFolderStructure($tailwindPath),
            ...$this->checkEntryFile($tailwindPath),
            ...$this->checkCssFiles($tailwindPath),
        ];
    }

    /**
     * Check the Tailwind CSS files shipped by a module
     *
     * @param string $modulePath
     * @return array<Tailwind4Finding>
     */
    public function checkModule(string $modulePath): array
    {
        $tailwindPath = rtrim($modulePath, '/') . '/' . self::MODULE_TAILWIND_DIR;

        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            return [];
        }

        return $this->checkCssFiles($tailwindPath);
    }

    /**
     * Get the paths of all Hyvä themes
     *
     * @param bool $excludeVendor
     * @return array<string, string>
     */
    private function getHyvaThemePaths(bool $excludeVendor): array
    {
        $paths = [];

        foreach ($this->themeList->getAllThemes() as $theme) {
            $code = $theme->getCode();
            $path = $this->themePath->getPath($code);

            if ($path === null || $excludeVendor && str_contains($path, '/vendor/')) {
                continue;
            }

            if ($this->isHyvaTheme($theme, $path)) {
                $paths[$code] = $path;
            }
        }

        return $paths;
    }

    /**
     * Check if a theme is a Hyvä theme, either by its own metadata or by inheriting from a Hyvä theme
     *
     * The Hyvä builder only recognises Hyvä in the theme's own theme.xml or composer.json, so a theme extending
     * a custom Hyvä base theme is detected through its ancestors.
     *
     * @param ThemeInterface $theme
     * @param string $path
     * @return bool
     */
    private function isHyvaTheme(mixed $theme, string $path): bool
    {
        if ($this->builderPool->getBuilder($path)?->getName() === self::HYVA_BUILDER_NAME) {
            return true;
        }

        if (!$this->fileDriver->isDirectory($path . '/' . self::THEME_TAILWIND_DIR)) {
            return false;
        }

        $parent = $theme->getParentTheme();
        while ($parent !== null) {
            if (str_starts_with($parent->getCode(), self::HYVA_THEME_PREFIX)) {
                return true;
            }

            $parent = $parent->getParentTheme();
        }

        return false;
    }

    /**
     * Check that the Node.js version is supported by Tailwind CSS v4
     *
     * @return array<Tailwind4Finding>
     */
    private function checkNodeVersion(): array
    {
        try {
            $version = trim($this->shell->execute('node --version'));
        } catch (\Exception) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Warning,
                    'Could not determine the Node.js version.',
                    'Make sure Node.js ' . self::MIN_NODE_MAJOR . ' or higher is available.',
                ),
            ];
        }

        $major = $this->parseMajor($version);
        if ($major !== null && $major < self::MIN_NODE_MAJOR) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Error,
                    sprintf('Node.js %s is too old for Tailwind CSS v4.', $version),
                    'Upgrade to Node.js ' . self::MIN_NODE_MAJOR . ' or higher.',
                ),
            ];
        }

        return [];
    }

    /**
     * Check dependencies and scripts in package.json
     *
     * @param string $tailwindPath
     * @return array<Tailwind4Finding>
     */
    private function checkPackageJson(string $tailwindPath): array
    {
        $packageFile = $tailwindPath . '/package.json';
        if (!$this->fileDriver->isExists($packageFile)) {
            return [new Tailwind4Finding(Tailwind4Severity::Error, 'package.json not found.', file: 'package.json')];
        }

        $package = $this->readJson($packageFile);
        if ($package === null) {
            return [
                new Tailwind4Finding(Tailwind4Severity::Error, 'package.json is not valid JSON.', file: 'package.json'),
            ];
        }

        $dependencies = array_merge(
            $this->toArray($package['dependencies'] ?? null),
            $this->toArray($package['devDependencies'] ?? null),
        );

        return [
            ...$this->checkTailwindVersion($tailwindPath, $dependencies),
            ...$this->checkPackages($dependencies),
            ...$this->checkScripts($this->toArray($package['scripts'] ?? null)),
        ];
    }

    /**
     * Check the declared and installed Tailwind CSS version
     *
     * @param string $tailwindPath
     * @param array<mixed> $dependencies
     * @return array<Tailwind4Finding>
     */
    private function checkTailwindVersion(string $tailwindPath, array $dependencies): array
    {
        $declared = $dependencies['tailwindcss'] ?? null;
        if (!is_string($declared)) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Error,
                    'tailwindcss is not listed in package.json.',
                    'npm install tailwindcss@latest --ignore-scripts',
                    'package.json',
                ),
            ];
        }

        $declaredMajor = $this->parseDeclaredMajor($declared);
        if ($declaredMajor !== null && $declaredMajor < self::MIN_TAILWIND_MAJOR) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Error,
                    sprintf('tailwindcss %s is declared, Tailwind CSS v4 is required.', $declared),
                    'npm install tailwindcss@latest --ignore-scripts',
                    'package.json',
                ),
            ];
        }

        $installed = $this->readJson($tailwindPath . '/node_modules/tailwindcss/package.json');
        $installedVersion = is_array($installed) && is_string($installed['version'] ?? null)
            ? $installed['version']
            : null;
        $installedMajor = $installedVersion === null ? null : $this->parseMajor($installedVersion);

        if ($installedMajor !== null && $installedMajor < self::MIN_TAILWIND_MAJOR) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Warning,
                    sprintf(
                        'tailwindcss %s is installed in node_modules, but %s is declared.',
                        $installedVersion,
                        $declared,
                    ),
                    'Run npm install in web/tailwind (or mageforge:theme:build) to update node_modules.',
                    'package.json',
                ),
            ];
        }

        if ($declaredMajor === null && $installedMajor === null && !$this->isAnyVersionTag($declared)) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Warning,
                    sprintf('The tailwindcss range "%s" cannot be evaluated.', $declared),
                    'Use a simple range such as ^4.1.0.',
                    'package.json',
                ),
            ];
        }

        return [];
    }

    /**
     * Check for removed and newly required packages
     *
     * @param array<mixed> $dependencies
     * @return array<Tailwind4Finding>
     */
    private function checkPackages(array $dependencies): array
    {
        $findings = [];

        $legacy = array_values(array_filter(self::LEGACY_PACKAGES, static fn(string $package): bool => array_key_exists(
            $package,
            $dependencies,
        )));
        if ($legacy !== []) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'Packages no longer needed: ' . implode(', ', $legacy),
                'npm uninstall ' . implode(' ', $legacy) . ' --ignore-scripts',
                'package.json',
            );
        }

        foreach (self::REQUIRED_PACKAGES as $package) {
            if (!array_key_exists($package, $dependencies)) {
                $findings[] = new Tailwind4Finding(
                    Tailwind4Severity::Error,
                    sprintf('%s is not listed in package.json.', $package),
                    sprintf('npm install %s@latest --ignore-scripts', $package),
                    'package.json',
                );
            }
        }

        return $findings;
    }

    /**
     * Check the npm scripts for the new build process
     *
     * @param array<mixed> $scripts
     * @return array<Tailwind4Finding>
     */
    private function checkScripts(array $scripts): array
    {
        $findings = [];
        $runsGenerate = static fn(mixed $script): bool => (
            is_string($script)
            && preg_match('/\bgenerate\b|hyva-sources/', $script) === 1
        );

        $generateText = isset($scripts['generate'])
            ? $this->expandScript($scripts, 'generate')
            : implode(' ', array_filter($scripts, 'is_string'));

        if (!isset($scripts['generate']) && !str_contains($generateText, 'hyva-sources')) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'The "generate" script is missing.',
                'Add "generate": "npx hyva-sources && npx hyva-tokens" and run it from "prewatch" and "prebuild".',
                'package.json',
            );
        } else {
            foreach (['hyva-sources', 'hyva-tokens'] as $command) {
                if (!str_contains($generateText, $command)) {
                    $findings[] = new Tailwind4Finding(
                        Tailwind4Severity::Warning,
                        sprintf('The "generate" script does not run %s.', $command),
                        'Make sure "generate" runs: npx hyva-sources && npx hyva-tokens',
                        'package.json',
                    );
                }
            }
        }

        foreach (['build', 'watch'] as $name) {
            $script = $scripts[$name] ?? null;
            if (!is_string($script)) {
                continue;
            }

            if (preg_match('/postcss|tailwind\.config/i', $script) === 1) {
                $findings[] = new Tailwind4Finding(
                    Tailwind4Severity::Warning,
                    sprintf('The "%s" script still uses PostCSS or tailwind.config.js.', $name),
                    sprintf(
                        'Use "npx tailwindcss -i %s -o ../css/styles.css%s".',
                        self::ENTRY_FILE,
                        $name === 'build' ? ' --minify' : ' --watch',
                    ),
                    'package.json',
                );
            }

            if (!$runsGenerate($script) && !$runsGenerate($scripts['pre' . $name] ?? null)) {
                $findings[] = new Tailwind4Finding(
                    Tailwind4Severity::Warning,
                    sprintf('The "%s" script does not run "generate" first.', $name),
                    sprintf('Add "pre%s": "npm run generate" so the generated Hyvä files exist.', $name),
                    'package.json',
                );
            }
        }

        return $findings;
    }

    /**
     * Check for files that are obsolete with Tailwind CSS v4
     *
     * @param string $tailwindPath
     * @return array<Tailwind4Finding>
     */
    private function checkLegacyFiles(string $tailwindPath): array
    {
        $findings = [];

        foreach (self::LEGACY_FILES as $file => $hint) {
            if ($this->fileDriver->isExists($tailwindPath . '/' . $file)) {
                $findings[] = new Tailwind4Finding(
                    Tailwind4Severity::Warning,
                    sprintf('%s is obsolete with Tailwind CSS v4.', $file),
                    $hint,
                    $file,
                );
            }
        }

        return $findings;
    }

    /**
     * Check that hyva.config.json exists
     *
     * @param string $tailwindPath
     * @return array<Tailwind4Finding>
     */
    private function checkHyvaConfig(string $tailwindPath): array
    {
        if ($this->fileDriver->isExists($tailwindPath . '/hyva.config.json')) {
            return [];
        }

        return [
            new Tailwind4Finding(
                Tailwind4Severity::Error,
                'hyva.config.json not found.',
                'Run "npx hyva-init" in web/tailwind and add your primary colours to the generated file.',
                'hyva.config.json',
            ),
        ];
    }

    /**
     * Check for the base/components/utilities folder structure of Hyvä 1.4
     *
     * @param string $tailwindPath
     * @return array<Tailwind4Finding>
     */
    private function checkFolderStructure(string $tailwindPath): array
    {
        foreach (self::STRUCTURE_DIRECTORIES as $directory) {
            if ($this->fileDriver->isDirectory($tailwindPath . '/' . $directory)) {
                return [];
            }
        }

        return [
            new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'None of the Hyvä 1.4 CSS folders (base, components, utilities) exist.',
                'Copy these folders from the Hyvä default theme and move your custom styles into them.',
            ),
        ];
    }

    /**
     * Check the CSS entry point for the Tailwind CSS v4 setup
     *
     * @param string $tailwindPath
     * @return array<Tailwind4Finding>
     */
    private function checkEntryFile(string $tailwindPath): array
    {
        $path = $tailwindPath . '/' . self::ENTRY_FILE;
        if (!$this->fileDriver->isExists($path)) {
            return [
                new Tailwind4Finding(
                    Tailwind4Severity::Error,
                    self::ENTRY_FILE . ' not found.',
                    'Copy it from the Hyvä default theme.',
                    self::ENTRY_FILE,
                ),
            ];
        }

        $content = $this->stripComments($this->fileDriver->fileGetContents($path));
        $findings = [];

        // The bundled stylesheet, or split imports that include the utilities layer
        $importsTailwind =
            preg_match('/@import\s+["\']tailwindcss(?:\/utilities(?:\.css)?)?["\']/', $content) === 1
            || preg_match('/@tailwind\s+utilities\b/', $content) === 1;
        if (!$importsTailwind) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Error,
                'The entry file does not import Tailwind CSS v4.',
                'Add: @import "tailwindcss" source(none); (split imports must include tailwindcss/utilities)',
                self::ENTRY_FILE,
            );
        }

        if (str_contains($content, 'source(none)') && !str_contains($content, '@source')) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'source(none) is used but no @source directive tells Tailwind where to find classes.',
                'Add: @source "../../**/*.phtml"; and @source "../../**/*.xml";',
                self::ENTRY_FILE,
            );
        }

        if (!str_contains($content, 'hyva-modules')) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                '@hyva-themes/hyva-modules is not imported.',
                'Add: @import "@hyva-themes/hyva-modules/css";',
                self::ENTRY_FILE,
            );
        }

        if (!str_contains($content, 'hyva-source.css') || !str_contains($content, 'hyva-tokens.css')) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'The generated module sources and design tokens are not imported.',
                'Add: @import "./generated/hyva-source.css"; and @import "./generated/hyva-tokens.css";',
                self::ENTRY_FILE,
            );
        }

        foreach ($this->findLines($content, '/^\s*@config\b/') as $line) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                '@config loads a JavaScript configuration, which is the legacy approach.',
                'Migrate the configuration to hyva.config.json or @theme.',
                self::ENTRY_FILE,
                $line,
            );
        }

        return $findings;
    }

    /**
     * Check all CSS files below a directory for syntax that changed in Tailwind CSS v4
     *
     * @param string $tailwindPath
     * @return array<Tailwind4Finding>
     */
    private function checkCssFiles(string $tailwindPath): array
    {
        $findings = [];

        foreach ($this->getCssFiles($tailwindPath) as $file) {
            $relative = ltrim(substr($file, strlen($tailwindPath)), '/');
            $css = $this->stripComments($this->fileDriver->fileGetContents($file));

            foreach (preg_split('/\R/', $css) ?: [] as $index => $line) {
                $finding = $this->checkCssLine(trim($line), $relative, $index + 1);
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        return $findings;
    }

    /**
     * Check a single CSS line
     *
     * @param string $line
     * @param string $file
     * @param int $number
     * @return Tailwind4Finding|null
     */
    private function checkCssLine(string $line, string $file, int $number): ?Tailwind4Finding
    {
        if ($line === '') {
            return null;
        }

        $matches = [];
        if (preg_match('/^@tailwind\s+(base|components)\b/', $line, $matches) === 1) {
            return new Tailwind4Finding(
                Tailwind4Severity::Error,
                sprintf('@tailwind %s was removed in Tailwind CSS v4.', $matches[1]),
                'Replace the @tailwind directives with: @import "tailwindcss" source(none);',
                $file,
                $number,
            );
        }

        if (preg_match('/^@screen\b/', $line) === 1) {
            return new Tailwind4Finding(
                Tailwind4Severity::Warning,
                '@screen was removed in Tailwind CSS v4.',
                'Use @media (width >= theme(--breakpoint-md)) { ... } instead.',
                $file,
                $number,
            );
        }

        if (preg_match('/\btheme\(\s*["\']?(?!--)[a-zA-Z]+[.\[]/', $line) === 1) {
            return new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'theme() with a dotted path is deprecated in Tailwind CSS v4.',
                'Use CSS variables instead, e.g. var(--color-red-500).',
                $file,
                $number,
            );
        }

        if (preg_match('/^@import\s+(?:url\()?["\']([^"\']+)["\']/', $line, $matches) !== 1) {
            return null;
        }

        return $this->checkImportPath($matches[1], $file, $number);
    }

    /**
     * Check an @import path
     *
     * @param string $path
     * @param string $file
     * @param int $number
     * @return Tailwind4Finding|null
     */
    private function checkImportPath(string $path, string $file, int $number): ?Tailwind4Finding
    {
        if (preg_match('#^tailwindcss/(base|components)(\.css)?$#', $path) === 1) {
            return new Tailwind4Finding(
                Tailwind4Severity::Error,
                sprintf('"%s" does not exist in Tailwind CSS v4.', $path),
                'Use @import "tailwindcss" source(none); instead.',
                $file,
                $number,
            );
        }

        if (preg_match('#^(\./|\.\./|/|https?:|tailwindcss|@hyva-themes/|@tailwindcss/)#', $path) === 1) {
            return null;
        }

        return new Tailwind4Finding(
            Tailwind4Severity::Warning,
            sprintf('@import "%s" is not a relative path.', $path),
            'Tailwind CSS v4 requires a ./ or ../ prefix for local imports.',
            $file,
            $number,
        );
    }

    /**
     * Get the CSS files below a directory, excluding dependencies and generated output
     *
     * Ignored directories are pruned before descending, so node_modules is never traversed.
     *
     * @param string $directory
     * @return array<string>
     */
    private function getCssFiles(string $directory): array
    {
        $files = [];

        foreach ($this->fileDriver->readDirectory($directory) as $path) {
            if ($this->fileDriver->isDirectory($path)) {
                $name = substr($path, (int) strrpos($path, '/') + 1);
                if (!in_array($name, self::IGNORED_DIRECTORIES, true)) {
                    array_push($files, ...$this->getCssFiles($path));
                }
                continue;
            }

            if (str_ends_with($path, '.css')) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Find the numbers of lines matching a pattern
     *
     * @param string $content
     * @param string $pattern
     * @return array<int>
     */
    private function findLines(string $content, string $pattern): array
    {
        $numbers = [];

        foreach (preg_split('/\R/', $content) ?: [] as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $numbers[] = $index + 1;
            }
        }

        return $numbers;
    }

    /**
     * Remove CSS comments while keeping strings and line numbers intact
     *
     * @param string $css
     * @return string
     */
    private function stripComments(string $css): string
    {
        $result = '';
        $length = strlen($css);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                $result .= $char;
                if ($char === '\\' && ($i + 1) < $length) {
                    $result .= $css[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                $comment = $end === false ? substr($css, $i) : substr($css, $i, $end + 2 - $i);
                $result .= ' ' . str_repeat("\n", substr_count($comment, "\n"));
                $i += strlen($comment) - 1;
                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * Read and decode a JSON file
     *
     * @param string $path
     * @return array<mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!$this->fileDriver->isExists($path)) {
            return null;
        }

        try {
            $data = json_decode($this->fileDriver->fileGetContents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Cast a decoded JSON value to an array
     *
     * @param mixed $value
     * @return array<mixed>
     */
    private function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Get the text of a script including the scripts it delegates to via "npm run <name>"
     *
     * @param array<mixed> $scripts
     * @param string $name
     * @param int $depth
     * @return string
     */
    private function expandScript(array $scripts, string $name, int $depth = 0): string
    {
        $script = $scripts[$name] ?? null;
        if (!is_string($script) || $depth > 5) {
            return '';
        }

        $text = $script;
        $matches = [];
        preg_match_all('/\b(?:npm|pnpm|yarn)\s+(?:run(?:-script)?\s+)?([\w:.-]+)/', $script, $matches);
        foreach ($matches[1] ?? [] as $reference) {
            $text .= ' ' . $this->expandScript($scripts, $reference, $depth + 1);
        }

        return $text;
    }

    /**
     * Extract the major version of a simple npm range such as "^3.4.1", "~4.0" or "4.1.x"
     *
     * Lower bounds below the required major (">=3.4.0") permit newer versions and are therefore inconclusive.
     *
     * @param string $range
     * @return int|null Null for ranges that cannot be evaluated reliably, e.g. "<4.0.0" or "3 || 4"
     */
    private function parseDeclaredMajor(string $range): ?int
    {
        $pattern = '/^(\^|~|>=|=)?\s*v?(\d+)(?:\.(?:\d+|x|\*))*(?:-[0-9A-Za-z.\-]+)?$/';
        $matches = [];
        if (preg_match($pattern, trim($range), $matches) !== 1) {
            return null;
        }

        $major = (int) $matches[2];

        return $matches[1] === '>=' && $major < self::MIN_TAILWIND_MAJOR ? null : $major;
    }

    /**
     * Check if a range accepts any version or the latest tag
     *
     * @param string $range
     * @return bool
     */
    private function isAnyVersionTag(string $range): bool
    {
        return in_array(strtolower(trim($range)), ['', '*', 'x', 'latest', 'next'], true);
    }

    /**
     * Extract the first number of a version, e.g. "v20.11.0"
     *
     * @param string $version
     * @return int|null
     */
    private function parseMajor(string $version): ?int
    {
        $matches = [];

        return preg_match('/\d+/', $version, $matches) === 1 ? (int) $matches[0] : null;
    }
}
