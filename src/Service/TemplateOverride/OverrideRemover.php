<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Model\ObsoleteOverride;

/**
 * Deletes override files and the directories that become empty afterwards
 */
class OverrideRemover
{
    private const MODULE_DIRECTORY_PATTERN = '#/[A-Za-z0-9]+_[A-Za-z0-9]+$#';

    /**
     * @param File $fileDriver
     */
    public function __construct(
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Delete the override file and clean up empty parent directories up to its module directory
     *
     * @param ObsoleteOverride $override
     * @return bool
     */
    public function remove(ObsoleteOverride $override): bool
    {
        try {
            $this->fileDriver->deleteFile($override->getFile());
        } catch (\Exception) {
            return false;
        }

        // The file is already gone, so a failing directory cleanup must not turn this into a failure
        try {
            $this->removeEmptyDirectories($this->fileDriver->getParentDirectory($override->getFile()));
        } catch (\Exception) {
            return true;
        }

        return true;
    }

    /**
     * Remove empty directories upwards until (and including) the module directory
     *
     * @param string $directory
     * @return void
     */
    private function removeEmptyDirectories(string $directory): void
    {
        while ($this->fileDriver->isDirectory($directory) && $this->fileDriver->readDirectory($directory) === []) {
            $this->fileDriver->deleteDirectory($directory);
            if (preg_match(self::MODULE_DIRECTORY_PATTERN, $directory) === 1) {
                break;
            }
            $directory = $this->fileDriver->getParentDirectory($directory);
        }
    }
}
