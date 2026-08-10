<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\PackageInfo;
use OpenForgeProject\MageForge\Model\Config\TemplateOverride as TemplateOverrideConfig;

/**
 * Copies a template file to its override location, creating directories as needed
 */
class TemplateCopier
{
    /**
     * @param File $fileDriver
     * @param ScopeConfigInterface $scopeConfig
     * @param PackageInfo $packageInfo
     * @param CommentStyle $commentStyle
     * @param DirectoryList $directoryList
     * @param ComponentRegistrarInterface $componentRegistrar
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PackageInfo $packageInfo,
        private readonly CommentStyle $commentStyle,
        private readonly DirectoryList $directoryList,
        private readonly ComponentRegistrarInterface $componentRegistrar,
    ) {
    }

    /**
     * Check whether the override date should be included in the source header
     *
     * @return bool
     */
    private function shouldIncludeDateInHeader(): bool
    {
        return $this->scopeConfig->isSetFlag(
            TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_DATE,
            TemplateOverrideConfig::SCOPE_STORE,
        );
    }

    /**
     * Check whether the source module version should be included in the source header
     *
     * @return bool
     */
    private function shouldIncludeModuleVersionInHeader(): bool
    {
        return $this->scopeConfig->isSetFlag(
            TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_MODULE_VERSION,
            TemplateOverrideConfig::SCOPE_STORE,
        );
    }

    /**
     * Check whether the relative source path should be included in the source header
     *
     * @return bool
     */
    private function shouldIncludeSourcePathInHeader(): bool
    {
        return $this->scopeConfig->isSetFlag(
            TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_PATH,
            TemplateOverrideConfig::SCOPE_STORE,
        );
    }

    /**
     * Check whether the source module name should be included in the source header
     *
     * @return bool
     */
    private function shouldIncludeSourceModuleInHeader(): bool
    {
        return $this->scopeConfig->isSetFlag(
            TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_MODULE,
            TemplateOverrideConfig::SCOPE_STORE,
        );
    }

    /**
     * Check whether the logical override target should be included in the source header
     *
     * @return bool
     */
    private function shouldIncludeOverrideForInHeader(): bool
    {
        return $this->scopeConfig->isSetFlag(
            TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_INCLUDE_OVERRIDE_FOR,
            TemplateOverrideConfig::SCOPE_STORE,
        );
    }

    /**
     * Check whether source headers are enabled for the given file type
     *
     * @param string $filePath
     * @return bool
     */
    private function isHeaderEnabledForFile(string $filePath): bool
    {
        return match ($this->extension($filePath)) {
            'phtml', 'php' => $this->scopeConfig->isSetFlag(
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_PHTML,
                TemplateOverrideConfig::SCOPE_STORE,
            ),
            'html', 'htm' => $this->scopeConfig->isSetFlag(
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_HTML,
                TemplateOverrideConfig::SCOPE_STORE,
            ),
            'xml', 'xhtml', 'svg' => $this->scopeConfig->isSetFlag(
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_XML,
                TemplateOverrideConfig::SCOPE_STORE,
            ),
            'css', 'js', 'less', 'scss', 'sass', 'ts' => $this->scopeConfig->isSetFlag(
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_WEB_ASSETS,
                TemplateOverrideConfig::SCOPE_STORE,
            ),
            'sh', 'bash', 'zsh', 'fish' => $this->scopeConfig->isSetFlag(
                TemplateOverrideConfig::XML_PATH_SOURCE_HEADER_ENABLE_SHELL,
                TemplateOverrideConfig::SCOPE_STORE,
            ),
            default => false,
        };
    }

    /**
     * Copy the source template to the target location
     *
     * @param string $sourceFile
     * @param string $targetFile
     * @param string|null $sourceModuleName
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function copy(string $sourceFile, string $targetFile, ?string $sourceModuleName = null): void
    {
        $targetDir = $this->fileDriver->getParentDirectory($targetFile);
        if (!$this->fileDriver->isDirectory($targetDir)) {
            $this->fileDriver->createDirectory($targetDir);
        }

        $commentStyle = $this->commentStyle->fromFilePath($targetFile);
        if ($commentStyle->isSupported() && $this->shouldAddHeader() && $this->isHeaderEnabledForFile($targetFile)) {
            $this->copyWithHeader($sourceFile, $targetFile, $sourceModuleName, $commentStyle);
            return;
        }

        $this->fileDriver->copy($sourceFile, $targetFile);
    }

    /**
     * Check whether the source header should be prepended to copied files
     *
     * @return bool
     */
    private function shouldAddHeader(): bool
    {
        return $this->scopeConfig->isSetFlag(
            TemplateOverrideConfig::XML_PATH_ADD_HEADER,
            TemplateOverrideConfig::SCOPE_STORE,
        );
    }

    /**
     * Copy the source file and prepend or inject an information header
     *
     * For PHP files that already open with an "<?php" tag, the header is injected
     * right after that opening tag so no duplicate PHP open tag is created.
     *
     * @param string $sourceFile
     * @param string $targetFile
     * @param string|null $sourceModuleName
     * @param CommentStyle $commentStyle
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function copyWithHeader(
        string $sourceFile,
        string $targetFile,
        ?string $sourceModuleName,
        CommentStyle $commentStyle,
    ): void {
        $content = $this->fileDriver->fileGetContents($sourceFile);

        if ($commentStyle->isPhpBlock()) {
            $headerLines = $this->buildPhpDocHeaderLines($sourceFile, $sourceModuleName);
            $header = $this->contentStartsWithOpenPhpTag($content)
                ? $this->injectAfterOpenPhpTag($content, $commentStyle->wrapPhpDoc($headerLines))
                : $commentStyle->wrapPhpBlock($headerLines) . $content;
            $content = $header;
        } else {
            $headerLines = $this->buildHeaderLines($sourceFile, $sourceModuleName);
            $content = $commentStyle->wrap($headerLines) . $content;
        }

        $this->fileDriver->filePutContents($targetFile, $content);
    }

    /**
     * Build the source information header lines for the copied file
     *
     * The source path is stored relative to the Magento root so the header stays
     * portable across different developer machines and container setups.
     *
     * @param string $sourceFile
     * @param string|null $sourceModuleName
     * @return string[]
     */
    private function buildHeaderLines(string $sourceFile, ?string $sourceModuleName): array
    {
        $lines = [
            'MageForge Template Override',
        ];

        if ($this->shouldIncludeDateInHeader()) {
            $lines[] = 'Date: ' . date('Y-m-d');
        }

        if ($this->shouldIncludeSourcePathInHeader()) {
            $lines[] = 'Source: ' . $this->toRelativePath($sourceFile);
        }

        $actualSourceModule = $this->resolveSourceModule($sourceFile);
        $includeSourceModule = $this->shouldIncludeSourceModuleInHeader();

        if ($actualSourceModule !== null && $includeSourceModule) {
            $lines[] = 'Source Module: ' . $actualSourceModule;
            $version = $this->shouldIncludeModuleVersionInHeader()
                ? $this->packageInfo->getVersion($actualSourceModule)
                : '';
            if ($version !== '') {
                $lines[] = 'Source Module-Version: ' . $version;
            }
        } elseif ($sourceModuleName !== null && $sourceModuleName !== '' && $includeSourceModule) {
            $lines[] = 'Override For: ' . $sourceModuleName;
            $version = $this->shouldIncludeModuleVersionInHeader()
                ? $this->packageInfo->getVersion($sourceModuleName)
                : '';
            if ($version !== '') {
                $lines[] = 'Module-Version: ' . $version;
            }
        }

        return $lines;
    }

    /**
     * Build the PHPDoc-style header lines for PHP/PHTML files
     *
     * Uses @-tags so IDEs highlight the metadata nicely. The module/version tags
     * always refer to the component that physically provided the source file,
     * ensuring the recorded version is the one that was current at copy time.
     *
     * @param string $sourceFile
     * @param string|null $sourceModuleName
     * @return string[]
     */
    private function buildPhpDocHeaderLines(string $sourceFile, ?string $sourceModuleName): array
    {
        $lines = [
            '@mageforge-template-override',
        ];

        if ($this->shouldIncludeDateInHeader()) {
            $lines[] = '@date ' . date('Y-m-d');
        }

        if ($this->shouldIncludeSourcePathInHeader()) {
            $lines[] = '@source ' . $this->toRelativePath($sourceFile);
        }

        $actualSourceModule = $this->resolveSourceModule($sourceFile);
        $includeSourceModule = $this->shouldIncludeSourceModuleInHeader();

        if ($actualSourceModule !== null && $includeSourceModule) {
            $lines[] = '@module ' . $actualSourceModule;
            $version = $this->shouldIncludeModuleVersionInHeader()
                ? $this->packageInfo->getVersion($actualSourceModule)
                : '';
            if ($version !== '') {
                $lines[] = '@module-version ' . $version;
            }
        } elseif ($sourceModuleName !== null && $sourceModuleName !== '' && $includeSourceModule) {
            $lines[] = '@module ' . $sourceModuleName;
            $version = $this->shouldIncludeModuleVersionInHeader()
                ? $this->packageInfo->getVersion($sourceModuleName)
                : '';
            if ($version !== '') {
                $lines[] = '@module-version ' . $version;
            }
        }

        $includeOverrideFor =
            $this->shouldIncludeOverrideForInHeader()
            && $this->isOverrideForDifferentModule($actualSourceModule, $sourceModuleName);
        if ($includeOverrideFor) {
            $lines[] = '@override-for ' . (string) $sourceModuleName;
        }

        return $lines;
    }

    /**
     * Extract the lower-cased file extension from a path
     *
     * @param string $filePath
     * @return string
     */
    private function extension(string $filePath): string
    {
        $lastDot = strrpos($filePath, '.');

        return $lastDot === false ? '' : strtolower(substr($filePath, $lastDot + 1));
    }

    /**
     * Check whether the source file belongs to a different module than the logical override target
     *
     * @param string|null $actualSourceModule
     * @param string|null $sourceModuleName
     * @return bool
     */
    private function isOverrideForDifferentModule(?string $actualSourceModule, ?string $sourceModuleName): bool
    {
        return (
            $actualSourceModule !== null
            && $sourceModuleName !== null
            && $sourceModuleName !== ''
            && $actualSourceModule !== $sourceModuleName
        );
    }

    /**
     * Find the registered module that physically contains the source file
     *
     * Uses the longest path match so nested or compat module paths win.
     *
     * @param string $sourceFile
     * @return string|null
     */
    private function resolveSourceModule(string $sourceFile): ?string
    {
        $normalizedFile = str_replace('\\', '/', $sourceFile);
        $bestName = null;
        $bestPath = '';

        foreach ($this->componentRegistrar->getPaths(ComponentRegistrar::MODULE) as $name => $path) {
            if (!is_string($path)) {
                continue;
            }

            $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
            if ($normalizedPath === '' || !str_starts_with($normalizedFile, $normalizedPath . '/')) {
                continue;
            }

            if (strlen($normalizedPath) > strlen($bestPath)) {
                $bestName = (string) $name;
                $bestPath = $normalizedPath;
            }
        }

        return $bestName;
    }

    /**
     * Convert an absolute path to a path relative to the Magento root
     *
     * If the path is outside the Magento root, the original path is returned.
     *
     * @param string $absolutePath
     * @return string
     */
    private function toRelativePath(string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', $this->directoryList->getRoot()), '/');
        $normalizedPath = str_replace('\\', '/', $absolutePath);

        if ($root !== '' && str_starts_with($normalizedPath, $root . '/')) {
            return substr($normalizedPath, strlen($root) + 1);
        }

        return $normalizedPath;
    }

    /**
     * Check whether the file content starts with an opening PHP tag
     *
     * Allows optional whitespace before the tag, but requires a line break or
     * whitespace after it so short tags like "<?php echo" are still recognised.
     *
     * @param string $content
     * @return bool
     */
    private function contentStartsWithOpenPhpTag(string $content): bool
    {
        return preg_match('/^\s*<\?php\s/', $content) === 1;
    }

    /**
     * Inject a PHP doc-block header right after the opening PHP tag
     *
     * The header is placed on its own line after the tag, preserving any content
     * that follows on the same line (e.g. "<?php declare(strict_types=1);").
     *
     * @param string $content
     * @param string $header
     * @return string
     */
    private function injectAfterOpenPhpTag(string $content, string $header): string
    {
        $match = [];
        if (!preg_match('/^(\s*<\?php)\s*/m', $content, $match)) {
            return $header . $content;
        }

        return $match[1] . "\n" . rtrim($header) . "\n\n" . substr($content, strlen($match[0]));
    }
}
