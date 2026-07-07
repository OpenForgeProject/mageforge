<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Model\TemplateReference;

/**
 * Parses user input into a template reference
 *
 * Accepts the Module_Name::path/to/template.phtml notation as well as filesystem paths into
 * a module's view/<area>/templates directory or a theme's <Module_Name>/templates directory.
 * Hyvä compatibility module references are normalized to the original module, because theme
 * overrides always use the original module's name as directory name.
 */
class TemplatePathParser
{
    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param CompatModuleResolver $compatModuleResolver
     * @param File $fileDriver
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly CompatModuleResolver $compatModuleResolver,
        private readonly File $fileDriver,
        private readonly DirectoryList $directoryList,
    ) {
    }

    /**
     * Parse a template reference or file path into a TemplateReference
     *
     * @param string $input
     * @return TemplateReference
     * @throws \InvalidArgumentException
     */
    public function parse(string $input): TemplateReference
    {
        $input = trim($input);
        if ($input === '') {
            throw new \InvalidArgumentException('Template reference must not be empty.');
        }

        if (str_contains($input, '::')) {
            return $this->parseTemplateId($input);
        }

        return $this->parseFilePath($input);
    }

    /**
     * Parse the Module_Name::path/to/template.phtml notation
     *
     * @param string $input
     * @return TemplateReference
     * @throws \InvalidArgumentException
     */
    private function parseTemplateId(string $input): TemplateReference
    {
        [$moduleName, $templatePath] = explode('::', $input, 2);
        $moduleName = trim($moduleName);
        $templatePath = $this->normalizeTemplatePath($templatePath);

        if ($moduleName === '' || $templatePath === '') {
            throw new \InvalidArgumentException(
                "Invalid template reference '$input'. Expected format: Module_Name::path/to/template.phtml",
            );
        }

        if ($this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName) === null) {
            throw new \InvalidArgumentException("Module '$moduleName' is not registered in this installation.");
        }

        return $this->createReference($moduleName, $templatePath);
    }

    /**
     * Parse a filesystem path pointing into a module or theme templates directory
     *
     * @param string $input
     * @return TemplateReference
     * @throws \InvalidArgumentException
     */
    private function parseFilePath(string $input): TemplateReference
    {
        $absolutePath = $this->locateFile(str_replace('\\', '/', $input));
        if ($absolutePath === null) {
            throw new \InvalidArgumentException(
                "Template file '$input' not found. Pass an existing file path or use the "
                . 'Module_Name::path/to/template.phtml notation.',
            );
        }

        $moduleReference = $this->matchModuleFile($absolutePath);
        if ($moduleReference !== null) {
            return $moduleReference;
        }

        $themeReference = $this->matchThemeFile($absolutePath);
        if ($themeReference !== null) {
            return $themeReference;
        }

        throw new \InvalidArgumentException(
            "The file '$input' does not belong to a registered module or theme view directory.",
        );
    }

    /**
     * Locate a file by its path, trying the working directory and the Magento root
     *
     * Relative paths are first resolved against the current working directory and then
     * against the Magento root, so paths like vendor/module/... work from any directory.
     *
     * @param string $path
     * @return string|null The normalized absolute path, or null when the file does not exist
     */
    private function locateFile(string $path): ?string
    {
        $candidates = [$path];
        if (!str_starts_with($path, '/')) {
            $root = rtrim(str_replace('\\', '/', $this->directoryList->getRoot()), '/');
            if ($root !== '') {
                $candidates[] = $root . '/' . $path;
            }
        }

        foreach ($candidates as $candidate) {
            if (!$this->fileDriver->isFile($candidate)) {
                continue;
            }
            $realPath = $this->fileDriver->getRealPath($candidate);
            if (is_string($realPath)) {
                return str_replace('\\', '/', $realPath);
            }
        }

        return null;
    }

    /**
     * Match a file inside a module's view/<area>/templates directory
     *
     * @param string $absolutePath
     * @return TemplateReference|null
     * @throws \InvalidArgumentException
     */
    private function matchModuleFile(string $absolutePath): ?TemplateReference
    {
        $match = $this->findOwningComponent($absolutePath, ComponentRegistrar::MODULE);
        if ($match === null) {
            return null;
        }

        [$moduleName, $relativePath] = $match;
        $matches = [];
        if (!preg_match('#^view/[a-z_]+/templates/(.+)$#', $relativePath, $matches)) {
            throw new \InvalidArgumentException(sprintf(
                "The file belongs to module '%s' but is not inside a view/<area>/templates directory.",
                $moduleName,
            ));
        }

        return $this->createReference($moduleName, $matches[1]);
    }

    /**
     * Match a file inside a theme's <Module_Name>/templates directory
     *
     * @param string $absolutePath
     * @return TemplateReference|null
     * @throws \InvalidArgumentException
     */
    private function matchThemeFile(string $absolutePath): ?TemplateReference
    {
        $match = $this->findOwningComponent($absolutePath, ComponentRegistrar::THEME);
        if ($match === null) {
            return null;
        }

        [$themeFullPath, $relativePath] = $match;
        $matches = [];
        if (!preg_match('#^([A-Za-z0-9]+_[A-Za-z0-9]+)/templates/(.+)$#', $relativePath, $matches)) {
            throw new \InvalidArgumentException(sprintf(
                "The file belongs to theme '%s' but is not a module template override "
                . '(expected <Module_Name>/templates/...).',
                $themeFullPath,
            ));
        }

        return $this->createReference($matches[1], $matches[2]);
    }

    /**
     * Find the registered component (longest path match) containing the given file
     *
     * @param string $absolutePath
     * @param string $componentType
     * @return array{0: string, 1: string}|null Component name and file path relative to the component root
     */
    private function findOwningComponent(string $absolutePath, string $componentType): ?array
    {
        $bestName = null;
        $bestPath = '';

        foreach ($this->componentRegistrar->getPaths($componentType) as $name => $componentPath) {
            if (!is_string($componentPath)) {
                continue;
            }
            $componentPath = rtrim(str_replace('\\', '/', $componentPath), '/');
            if ($componentPath === '' || !str_starts_with($absolutePath, $componentPath . '/')) {
                continue;
            }
            if (strlen($componentPath) > strlen($bestPath)) {
                $bestName = (string) $name;
                $bestPath = $componentPath;
            }
        }

        if ($bestName === null) {
            return null;
        }

        return [$bestName, substr($absolutePath, strlen($bestPath) + 1)];
    }

    /**
     * Create the reference, normalizing Hyvä compat modules to their original module
     *
     * Theme overrides always live in a directory named after the original module, even when
     * the template currently in effect is shipped by a Hyvä compatibility module.
     *
     * @param string $moduleName
     * @param string $templatePath
     * @return TemplateReference
     * @throws \InvalidArgumentException
     */
    private function createReference(string $moduleName, string $templatePath): TemplateReference
    {
        $originalModules = $this->compatModuleResolver->getOriginalModules($moduleName);
        if ($originalModules === []) {
            return new TemplateReference($moduleName, $templatePath);
        }

        // Compat modules may keep templates in a subdirectory named after the original module
        foreach ($originalModules as $originalModule) {
            if (str_starts_with($templatePath, $originalModule . '/')) {
                return new TemplateReference($originalModule, substr($templatePath, strlen($originalModule) + 1));
            }
        }

        if (count($originalModules) === 1) {
            return new TemplateReference($originalModules[0], $templatePath);
        }

        throw new \InvalidArgumentException(sprintf(
            "'%s' is a Hyvä compatibility module for several modules (%s). "
            . "Please reference the template by its original module, e.g. '%s::%s'.",
            $moduleName,
            implode(', ', $originalModules),
            $originalModules[0],
            $templatePath,
        ));
    }

    /**
     * Normalize a template path and reject relative path segments
     *
     * @param string $path
     * @return string
     * @throws \InvalidArgumentException
     */
    private function normalizeTemplatePath(string $path): string
    {
        $normalized = ltrim(trim(str_replace('\\', '/', $path)), '/');
        if (str_contains($normalized, './')) {
            throw new \InvalidArgumentException("Template path '$path' must not contain relative path segments.");
        }

        return $normalized;
    }
}
