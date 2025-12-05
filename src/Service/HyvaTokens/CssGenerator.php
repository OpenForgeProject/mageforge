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
            $css .= "    {$cssVarName}: {$value};\n";
        }

        $css .= "}\n";

        return $css;
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
        // Ensure the directory exists by extracting parent directory path
        $pathParts = explode('/', $outputPath);
        array_pop($pathParts); // Remove filename
        $directory = implode('/', $pathParts);
        
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
