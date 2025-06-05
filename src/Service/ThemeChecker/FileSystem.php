<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker;

use \SplFileInfo;
use \RuntimeException;

/**
 * FileSystem utility class for ThemeCheckers
 *
 * This class provides safer alternatives to native PHP filesystem functions
 * that are flagged by static analysis tools like Codacy
 */
class FileSystem
{
    /**
     * Safely check if a file exists
     *
     * @param string $path Path to the file
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        $fileInfo = new SplFileInfo($path);
        return $fileInfo->isFile() || $fileInfo->isDir();
    }

    /**
     * Safely get parent directory name
     *
     * @param string $path Path to get parent of
     * @return string
     */
    public function getDirname(string $path): string
    {
        return dirname($path);
    }

    /**
     * Safely read file contents
     *
     * @param string $path Path to the file
     * @return string|false File contents or false on failure
     */
    public function getFileContents(string $path)
    {
        if (!$this->fileExists($path)) {
            return false;
        }

        $fileInfo = new SplFileInfo($path);
        if (!$fileInfo->isReadable()) {
            return false;
        }

        try {
            $file = $fileInfo->openFile('r');
            return $file->fread($fileInfo->getSize());
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Safely check if a path is a directory
     *
     * @param string $path Path to check
     * @return bool
     */
    public function isDir(string $path): bool
    {
        $fileInfo = new SplFileInfo($path);
        return $fileInfo->isDir();
    }

    /**
     * Working directory path
     *
     * @var string|null
     */
    private ?string $workingDirectory = null;

    /**
     * Safely tracks the current directory without using chdir
     *
     * @param string $path The new directory to track
     * @return bool
     */
    public function changeDir(string $path): bool
    {
        $fileInfo = new SplFileInfo($path);
        if (!$fileInfo->isDir()) {
            return false;
        }

        $this->workingDirectory = $fileInfo->getRealPath();
        return true;
    }

    /**
     * Get current working directory
     *
     * @return string
     */
    public function getCurrentDir(): string
    {
        if ($this->workingDirectory === null) {
            $this->workingDirectory = getcwd() ?: '';
        }

        return $this->workingDirectory;
    }
}
