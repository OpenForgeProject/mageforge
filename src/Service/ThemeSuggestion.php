<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use OpenForgeProject\MageForge\Model\ThemeList;

/**
 * Service for suggesting similar theme names
 */
class ThemeSuggestion
{
    /**
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemeList $themeList
    ) {
    }

    /**
     * Find the most similar theme names to the input
     *
     * @param string $input The theme name that was not found
     * @param int $maxSuggestions Maximum number of suggestions to return
     * @return array Array of suggested theme codes
     */
    public function getSuggestions(string $input, int $maxSuggestions = 3): array
    {
        $themes = $this->themeList->getAllThemes();
        $suggestions = [];

        foreach ($themes as $theme) {
            $themeCode = $theme->getCode();
            $similarity = $this->calculateSimilarity($input, $themeCode);
            
            if ($similarity > 0) {
                $suggestions[] = [
                    'code' => $themeCode,
                    'similarity' => $similarity
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($suggestions, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return only the theme codes, limited by maxSuggestions
        return array_slice(
            array_column($suggestions, 'code'),
            0,
            $maxSuggestions
        );
    }

    /**
     * Calculate similarity between two strings
     * Uses a combination of Levenshtein distance and case-insensitive comparison
     *
     * @param string $input User input
     * @param string $target Theme code to compare against
     * @return float Similarity score (higher is more similar)
     */
    private function calculateSimilarity(string $input, string $target): float
    {
        // Normalize inputs for comparison
        $inputLower = strtolower($input);
        $targetLower = strtolower($target);

        // Exact match (case-insensitive) gets highest score
        if ($inputLower === $targetLower) {
            return 100.0;
        }

        // Check if input is a substring of target
        if (str_contains($targetLower, $inputLower)) {
            return 90.0 + (strlen($inputLower) / strlen($targetLower)) * 5;
        }

        // Calculate Levenshtein distance
        $distance = levenshtein($inputLower, $targetLower);
        
        // If distance is too large, consider it not similar
        $maxLength = max(strlen($inputLower), strlen($targetLower));
        if ($distance > $maxLength * 0.6) {
            return 0.0;
        }

        // Convert distance to similarity score (0-80 range)
        $similarity = (1 - ($distance / $maxLength)) * 80;

        return max(0.0, $similarity);
    }
}
