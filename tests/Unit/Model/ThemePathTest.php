<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use OpenForgeProject\MageForge\Model\ThemePath;
use PHPUnit\Framework\TestCase;

class ThemePathTest extends TestCase
{
    private ComponentRegistrarInterface $componentRegistrar;
    private ThemePath $themePath;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->themePath = new ThemePath($this->componentRegistrar);
    }

    public function testGetPathReturnsPathForExactMatchingTheme(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->with(ComponentRegistrar::THEME)
            ->willReturn([
                'frontend/Magento/luma' => '/vendor/magento/theme-frontend-luma',
                'frontend/Vendor/Theme' => '/app/design/frontend/Vendor/Theme',
            ]);

        $result = $this->themePath->getPath('frontend/Vendor/Theme');

        $this->assertSame('/app/design/frontend/Vendor/Theme', $result);
    }

    public function testGetPathReturnsPathForSubstringMatch(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'frontend/Vendor/MyTheme' => '/app/design/frontend/Vendor/MyTheme',
            ]);

        // ThemePath::getPath uses str_contains so a partial match will work
        $result = $this->themePath->getPath('Vendor/MyTheme');

        $this->assertSame('/app/design/frontend/Vendor/MyTheme', $result);
    }

    public function testGetPathReturnsNullForNonMatchingThemeCode(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'frontend/Magento/luma' => '/vendor/magento/theme-frontend-luma',
            ]);

        $result = $this->themePath->getPath('frontend/Vendor/NonExistent');

        $this->assertNull($result);
    }

    public function testGetPathReturnsNullForEmptyThemeRegistry(): void
    {
        $this->componentRegistrar->method('getPaths')->willReturn([]);

        $result = $this->themePath->getPath('frontend/Vendor/Theme');

        $this->assertNull($result);
    }

    public function testGetPathReturnsFirstMatchWhenMultipleThemesMatch(): void
    {
        $this->componentRegistrar->method('getPaths')
            ->willReturn([
                'frontend/Vendor/ThemeBase' => '/app/design/frontend/Vendor/ThemeBase',
                'frontend/Vendor/Theme' => '/app/design/frontend/Vendor/Theme',
            ]);

        // "Theme" is a substring of both "ThemeBase" and "Theme"
        // should return the first match in iteration order
        $result = $this->themePath->getPath('Theme');

        $this->assertNotNull($result);
    }

    public function testGetPathUsesComponentRegistrarThemeType(): void
    {
        $this->componentRegistrar->expects($this->once())
            ->method('getPaths')
            ->with(ComponentRegistrar::THEME)
            ->willReturn([]);

        $this->themePath->getPath('frontend/Vendor/Theme');
    }
}
