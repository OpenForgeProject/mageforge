<?php

declare(strict_types=1);

namespace Magento\Framework\Filesystem\Driver;

/**
 * Stub class for Magento\Framework\Filesystem\Driver\File
 * Used to allow unit testing without a full Magento installation.
 */
class File
{
    public function isFile(string $path): bool
    {
        return false;
    }

    public function isDirectory(string $path): bool
    {
        return false;
    }

    public function isExists(string $path): bool
    {
        return false;
    }

    public function copy(string $source, string $target): bool
    {
        return true;
    }

    public function fileGetContents(string $path): string
    {
        return '';
    }

    /**
     * @return array<int, string>
     */
    public function readDirectory(string $directory): array
    {
        return [];
    }

    public function deleteFile(string $path): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function stat(string $path): array
    {
        return [];
    }
}
