<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\HyvaTokens;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Token parser for different formats (default and Figma)
 */
class TokenParser
{
    public function __construct(
        private readonly File $fileDriver
    ) {
    }

    /**
     * Parse tokens from a file or inline configuration
     *
     * @param string|null $filePath
     * @param array|null $inlineValues
     * @param string $format
     * @return array
     * @throws \Exception
     */
    public function parse(?string $filePath, ?array $inlineValues, string $format): array
    {
        // Use inline values if provided
        if ($inlineValues !== null) {
            return $this->normalizeTokens($inlineValues, $format);
        }

        // Otherwise, read from file
        if ($filePath === null || !$this->fileDriver->isFile($filePath)) {
            throw new \Exception("Token source file not found: " . ($filePath ?? 'null'));
        }

        $content = $this->fileDriver->fileGetContents($filePath);

        try {
            $tokens = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \Exception("Invalid JSON in token file: " . $e->getMessage());
        }

        return $this->normalizeTokens($tokens, $format);
    }

    /**
     * Normalize tokens to a flat structure
     *
     * @param array $tokens
     * @param string $format
     * @return array
     */
    private function normalizeTokens(array $tokens, string $format): array
    {
        if ($format === 'figma') {
            return $this->normalizeFigmaTokens($tokens);
        }

        return $this->normalizeDefaultTokens($tokens);
    }

    /**
     * Normalize default format tokens to flat structure
     *
     * @param array $tokens
     * @param string $prefix
     * @return array
     */
    private function normalizeDefaultTokens(array $tokens, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($tokens as $key => $value) {
            $currentKey = $prefix ? $prefix . '-' . $key : $key;

            if (is_array($value)) {
                // Check if this is a token value with a special key (e.g., DEFAULT)
                if (isset($value['DEFAULT']) || $this->isLeafNode($value)) {
                    foreach ($value as $subKey => $subValue) {
                        if ($subKey === 'DEFAULT') {
                            $flattened[$currentKey] = $subValue;
                        } else {
                            $flattened[$currentKey . '-' . $subKey] = $subValue;
                        }
                    }
                } else {
                    // Recursively flatten nested structures
                    $flattened = array_merge(
                        $flattened,
                        $this->normalizeDefaultTokens($value, $currentKey)
                    );
                }
            } else {
                $flattened[$currentKey] = $value;
            }
        }

        return $flattened;
    }

    /**
     * Check if a node is a leaf node (contains actual token values)
     *
     * @param array $node
     * @return bool
     */
    private function isLeafNode(array $node): bool
    {
        foreach ($node as $value) {
            if (is_array($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Normalize Figma format tokens to flat structure
     *
     * @param array $tokens
     * @param string $prefix
     * @return array
     */
    private function normalizeFigmaTokens(array $tokens, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($tokens as $key => $value) {
            $currentKey = $prefix ? $prefix . '-' . $key : $key;

            if (is_array($value)) {
                // Figma tokens have a specific structure with $value or value keys
                if (isset($value['$value'])) {
                    $flattened[$currentKey] = $value['$value'];
                } elseif (isset($value['value'])) {
                    $flattened[$currentKey] = $value['value'];
                } else {
                    // Recursively flatten nested structures
                    $flattened = array_merge(
                        $flattened,
                        $this->normalizeFigmaTokens($value, $currentKey)
                    );
                }
            }
        }

        return $flattened;
    }
}
