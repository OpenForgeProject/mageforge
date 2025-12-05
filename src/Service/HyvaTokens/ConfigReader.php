<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\HyvaTokens;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Configuration reader for Hyva tokens
 */
class ConfigReader
{
    private const DEFAULT_SOURCE = 'design.tokens.json';
    private const DEFAULT_CSS_SELECTOR = '@theme';
    private const DEFAULT_FORMAT = 'default';

    public function __construct(
        private readonly File $fileDriver
    ) {
    }

    /**
     * Read configuration from hyva.config.json or provide defaults
     *
     * @param string $themePath
     * @return array
     */
    public function getConfig(string $themePath): array
    {
        $configPath = $this->getConfigPath($themePath);
        
        // Default configuration
        $config = [
            'src' => self::DEFAULT_SOURCE,
            'format' => self::DEFAULT_FORMAT,
            'cssSelector' => self::DEFAULT_CSS_SELECTOR,
            'values' => null,
        ];

        if ($this->fileDriver->isExists($configPath)) {
            $configContent = $this->fileDriver->fileGetContents($configPath);
            $jsonConfig = json_decode($configContent, true);

            if (isset($jsonConfig['tokens'])) {
                $tokensConfig = $jsonConfig['tokens'];
                
                // Override with config file values
                if (isset($tokensConfig['src'])) {
                    $config['src'] = $tokensConfig['src'];
                }
                if (isset($tokensConfig['format'])) {
                    $config['format'] = $tokensConfig['format'];
                }
                if (isset($tokensConfig['cssSelector'])) {
                    $config['cssSelector'] = $tokensConfig['cssSelector'];
                }
                if (isset($tokensConfig['values'])) {
                    $config['values'] = $tokensConfig['values'];
                }
            }
        }

        return $config;
    }

    /**
     * Get the path to hyva.config.json
     *
     * @param string $themePath
     * @return string
     */
    private function getConfigPath(string $themePath): string
    {
        return rtrim($themePath, '/') . '/web/tailwind/hyva.config.json';
    }

    /**
     * Get the path to the token source file
     *
     * @param string $themePath
     * @param string $sourceFile
     * @return string
     */
    public function getTokenSourcePath(string $themePath, string $sourceFile): string
    {
        return rtrim($themePath, '/') . '/web/tailwind/' . ltrim($sourceFile, '/');
    }

    /**
     * Get the output path for generated CSS
     *
     * @param string $themePath
     * @return string
     */
    public function getOutputPath(string $themePath): string
    {
        return rtrim($themePath, '/') . '/web/tailwind/generated/hyva-tokens.css';
    }

    /**
     * Check if token source exists (file or inline values)
     *
     * @param string $themePath
     * @param array $config
     * @return bool
     */
    public function hasTokenSource(string $themePath, array $config): bool
    {
        // Check for inline values
        if (!empty($config['values'])) {
            return true;
        }

        // Check for source file
        $sourcePath = $this->getTokenSourcePath($themePath, $config['src']);
        return $this->fileDriver->isExists($sourcePath);
    }
}
