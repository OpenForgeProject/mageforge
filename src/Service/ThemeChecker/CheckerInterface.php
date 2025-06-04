<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker;

interface CheckerInterface
{
    /**
     * Detect if this checker can check the given theme
     *
     * @param string $themePath
     * @return bool
     */
    public function detect(string $themePath): bool;

    /**
     * Check for outdated composer dependencies
     *
     * @param string $themePath
     * @return array
     */
    public function checkComposerDependencies(string $themePath): array;

    /**
     * Check for outdated npm dependencies
     *
     * @param string $themePath
     * @return array
     */
    public function checkNpmDependencies(string $themePath): array;

    /**
     * Get the name of the checker
     *
     * @return string
     */
    public function getName(): string;
}
