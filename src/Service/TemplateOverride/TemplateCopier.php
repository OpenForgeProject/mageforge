<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Copies a template file to its override location, creating directories as needed
 */
class TemplateCopier
{
    /**
     * @param File $fileDriver
     */
    public function __construct(
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Copy the source template to the target location
     *
     * @param string $sourceFile
     * @param string $targetFile
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function copy(string $sourceFile, string $targetFile): void
    {
        $targetDir = $this->fileDriver->getParentDirectory($targetFile);
        if (!$this->fileDriver->isDirectory($targetDir)) {
            $this->fileDriver->createDirectory($targetDir);
        }

        $this->fileDriver->copy($sourceFile, $targetFile);
    }
}
