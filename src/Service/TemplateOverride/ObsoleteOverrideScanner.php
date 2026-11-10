<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Model\ObsoleteOverride;
use OpenForgeProject\MageForge\Model\ObsoleteReason;
use OpenForgeProject\MageForge\Model\TemplateReference;
use OpenForgeProject\MageForge\Model\TemplateType;

/**
 * Finds theme override files that are no longer needed
 *
 * The theme's area DI configuration must be loaded first (see AreaEmulator) so the fallback
 * resolution matches what Magento does at runtime.
 */
class ObsoleteOverrideScanner
{
    private const MODULE_DIRECTORY_PATTERN = '#/[A-Za-z0-9]+_[A-Za-z0-9]+$#';
    private const VIEW_DIRECTORIES = [
        'templates' => TemplateType::TEMPLATE,
        'email' => TemplateType::EMAIL,
        'web' => TemplateType::STATIC,
        'layout' => TemplateType::LAYOUT,
    ];

    /**
     * @var array<string,true>|null
     */
    private ?array $references = null;

    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param TemplateFallbackResolver $fallbackResolver
     * @param OverrideContentAnalyzer $contentAnalyzer
     * @param TemplateUsageIndex $usageIndex
     * @param PackageInfo $packageInfo
     * @param File $fileDriver
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly TemplateFallbackResolver $fallbackResolver,
        private readonly OverrideContentAnalyzer $contentAnalyzer,
        private readonly TemplateUsageIndex $usageIndex,
        private readonly PackageInfo $packageInfo,
        private readonly File $fileDriver,
        private readonly DirectoryList $directoryList,
    ) {
    }

    /**
     * Scan a theme for obsolete overrides
     *
     * @param ThemeInterface $theme
     * @param bool $deep Also report templates that are not referenced anywhere (slower, heuristic)
     * @return ObsoleteOverride[]
     */
    public function scan(ThemeInterface $theme, bool $deep = false): array
    {
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $theme->getFullPath());
        if ($themePath === null || !$this->fileDriver->isDirectory($themePath)) {
            return [];
        }

        $references = $deep ? $this->getReferences() : null;

        $findings = [];
        foreach ($this->listFiles($themePath) as $file) {
            $finding = $this->analyse($theme, $themePath, $file, $references);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Build the reference index once and reuse it for every scanned theme
     *
     * @return array<string,true>
     */
    private function getReferences(): array
    {
        return $this->references ??= $this->usageIndex->collect();
    }

    /**
     * Check whether the theme is installed below the vendor directory
     *
     * @param ThemeInterface $theme
     * @return bool
     */
    public function isVendorTheme(ThemeInterface $theme): bool
    {
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $theme->getFullPath());
        if ($themePath === null) {
            return false;
        }

        $vendorDir = $this->normalize($this->directoryList->getRoot()) . '/vendor/';

        return str_starts_with($this->normalize($themePath) . '/', $vendorDir);
    }

    /**
     * List all override files located in <theme>/<Module_Name>/{templates,email,web,layout}
     *
     * @param string $themePath
     * @return string[]
     */
    private function listFiles(string $themePath): array
    {
        $files = [];
        $moduleDirs = $this->fileDriver->readDirectory($themePath);
        sort($moduleDirs);

        foreach ($moduleDirs as $moduleDir) {
            $isModuleDir =
                $this->fileDriver->isDirectory($moduleDir)
                && preg_match(self::MODULE_DIRECTORY_PATTERN, $moduleDir) === 1;
            if (!$isModuleDir) {
                continue;
            }

            foreach (array_keys(self::VIEW_DIRECTORIES) as $subDir) {
                $dir = $moduleDir . '/' . $subDir;
                if (!$this->fileDriver->isDirectory($dir)) {
                    continue;
                }

                $entries = $this->fileDriver->readDirectoryRecursively($dir);
                sort($entries);
                foreach ($entries as $entry) {
                    if ($this->fileDriver->isFile($entry)) {
                        $files[] = $entry;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Analyse one override file
     *
     * @param ThemeInterface $theme
     * @param string $themePath
     * @param string $file
     * @param array<string,true>|null $references
     * @return ObsoleteOverride|null
     */
    private function analyse(
        ThemeInterface $theme,
        string $themePath,
        string $file,
        ?array $references,
    ): ?ObsoleteOverride {
        $relative = substr($this->normalize($file), strlen($this->normalize($themePath)) + 1);
        [$moduleName, $subDir, $templatePath] = explode('/', $relative, 3) + [null, null, null];
        if ($moduleName === null || $subDir === null || $templatePath === null) {
            return null;
        }

        $type = self::VIEW_DIRECTORIES[$subDir];
        if ($type === TemplateType::STATIC && str_starts_with($templatePath, 'i18n/')) {
            return null;
        }

        $templateId = $moduleName . '::' . $templatePath;
        $create = static fn(
            ObsoleteReason $reason,
            string $details,
            bool $safe,
        ): ObsoleteOverride => new ObsoleteOverride($theme->getCode(), $file, $templateId, $reason, $details, $safe);

        if ($this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName) === null) {
            $details = sprintf('Module %s is not installed or enabled', $moduleName);

            return $create(ObsoleteReason::ORPHANED, $details, true);
        }

        // Theme layout files extend the module layout instead of replacing it
        if ($type === TemplateType::LAYOUT) {
            return null;
        }

        $content = $this->fileDriver->fileGetContents($file);
        $header = $this->contentAnalyzer->parseHeader($content);
        $original = $this->findOriginal($theme, $file, new TemplateReference($moduleName, $templatePath, $type));

        if ($original !== null && $this->isIdentical($content, $original)) {
            return $create(ObsoleteReason::IDENTICAL, 'Identical to ' . $this->relativePath($original), true);
        }

        if ($original === null && $header['source'] !== null && !$this->sourceExists($header['source'])) {
            return $create(ObsoleteReason::ORPHANED, 'Source file no longer exists: ' . $header['source'], false);
        }

        $isUnused =
            $references !== null
            && $type === TemplateType::TEMPLATE
            && str_ends_with($templatePath, '.phtml')
            && !isset($references[$templateId])
            && !isset($references[TemplateUsageIndex::ANY_MODULE . $templatePath]);
        if ($isUnused) {
            $details =
                'No reference found. It may still be used (widget, DI, database, dynamic path)'
                . ' - verify before deleting';

            return $create(ObsoleteReason::POSSIBLY_UNUSED, $details, false);
        }

        $installedVersion = $header['module'] !== null ? $this->packageInfo->getVersion($header['module']) : '';
        if ($header['version'] !== null && $installedVersion !== '' && $installedVersion !== $header['version']) {
            return $create(
                ObsoleteReason::OUTDATED,
                sprintf('Copied from %s %s, installed is %s', $header['module'], $header['version'], $installedVersion),
                false,
            );
        }

        return null;
    }

    /**
     * Find the file Magento would use if the override did not exist
     *
     * @param ThemeInterface $theme
     * @param string $overrideFile
     * @param TemplateReference $reference
     * @return string|null
     */
    private function findOriginal(ThemeInterface $theme, string $overrideFile, TemplateReference $reference): ?string
    {
        $overrideFile = $this->normalize($overrideFile);

        foreach ($this->fallbackResolver->getFallbackDirs($reference, $theme) as $dir) {
            $candidate = $this->normalize($dir) . '/' . $reference->getTemplatePath();
            if ($candidate !== $overrideFile && $this->fileDriver->isFile($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check whether the override content equals the original file
     *
     * @param string $content
     * @param string $original
     * @return bool
     */
    private function isIdentical(string $content, string $original): bool
    {
        return $this->contentAnalyzer->isIdentical($content, $this->fileDriver->fileGetContents($original));
    }

    /**
     * Check whether a source path from an override header (relative to the Magento root) still exists
     *
     * @param string $source
     * @return bool
     */
    private function sourceExists(string $source): bool
    {
        $path = str_starts_with($source, '/') ? $source : $this->directoryList->getRoot() . '/' . $source;

        return $this->fileDriver->isExists($path);
    }

    /**
     * Make a path relative to the Magento root for display
     *
     * @param string $path
     * @return string
     */
    private function relativePath(string $path): string
    {
        $root = $this->normalize($this->directoryList->getRoot());
        $normalized = $this->normalize($path);

        return $root !== '' && str_starts_with($normalized, $root . '/')
            ? substr($normalized, strlen($root) + 1)
            : $path;
    }

    /**
     * Normalise directory separators and trailing slashes
     *
     * @param string $path
     * @return string
     */
    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
