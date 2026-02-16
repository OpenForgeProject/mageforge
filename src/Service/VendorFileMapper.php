<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\DirectoryList;
use RuntimeException;

class VendorFileMapper
{
    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly DirectoryList $directoryList
    ) {
    }

    /**
     * Map a vendor file path to the correct theme override path
     *
     * @param string $sourcePath
     * @param string $themePath
     * @param string|null $themeArea Optional theme area (frontend/adminhtml), if not provided will be extracted from path
     * @return string
     * @throws RuntimeException
     */
    public function mapToThemePath(string $sourcePath, string $themePath, ?string $themeArea = null): string
    {
        // 1. Determine target theme area (frontend or adminhtml)
        $themeArea = $themeArea ?? $this->extractThemeArea($themePath);

        // 2. Normalize: Ensure $sourcePath is relative from Magento Root if it's absolute
        $rootPath = rtrim($this->directoryList->getRoot(), '/');
        if (str_starts_with($sourcePath, $rootPath . '/')) {
            $sourcePath = substr($sourcePath, strlen($rootPath) + 1);
        }

        // 3. Detect "Standard Module" Pattern (Priority 1) - Best for Local Modules & Composer Packages
        $modules = $this->componentRegistrar->getPaths(ComponentRegistrar::MODULE);
        foreach ($modules as $moduleName => $path) {
            // Normalize module path relative to root
            if (str_starts_with($path, $rootPath . '/')) {
                $path = substr($path, strlen($rootPath) + 1);
            }

            // Check if source starts with this module path
            if (str_starts_with($sourcePath, $path . '/')) {
                $pathInsideModule = substr($sourcePath, strlen($path) + 1);

                // Validate area and extract clean path
                $cleanPath = $this->validateAndExtractViewPath($pathInsideModule, $themeArea, $sourcePath);

                return rtrim($themePath, '/') . '/' . $moduleName . '/' . ltrim($cleanPath, '/');
            }
        }

        // 4. Detect "Nested Module" Pattern (Priority 2) - Works for Hyva Compat & Vendor Themes
        // Regex search for a segment matching Vendor_Module (e.g. Magento_Catalog).
        // Captures (Group 1): "Vendor_Module"
        if (preg_match('/([A-Z][a-zA-Z0-9]*_[A-Z][a-zA-Z0-9]*)/', $sourcePath, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[1][1];

            // Extract from Vendor_Module onwards (e.g. "Mollie_Payment/templates/file.phtml")
            $relativePath = substr($sourcePath, $offset);

            // Validate that this path contains a valid view area
            // Extract the part after Vendor_Module to check
            $parts = explode('/', $relativePath, 3);
            if (count($parts) >= 3 && $parts[1] === 'view') {
                // Format: Vendor_Module/view/{area}/...
                $area = $parts[2];
                if (!$this->isAreaCompatible($area, $themeArea)) {
                    throw new RuntimeException(
                        sprintf(
                            "Cannot map file from area '%s' to %s theme. File: %s",
                            $area,
                            $themeArea,
                            $sourcePath
                        )
                    );
                }
            }

            return rtrim($themePath, '/') . '/' . ltrim($relativePath, '/');
        }

        // 5. Fallback
        throw new RuntimeException("Could not determine target module or theme structure for file: " . $sourcePath);
    }

    /**
     * Extract theme area from theme path
     *
     * @param string $themePath
     * @return string
     * @throws RuntimeException
     */
    private function extractThemeArea(string $themePath): string
    {
        if (preg_match('#/(frontend|adminhtml)/#', $themePath, $matches)) {
            return $matches[1];
        }

        throw new RuntimeException("Could not determine theme area from path: " . $themePath);
    }

    /**
     * Validate that the path is under view/{area}/ and compatible with target theme area
     *
     * @param string $pathInsideModule
     * @param string $targetArea
     * @param string $originalPath
     * @return string Clean path without view/{area}/ prefix
     * @throws RuntimeException
     */
    private function validateAndExtractViewPath(
        string $pathInsideModule,
        string $targetArea,
        string $originalPath
    ): string {
        // Check if path starts with view/{area}/
        if (!preg_match('#^view/([^/]+)/#', $pathInsideModule, $matches)) {
            throw new RuntimeException(
                sprintf(
                    "File is not under a view/ directory. " .
                    "Only files under view/{area}/ can be mapped to themes. File: %s",
                    $originalPath
                )
            );
        }

        $sourceArea = $matches[1];

        // Validate area compatibility
        if (!$this->isAreaCompatible($sourceArea, $targetArea)) {
            throw new RuntimeException(
                sprintf(
                    "Cannot map file from area '%s' to %s theme. File: %s",
                    $sourceArea,
                    $targetArea,
                    $originalPath
                )
            );
        }

        // Remove view/{area}/ prefix
        return (string) preg_replace('#^view/[^/]+/#', '', $pathInsideModule);
    }

    /**
     * Check if source area is compatible with target theme area
     *
     * @param string $sourceArea
     * @param string $targetArea
     * @return bool
     */
    private function isAreaCompatible(string $sourceArea, string $targetArea): bool
    {
        // Exact match
        if ($sourceArea === $targetArea) {
            return true;
        }

        // 'base' area is compatible with both frontend and adminhtml
        if ($sourceArea === 'base') {
            return true;
        }

        return false;
    }
}
