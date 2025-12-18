<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\HyvaTokens;

/**
 * Main token processor that orchestrates the token generation process
 */
class TokenProcessor
{
    public function __construct(
        private readonly ConfigReader $configReader,
        private readonly TokenParser $tokenParser,
        private readonly CssGenerator $cssGenerator
    ) {
    }

    /**
     * Process tokens for a theme
     *
     * @param string $themePath
     * @return array [success: bool, message: string, outputPath: string|null]
     */
    public function process(string $themePath): array
    {
        try {
            // Read configuration
            $config = $this->configReader->getConfig($themePath);

            // Check if token source exists
            if (!$this->configReader->hasTokenSource($themePath, $config)) {
                return [
                    'success' => false,
                    'message' => "No token source found. Create a {$config['src']} file " .
                                 "or add 'values' to hyva.config.json",
                    'outputPath' => null,
                ];
            }

            // Determine source path or inline values
            $sourcePath = null;
            $inlineValues = $config['values'];

            if ($inlineValues === null) {
                $sourcePath = $this->configReader->getTokenSourcePath($themePath, $config['src']);
            }

            // Parse tokens
            $tokens = $this->tokenParser->parse($sourcePath, $inlineValues, $config['format']);

            if (empty($tokens)) {
                return [
                    'success' => false,
                    'message' => 'No tokens found in source',
                    'outputPath' => null,
                ];
            }

            // Generate CSS
            $css = $this->cssGenerator->generate($tokens, $config['cssSelector']);

            // Write to output file
            $outputPath = $this->configReader->getOutputPath($themePath);
            $this->cssGenerator->write($css, $outputPath);

            return [
                'success' => true,
                'message' => "Successfully generated tokens CSS",
                'outputPath' => $outputPath,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing tokens: ' . $e->getMessage(),
                'outputPath' => null,
            ];
        }
    }
}
