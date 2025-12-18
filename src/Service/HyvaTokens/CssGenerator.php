<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\HyvaTokens;

use Magento\Framework\Filesystem\Driver\File;

/**
 * CSS generator for Hyva tokens
 */
class CssGenerator
{
    public function __construct(
        private readonly File $fileDriver
    ) {
    }

    /**
     * Generate CSS from tokens
     *
     * @param array $tokens
     * @param string $cssSelector
     * @return string
     */
    public function generate(array $tokens, string $cssSelector): string
    {
        $css = $cssSelector . " {\n";

        foreach ($tokens as $name => $value) {
            $cssVarName = '--' . $name;
            // Sanitize value to prevent CSS syntax issues
            $sanitizedValue = $this->sanitizeCssValue($value);
            $css .= "    {$cssVarName}: {$sanitizedValue};\n";
        }

        $css .= "}\n";

        return $css;
    }

    /**
     * Sanitize CSS value to prevent syntax issues
     *
     * @param string $value
     * @return string
     */
    private function sanitizeCssValue(string $value): string
    {
        // Remove newlines and control characters
        $value = preg_replace('/[\r\n\t\x00-\x1F\x7F]/', '', $value);
        
        // Remove potentially problematic characters
        // Allow: alphanumeric, spaces, parentheses, commas, periods, hyphens, underscores, 
        // percent signs, hash symbols, and forward slashes
        $sanitized = preg_replace('/[^\w\s(),.%#\/-]/', '', $value);
        
        // Trim whitespace
        return trim($sanitized ?? '');
    }

    /**
     * Write CSS to file
     *
     * @param string $content
     * @param string $outputPath
     * @return bool
     * @throws \Exception
     */
    public function write(string $content, string $outputPath): bool
    {
        // Ensure the directory exists
        $directory = \dirname($outputPath);

        if (!$this->fileDriver->isDirectory($directory)) {
            $this->fileDriver->createDirectory($directory, 0750);
        }

        try {
            $this->fileDriver->filePutContents($outputPath, $content);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to write CSS file: " . $e->getMessage());
        }
    }
}
