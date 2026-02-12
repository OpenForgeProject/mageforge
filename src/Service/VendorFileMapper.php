<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\DirectoryList;
use RuntimeException;

class VendorFileMapper
{
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly DirectoryList $directoryList
    ) {}

    public function mapToThemePath(string $sourcePath, string $themePath): string
    {
        // 1. Normalize: Ensure $sourcePath is relative from Magento Root if it's absolute
        $rootPath = rtrim($this->directoryList->getRoot(), '/');
        if (str_starts_with($sourcePath, $rootPath . '/')) {
            $sourcePath = substr($sourcePath, strlen($rootPath) + 1);
        }

        // 2. Detect "Nested Module" Pattern (Priority 1) - Works for Hyva Compat & Vendor Themes
        // Regex search for a segment matching Vendor_Module (e.g. Magento_Catalog).
        // Captures (Group 1): "Vendor_Module"
        if (preg_match('/([A-Z][a-zA-Z0-9]*_[A-Z][a-zA-Z0-9]*)/', $sourcePath, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[1][1];

            // Extract from Vendor_Module onwards (e.g. "Mollie_Payment/templates/file.phtml")
            $relativePath = substr($sourcePath, $offset);

            return rtrim($themePath, '/') . '/' . ltrim($relativePath, '/');
        }

        // 3. Detect "Standard Module" Pattern (Priority 2)
        $modules = $this->componentRegistrar->getPaths(ComponentRegistrar::MODULE);
        foreach ($modules as $moduleName => $path) {
            // Normalize module path relative to root
            if (str_starts_with($path, $rootPath . '/')) {
                $path = substr($path, strlen($rootPath) + 1);
            }

            // Check if source starts with this module path
            if (str_starts_with($sourcePath, $path . '/')) {
                 $pathInsideModule = substr($sourcePath, strlen($path) + 1);

                 // Remove view/frontend/ or view/base/ from the path
                 $cleanPath = (string) preg_replace('#^view/(frontend|base)/#', '', $pathInsideModule);

                 return rtrim($themePath, '/') . '/' . $moduleName . '/' . ltrim($cleanPath, '/');
            }
        }

        // 4. Fallback
        throw new RuntimeException("Could not determine target module or theme structure for file: " . $sourcePath);
    }
}
