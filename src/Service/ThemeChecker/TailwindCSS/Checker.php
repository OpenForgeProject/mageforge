<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker\TailwindCSS;

use OpenForgeProject\MageForge\Service\ThemeChecker\MagentoStandard\Checker as StandardChecker;

class Checker extends StandardChecker
{
    /**
     * {@inheritdoc}
     */
    public function detect(string $themePath): bool
    {
        // Normalize path
        $themePath = rtrim($themePath, '/');

        // Check for package.json with TailwindCSS dependency
        if (!$this->fileSystem->fileExists($themePath . '/package.json')) {
            return false;
        }

        $packageJsonContent = $this->fileSystem->getFileContents($themePath . '/package.json');
        if (!$packageJsonContent) {
            return false;
        }

        $packageJson = json_decode($packageJsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Check dependencies for tailwindcss
        $dependencies = $packageJson['dependencies'] ?? [];
        $devDependencies = $packageJson['devDependencies'] ?? [];

        return isset($dependencies['tailwindcss']) || isset($devDependencies['tailwindcss']);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'TailwindCSS';
    }
}
