<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use OpenForgeProject\MageForge\Model\ThemeList;

/**
 * Service for suggesting similar theme names when invalid themes are provided
 *
 * Uses Levenshtein distance algorithm similar to Symfony's command suggestion feature
 */
class ThemeSuggester
{
    /**
     * Maximum number of suggestions to return
     */
    private const MAX_SUGGESTIONS = 3;

    /**
     * Constructor
     *
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemeList $themeList
    ) {
    }

    /**
     * Find similar theme codes based on Levenshtein distance
     *
     * Algorithm based on Symfony's findAlternatives() method:
     * - Calculates Levenshtein distance between input and each theme code
     * - Accepts suggestions if distance ≤ strlen/3 OR substring match
     * - Returns top matches sorted by distance
     *
     * @param string $invalidTheme The invalid theme code entered by user
     * @return array Array of suggested theme codes (max 3)
     */
    public function findSimilarThemes(string $invalidTheme): array
    {
        $themes = $this->themeList->getAllThemes();
        $threshold = (int) (strlen($invalidTheme) / 3);
        $suggestions = [];

        foreach ($themes as $theme) {
            $themeCode = $theme->getCode();
            $distance = levenshtein($invalidTheme, $themeCode);

            // Accept if: distance ≤ 1/3 of input length OR substring match (case-insensitive)
            if ($distance <= $threshold || str_contains(strtolower($themeCode), strtolower($invalidTheme))) {
                $suggestions[$themeCode] = $distance;
            }
        }

        // Sort by distance (best matches first)
        asort($suggestions);

        // Return max 3 suggestions
        return array_slice(array_keys($suggestions), 0, self::MAX_SUGGESTIONS);
    }
}
