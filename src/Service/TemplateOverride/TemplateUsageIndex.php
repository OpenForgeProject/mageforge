<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Collects all Module_Name::path/to/template.phtml references found in module and theme sources
 *
 * Scans layout XML, PHP and PHTML files. References stored in the database (e.g. CMS blocks)
 * or built dynamically at runtime cannot be detected.
 */
class TemplateUsageIndex
{
    private const REFERENCE_PATTERN = '/\b([A-Za-z0-9]+_[A-Za-z0-9]+)::([A-Za-z0-9_\-\/.]+\.phtml)/';
    private const EXTENSIONS = ['xml', 'php', 'phtml'];
    private const SKIPPED_DIRECTORIES = ['node_modules', '.git', 'Test', 'tests', 'dev'];

    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param File $fileDriver
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Collect references from all registered modules and themes
     *
     * @return array<string, true> Template identifiers as keys
     */
    public function collect(): array
    {
        $roots = array_merge(
            array_values($this->componentRegistrar->getPaths(ComponentRegistrar::MODULE)),
            array_values($this->componentRegistrar->getPaths(ComponentRegistrar::THEME)),
        );

        return $this->collectFromDirectories($roots);
    }

    /**
     * Collect references from the given directories
     *
     * @param string[] $directories
     * @return array<string, true>
     */
    public function collectFromDirectories(array $directories): array
    {
        $references = [];

        foreach ($directories as $directory) {
            if (!$this->fileDriver->isDirectory($directory)) {
                continue;
            }

            $filter = new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                static fn(\SplFileInfo $file): bool => !$file->isDir()
                || !in_array($file->getFilename(), self::SKIPPED_DIRECTORIES, true),
            );

            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator($filter) as $file) {
                if (!$file->isFile() || !in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                    continue;
                }

                $content = $this->readFile($file->getPathname());
                if ($content === null || !str_contains($content, '::')) {
                    continue;
                }

                $matches = [];
                if (preg_match_all(self::REFERENCE_PATTERN, $content, $matches, PREG_SET_ORDER) !== false) {
                    foreach ($matches as $match) {
                        $references[$match[1] . '::' . $match[2]] = true;
                    }
                }
            }
        }

        return $references;
    }

    /**
     * Read a file, returning null when it is unreadable
     *
     * @param string $path
     * @return string|null
     */
    private function readFile(string $path): ?string
    {
        try {
            return $this->fileDriver->fileGetContents($path);
        } catch (\Exception) {
            return null;
        }
    }
}
