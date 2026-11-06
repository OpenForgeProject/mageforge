<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
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

    private const IGNORED_DIRECTORIES = ['/node_modules/', '/generated/', '/backup/'];
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
            if (($excludeVendor && str_contains($modulePath, '/vendor/'))
                || ($thirdPartyOnly && str_starts_with($moduleName, 'Magento_'))
                || !$this->fileDriver->isDirectory($modulePath . '/' . self::MODULE_TAILWIND_DIR)
            ) {
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

            if ($path === null || ($excludeVendor && str_contains($path, '/vendor/'))) {
                continue;
            }

            if ($this->builderPool->getBuilder($path)?->getName() === self::HYVA_BUILDER_NAME) {
                $paths[$code] = $path;
            }
        }

        return $paths;
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

        $declaredMajor = $this->parseMajor($declared);
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

        $legacy = array_values(array_filter(
            self::LEGACY_PACKAGES,
            static fn(string $package): bool => array_key_exists($package, $dependencies),
        ));
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

        if (!isset($scripts['generate'])) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Warning,
                'The "generate" script is missing.',
                'Add "generate": "npx hyva-sources && npx hyva-tokens" and run it from "prewatch" and "prebuild".',
                'package.json',
            );
        }

        foreach (['build', 'watch'] as $name) {
            $script = $scripts[$name] ?? null;
            if (is_string($script) && preg_match('/postcss|tailwind\.config/i', $script) === 1) {
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

        $content = $this->fileDriver->fileGetContents($path);
        $findings = [];

        foreach ($this->findLines($content, '/@tailwind\s+(base|components|utilities)\b/') as $line) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Error,
                'The @tailwind directives were removed in Tailwind CSS v4.',
                'Replace them with: @import "tailwindcss" source(none);',
                self::ENTRY_FILE,
                $line,
            );
        }

        if (preg_match('/@import\s+["\']tailwindcss["\']/', $content) !== 1) {
            $findings[] = new Tailwind4Finding(
                Tailwind4Severity::Error,
                'The entry file does not import Tailwind CSS v4.',
                'Add: @import "tailwindcss" source(none);',
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
            $lines = preg_split('/\R/', $this->fileDriver->fileGetContents($file)) ?: [];

            foreach ($lines as $index => $line) {
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
        if ($this->isCommentLine($line)) {
            return null;
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
        if (preg_match('#^tailwindcss/(base|components|utilities)(\.css)?$#', $path) === 1) {
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
     * @param string $directory
     * @return array<string>
     */
    private function getCssFiles(string $directory): array
    {
        $files = [];

        foreach ($this->fileDriver->readDirectoryRecursively($directory) as $path) {
            if (!str_ends_with($path, '.css') || !$this->fileDriver->isFile($path)) {
                continue;
            }

            $relative = '/' . ltrim(substr($path, strlen($directory)), '/');
            foreach (self::IGNORED_DIRECTORIES as $ignored) {
                if (str_contains($relative, $ignored)) {
                    continue 2;
                }
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * Find the numbers of lines matching a pattern, ignoring comments
     *
     * @param string $content
     * @param string $pattern
     * @return array<int>
     */
    private function findLines(string $content, string $pattern): array
    {
        $numbers = [];

        foreach (preg_split('/\R/', $content) ?: [] as $index => $line) {
            if (!$this->isCommentLine(trim($line)) && preg_match($pattern, $line) === 1) {
                $numbers[] = $index + 1;
            }
        }

        return $numbers;
    }

    /**
     * Check if a trimmed CSS line is empty or part of a comment
     *
     * @param string $line
     * @return bool
     */
    private function isCommentLine(string $line): bool
    {
        return $line === ''
            || str_starts_with($line, '/*')
            || str_starts_with($line, '*')
            || str_starts_with($line, '//');
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
     * Extract the first number of a version or range, e.g. "^3.4.1" or "v20.11.0"
     *
     * @param string $version
     * @return int|null
     */
    private function parseMajor(string $version): ?int
    {
        return preg_match('/\d+/', $version, $matches) === 1 ? (int) $matches[0] : null;
    }
}
