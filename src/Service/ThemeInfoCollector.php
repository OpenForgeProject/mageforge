<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Composer\InstalledVersions;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\HyvaVersionDetector;

/**
 * Collects read-only facts about a theme (Composer package, overrides, Hyvä details, deployed locales)
 *
 * @phpstan-type ComposerInfo array{package: ?string, version: ?string, source: string}
 * @phpstan-type OverrideInfo array{
 *     modules: int,
 *     templates: int,
 *     layouts: int,
 *     requirejs: int,
 *     webAssets: int
 * }
 * @phpstan-type HyvaInfo array{
 *     version: ?string,
 *     tailwindConfig: ?string,
 *     extensions: array<int, string>
 * }
 */
class ThemeInfoCollector
{
    private const MODULE_DIR_PATTERN = '/^[A-Za-z0-9]+_[A-Za-z0-9]+$/';
    private const WEB_ASSET_EXTENSIONS = ['less', 'css', 'js'];
    private const TAILWIND_CONFIGS = ['tailwind.config.js', 'tailwind-source.css'];

    /**
     * @param File $fileDriver
     * @param Filesystem $filesystem
     * @param HyvaVersionDetector $hyvaVersionDetector
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly Filesystem $filesystem,
        private readonly HyvaVersionDetector $hyvaVersionDetector,
    ) {
    }

    /**
     * Get the Composer package name and installed version of a theme.
     *
     * Themes without a composer.json (e.g. in app/design) are reported as local.
     *
     * @param string $themePath
     * @return array{package: ?string, version: ?string, source: string}
     * @phpstan-return ComposerInfo
     */
    public function getComposerInfo(string $themePath): array
    {
        $composerFile = rtrim($themePath, '/') . '/composer.json';
        $package = null;

        if ($this->fileDriver->isFile($composerFile)) {
            $data = json_decode($this->fileDriver->fileGetContents($composerFile), true);
            $name = is_array($data) ? $data['name'] ?? null : null;
            $package = is_string($name) ? $name : null;
        }

        $version = null;
        if ($package !== null && class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)) {
            $version = InstalledVersions::getPrettyVersion($package);
        }

        return [
            'package' => $package,
            'version' => $version,
            'source' => str_contains($themePath, '/vendor/') ? 'vendor' : 'app/design',
        ];
    }

    /**
     * Count the files a theme overrides or adds, grouped by type.
     *
     * @param string $themePath
     * @return array{modules: int, templates: int, layouts: int, requirejs: int, webAssets: int}
     * @phpstan-return OverrideInfo
     */
    public function getOverrideCounts(string $themePath): array
    {
        $counts = ['modules' => 0, 'templates' => 0, 'layouts' => 0, 'requirejs' => 0, 'webAssets' => 0];

        foreach ($this->fileDriver->readDirectory(rtrim($themePath, '/')) as $entry) {
            if (!$this->fileDriver->isDirectory($entry) || !$this->isModuleDirectory($entry)) {
                continue;
            }

            $counts['modules']++;
            foreach ($this->listFiles($entry) as $file) {
                $key = $this->classifyFile(substr($file, strlen($entry) + 1));
                if ($key !== null) {
                    $counts[$key]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Get Hyvä specific details: version, Tailwind config file and registered extensions.
     *
     * @param string $themePath
     * @return array{version: ?string, tailwindConfig: ?string, extensions: array<int, string>}
     * @phpstan-return HyvaInfo
     */
    public function getHyvaInfo(string $themePath): array
    {
        $tailwindDir = rtrim($themePath, '/') . '/web/tailwind';
        $tailwindConfig = null;

        foreach (self::TAILWIND_CONFIGS as $file) {
            if ($this->fileDriver->isFile($tailwindDir . '/' . $file)) {
                $tailwindConfig = 'web/tailwind/' . $file;
                break;
            }
        }

        return [
            'version' => $this->hyvaVersionDetector->getVersion(),
            'tailwindConfig' => $tailwindConfig,
            'extensions' => $this->getHyvaExtensions(),
        ];
    }

    /**
     * Get the locales for which static content is deployed for a theme.
     *
     * @param string $area
     * @param string $themeCode
     * @return array<int, string>
     */
    public function getDeployedLocales(string $area, string $themeCode): array
    {
        $staticDirectory = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);
        $themeDir = sprintf('%s/%s', $area, $themeCode);

        if (!$staticDirectory->isDirectory($themeDir)) {
            return [];
        }

        $locales = [];
        foreach ($staticDirectory->read($themeDir) as $path) {
            if ($staticDirectory->isDirectory($path)) {
                $locales[] = substr($path, (int) strrpos($path, '/') + 1);
            }
        }

        sort($locales);

        return $locales;
    }

    /**
     * Read the extension sources registered in app/etc/hyva-themes.json.
     *
     * @return array<int, string>
     */
    private function getHyvaExtensions(): array
    {
        $configDirectory = $this->filesystem->getDirectoryRead(DirectoryList::CONFIG);
        if (!$configDirectory->isFile('hyva-themes.json')) {
            return [];
        }

        $data = json_decode($configDirectory->readFile('hyva-themes.json'), true);
        $extensions = is_array($data) ? $data['extensions'] ?? null : null;
        if (!is_array($extensions)) {
            return [];
        }

        $sources = [];
        foreach ($extensions as $extension) {
            if (is_array($extension) && is_string($extension['src'] ?? null)) {
                $sources[] = $extension['src'];
            }
        }

        return $sources;
    }

    /**
     * Map a file path relative to its module directory to an override counter key.
     *
     * @param string $relative
     * @return string|null
     */
    private function classifyFile(string $relative): ?string
    {
        $dot = strrpos($relative, '.');
        $extension = $dot === false ? '' : strtolower(substr($relative, $dot + 1));

        return match (true) {
            $extension === 'phtml' && str_starts_with($relative, 'templates/') => 'templates',
            $extension === 'xml' && str_starts_with($relative, 'layout/') => 'layouts',
            $relative === 'requirejs-config.js' => 'requirejs',
            $this->isWebAsset($relative, $extension) => 'webAssets',
            default => null,
        };
    }

    /**
     * Check whether a file is a LESS, CSS or JS asset below the module's web directory.
     *
     * @param string $relative
     * @param string $extension
     * @return bool
     */
    private function isWebAsset(string $relative, string $extension): bool
    {
        return str_starts_with($relative, 'web/') && in_array($extension, self::WEB_ASSET_EXTENSIONS, true);
    }

    /**
     * Check whether a directory is named like a Magento module (Vendor_Module).
     *
     * @param string $path
     * @return bool
     */
    private function isModuleDirectory(string $path): bool
    {
        $position = strrpos(rtrim($path, '/'), '/');
        $name = $position === false ? $path : substr(rtrim($path, '/'), $position + 1);

        return preg_match(self::MODULE_DIR_PATTERN, $name) === 1;
    }

    /**
     * Recursively list all files below a directory.
     *
     * @param string $directory
     * @return array<int, string>
     */
    private function listFiles(string $directory): array
    {
        $files = [];
        foreach ($this->fileDriver->readDirectoryRecursively($directory) as $path) {
            if ($this->fileDriver->isFile($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
