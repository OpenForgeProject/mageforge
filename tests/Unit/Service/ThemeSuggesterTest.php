<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service;

use Magento\Theme\Model\Theme;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\TestCase;

class ThemeSuggesterTest extends TestCase
{
    private ThemeList $themeList;
    private ThemeSuggester $suggester;

    protected function setUp(): void
    {
        $this->themeList = $this->createMock(ThemeList::class);
        $this->suggester = new ThemeSuggester($this->themeList);
    }

    private function makeTheme(string $code): Theme
    {
        $theme = $this->createMock(Theme::class);
        $theme->method('getCode')->willReturn($code);
        return $theme;
    }

    public function testFindSimilarThemesReturnsEmptyArrayWhenNoThemesRegistered(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([]);

        $result = $this->suggester->findSimilarThemes('frontend/Vendor/Theme');

        $this->assertSame([], $result);
    }

    public function testFindSimilarThemesReturnsExactMatchWhenThemeExistsLiterally(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            $this->makeTheme('frontend/Vendor/Theme'),
        ]);

        $result = $this->suggester->findSimilarThemes('frontend/Vendor/Theme');

        $this->assertContains('frontend/Vendor/Theme', $result);
    }

    public function testFindSimilarThemesReturnsCloseSuggestions(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            $this->makeTheme('frontend/Magento/luma'),
            $this->makeTheme('frontend/Magento/blank'),
            $this->makeTheme('frontend/Vendor/custom'),
        ]);

        // "lumu" is close to "luma" (distance 1)
        $result = $this->suggester->findSimilarThemes('frontend/Magento/lumu');

        $this->assertContains('frontend/Magento/luma', $result);
    }

    public function testFindSimilarThemesReturnsMaxThreeSuggestions(): void
    {
        // Create many similar themes to verify the max 3 limit
        $themes = [];
        for ($i = 0; $i < 10; $i++) {
            $themes[] = $this->makeTheme("frontend/Vendor/Theme{$i}");
        }
        $this->themeList->method('getAllThemes')->willReturn($themes);

        // "Theme" is a substring of all themes, so many would match via substring
        $result = $this->suggester->findSimilarThemes('frontend/Vendor/Theme');

        $this->assertLessThanOrEqual(3, count($result));
    }

    public function testFindSimilarThemesFindsSubstringMatches(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            $this->makeTheme('frontend/Vendor/HyvaTheme'),
            $this->makeTheme('frontend/Magento/luma'),
        ]);

        // "hyva" is a substring (case-insensitive) of "HyvaTheme"
        $result = $this->suggester->findSimilarThemes('hyva');

        $this->assertContains('frontend/Vendor/HyvaTheme', $result);
    }

    public function testFindSimilarThemesSubstringMatchIsCaseInsensitive(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            $this->makeTheme('frontend/Vendor/UPPERCASE'),
        ]);

        $result = $this->suggester->findSimilarThemes('uppercase');

        $this->assertContains('frontend/Vendor/UPPERCASE', $result);
    }

    public function testFindSimilarThemesReturnsEmptyForCompletelyDifferentTheme(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            $this->makeTheme('frontend/Magento/luma'),
        ]);

        // "xyz" has no substring or distance match with "frontend/Magento/luma"
        $result = $this->suggester->findSimilarThemes('xyz');

        $this->assertSame([], $result);
    }

    public function testFindSimilarThemesSortsByLevenshteinDistanceBestFirst(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            $this->makeTheme('frontend/Vendor/ThemeXXX'), // further from 'Theme'
            $this->makeTheme('frontend/Vendor/Theme'),    // exact / distance 0
            $this->makeTheme('frontend/Vendor/Themes'),   // distance 1
        ]);

        // All are substring matches; sort by Levenshtein distance
        $result = $this->suggester->findSimilarThemes('frontend/Vendor/Theme');

        // Best match (distance 0) should come first
        $this->assertSame('frontend/Vendor/Theme', $result[0]);
    }

    public function testFindSimilarThemesUsesOneThirdOfLengthAsThreshold(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            // "frontend/Vendor/Themeabc" vs "frontend/Vendor/Themedef"
            // Levenshtein distance = 3, threshold = floor(24/3) = 8
            $this->makeTheme('frontend/Vendor/Themeabc'),
        ]);

        // "frontend/Vendor/Themedef" has 24 chars, threshold = 8, distance(abc, def part) = 3 → accept
        $result = $this->suggester->findSimilarThemes('frontend/Vendor/Themedef');

        $this->assertContains('frontend/Vendor/Themeabc', $result);
    }
}
