<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\App\Config\ScopeConfigInterface;
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
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PackageInfo $packageInfo,
    ) {
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

        if ($this->shouldAddHeader()) {
            $this->copyWithHeader($sourceFile, $targetFile, $sourceModuleName);
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
     * Copy the source file and prepend an information header
     *
     * @param string $sourceFile
     * @param string $targetFile
     * @param string|null $sourceModuleName
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function copyWithHeader(string $sourceFile, string $targetFile, ?string $sourceModuleName): void
    {
        $content = $this->fileDriver->fileGetContents($sourceFile);
        $header = $this->buildHeader($sourceFile, $sourceModuleName);

        $this->fileDriver->filePutContents($targetFile, $header . $content);
    }

    /**
     * Build the source information header for the copied file
     *
     * @param string $sourceFile
     * @param string|null $sourceModuleName
     * @return string
     */
    private function buildHeader(string $sourceFile, ?string $sourceModuleName): string
    {
        $date = date('Y-m-d');
        $lines = [
            'MageForge Template Override from ' . $date,
            'Source: ' . $sourceFile,
        ];

        if ($sourceModuleName !== null && $sourceModuleName !== '') {
            $version = $this->packageInfo->getVersion($sourceModuleName);
            if ($version !== '') {
                $lines[] = 'Source Module-Version: ' . $version;
            }
        }

        $commented = array_map(static fn(string $line): string => '# ' . $line, $lines);

        return implode("\n", $commented) . "\n\n";
    }
}
