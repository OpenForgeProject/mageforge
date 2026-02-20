<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\DirectoryList;
use RuntimeException;
use Magento\Framework\App\Area;

class VendorFileMapper
{
    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param DirectoryList $directoryList
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\State $appState
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly DirectoryList $directoryList,
        private readonly \Magento\Framework\ObjectManagerInterface $objectManager,
        private readonly \Magento\Framework\App\State $appState
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

                // Priority 1A: Check if this is a Hyva Compatibility Module
                // If so, map to its registered "original_module"
                $originalModule = $this->getOriginalModuleFromCompatRegistry($moduleName);

                if ($originalModule) {
                    // Start with clean path (e.g. templates/Original_Module/foo.phtml or templates/foo.phtml)
                    $targetPath = ltrim($cleanPath, '/');

                    // If path contains the Original Module name as a subdirectory (Hyva convention), strip it
                    // Example: templates/Mollie_Payment/foo.phtml -> templates/foo.phtml
                    // This prevents Theme/Mollie_Payment/templates/Mollie_Payment/foo.phtml
                    // Note: Check both strict and case-insensitive to be safe
                    if (str_contains($targetPath, '/' . $originalModule . '/')) {
                        $targetPath = str_replace('/' . $originalModule . '/', '/', $targetPath);
                    } elseif (stripos($targetPath, '/' . $originalModule . '/') !== false) {
                        // Case-insensitive replacement if strict failed
                         $targetPath = str_ireplace('/' . $originalModule . '/', '/', $targetPath);
                    }

                    return rtrim($themePath, '/') . '/' . $originalModule . '/' . $targetPath;
                }

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


    /**
     * Check if module is a registered Hyva compatibility module and retrieve its original module.
     *
     * @param string $compatModuleName
     * @return string|null
     */
    private function getOriginalModuleFromCompatRegistry(string $compatModuleName): ?string
    {
        // 1. Try Registry (via Emulated Area)
        if (class_exists('\Hyva\CompatModuleFallback\Model\CompatModuleRegistry')) {
            try {
                // Emulate frontend area to load proper DI configuration for CompatModuleRegistry
                // as CLI commands run in global scope where frontend/di.xml is ignored.
                /** @var \Hyva\CompatModuleFallback\Model\CompatModuleRegistry|null $registry */
                $registry = $this->appState->emulateAreaCode(
                    Area::AREA_FRONTEND,
                    function () {
                        // Use create() to ensure we get a fresh instance with the emulated configuration.
                        // get() might return a cached instance from global scope (empty).
                        return $this->objectManager->create('\Hyva\CompatModuleFallback\Model\CompatModuleRegistry');
                    }
                );

                if ($registry) {
                    // Iterate through original modules to find if current module is a registered compat module
                    // We call getOrigModules inside the emulation callback ideally, but here we got the object
                    foreach ($registry->getOrigModules() as $originalModule) {
                        // Get compat modules for this original module
                        $compatModules = $registry->getCompatModulesFor($originalModule);

                        // Check exact match first
                        if (in_array($compatModuleName, $compatModules, true)) {
                            return $originalModule;
                        }

                        // Fallback: Case-insensitive check
                        foreach ($compatModules as $compatModule) {
                            if (strnatcasecmp($compatModuleName, $compatModule) === 0) {
                                return $originalModule;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore errors here, continue to manual parsing
            }
        }

        // 2. Fallback: Manual XML parsing because CLI execution might miss frontend/di.xml config
        return $this->parseCompatModuleXml($compatModuleName);
    }

    /**
     * Manual fallback to parse etc/frontend/di.xml for compatibility registration.
     * Use DOMDocument for robust XML parsing.
     *
     * @param string $moduleName
     * @return string|null
     */
    private function parseCompatModuleXml(string $moduleName): ?string
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
            if (!$path) {
                return null;
            }

            // Check frontend/di.xml first, then global di.xml
            $diFile = $path . '/etc/frontend/di.xml';
            $globalDiFile = $path . '/etc/di.xml';

            $filesToCheck = [];
            if (file_exists($diFile)) {
                $filesToCheck[] = $diFile;
            }
            if (file_exists($globalDiFile)) {
                $filesToCheck[] = $globalDiFile;
            }

            foreach ($filesToCheck as $diPath) {
                if (!is_file($diPath)) { // Extra check for symlinks/is_file
                    continue;
                }

                $content = file_get_contents($diPath);
                if (!$content) {
                    continue;
                }

                $dom = new \DOMDocument();
                // Suppress warnings for malformed XML or namespace issues
                $libxmlState = libxml_use_internal_errors(true);
                $dom->loadXML($content);
                libxml_use_internal_errors($libxmlState);

                $xpath = new \DOMXPath($dom);
                // Register namespace? Usually not needed if query is correct
                // Try to find the compatModules argument node
                $query = "//argument[@name='compatModules'][@xsi:type='array']/item[@xsi:type='array']";
                $items = $xpath->query($query);

                if ($items === false || $items->length === 0) {
                    continue;
                }

                foreach ($items as $item) {
                     // Check children items for compat_module/original_module keys
                     $compatModuleValue = null;
                     $originalModuleValue = null;

                     /** @var \DOMElement $item */
                     $childNodes = $xpath->query('item', $item);
                     foreach ($childNodes as $childNode) {
                         /** @var \DOMElement $childNode */
                         $nameAttr = $childNode->getAttribute('name');
                         $value = trim($childNode->nodeValue);

                         if ($nameAttr === 'compat_module') {
                             $compatModuleValue = $value;
                         } elseif ($nameAttr === 'original_module') {
                             $originalModuleValue = $value;
                         }
                     }

                     if ($compatModuleValue === $moduleName && $originalModuleValue) {
                         return $originalModuleValue;
                     }
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
