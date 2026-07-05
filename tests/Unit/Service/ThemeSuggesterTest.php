<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\TestCase;

class ThemeSuggesterTest extends TestCase
{
    public function testSuggestsThemeForSmallTypo(): void
    {
        $suggester = $this->createSuggester(['Magento/luma', 'Vendor/checkout', 'Hyva/default']);

        $this->assertSame(['Magento/luma'], $suggester->findSimilarThemes('Magento/lume'));
    }

    public function testSuggestsSubstringMatchesRegardlessOfDistance(): void
    {
        $suggester = $this->createSuggester(['Vendor/super-shop-theme', 'Magento/blank']);

        $this->assertSame(['Vendor/super-shop-theme'], $suggester->findSimilarThemes('shop'));
    }

    public function testMatchesUppercaseInputAgainstLowercaseCode(): void
    {
        $suggester = $this->createSuggester(['hyva/default']);

        $this->assertSame(['hyva/default'], $suggester->findSimilarThemes('HYVA/DEFAULT'));
    }

    public function testMatchesLowercaseInputAgainstUppercaseCode(): void
    {
        $suggester = $this->createSuggester(['HYVA/DEFAULT']);

        $this->assertSame(['HYVA/DEFAULT'], $suggester->findSimilarThemes('hyva/default'));
    }

    public function testAcceptsDistancesUpToOneThirdOfInputLengthOnly(): void
    {
        // Input length 12 → threshold 4; "Magento/blank" has distance 4, "Magento/blanko" distance 5.
        $suggester = $this->createSuggester(['Magento/luma', 'Magento/blank', 'Magento/blanko']);

        $this->assertSame(['Magento/luma', 'Magento/blank'], $suggester->findSimilarThemes('Magento/lume'));
    }

    public function testSortsSuggestionsByDistanceAndLimitsToThree(): void
    {
        $suggester = $this->createSuggester([
            'Magento/luma2',
            'Magento/luma',
            'Magento/lumaX9',
            'Magento/luma34',
        ]);

        $suggestions = $suggester->findSimilarThemes('Magento/luma');

        $this->assertCount(3, $suggestions);
        $this->assertSame('Magento/luma', $suggestions[0]);
    }

    public function testReturnsEmptyArrayWhenNothingIsSimilar(): void
    {
        $suggester = $this->createSuggester(['Magento/blank']);

        $this->assertSame([], $suggester->findSimilarThemes('Hyva/checkout-theme'));
    }

    public function testReturnsEmptyArrayForEmptyInput(): void
    {
        $suggester = $this->createSuggester(['Magento/blank']);

        $this->assertSame([], $suggester->findSimilarThemes(''));
    }

    public function testReturnsEmptyArrayForOverlongInput(): void
    {
        // The theme code would substring-match, so only the length guard produces the empty result.
        $suggester = $this->createSuggester(['Vendor/' . str_repeat('a', 256)]);

        $this->assertSame([], $suggester->findSimilarThemes(str_repeat('a', 256)));
    }

    public function testAcceptsInputOfExactlyMaxLength(): void
    {
        $longCode = 'Vendor/' . str_repeat('a', 255);
        $suggester = $this->createSuggester([$longCode]);

        $this->assertSame([$longCode], $suggester->findSimilarThemes(str_repeat('a', 255)));
    }

    public function testOverlongThemeCodeIsOnlyConsideredForSubstringMatch(): void
    {
        $longCode = 'Vendor/' . str_repeat('x', 260) . '-shop';
        $suggester = $this->createSuggester([$longCode, 'Magento/blank']);

        $this->assertSame([$longCode], $suggester->findSimilarThemes('shop'));
        $this->assertSame([], $suggester->findSimilarThemes('Vendor/theme'));
    }

    /**
     * @param array<int, string> $themeCodes
     */
    private function createSuggester(array $themeCodes): ThemeSuggester
    {
        $themes = [];
        foreach ($themeCodes as $code) {
            $theme = $this->createMock(ThemeInterface::class);
            $theme->method('getCode')->willReturn($code);
            $themes[$code] = $theme;
        }

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn($themes);

        return new ThemeSuggester($themeList);
    }
}
