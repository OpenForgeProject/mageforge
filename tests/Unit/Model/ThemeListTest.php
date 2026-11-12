<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model;

use Magento\Framework\View\Design\Theme\ThemeList as MagentoThemeList;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Model\ThemeList;
use PHPUnit\Framework\TestCase;

class ThemeListTest extends TestCase
{
    public function testReturnsAllThemesFromMagentoThemeList(): void
    {
        $themeOne = $this->createStub(ThemeInterface::class);
        $themeTwo = $this->createStub(ThemeInterface::class);

        $magentoThemeList = $this->createStub(MagentoThemeList::class);
        $magentoThemeList
            ->method('getItems')
            ->willReturn(['frontend/Vendor/one' => $themeOne, 'frontend/Vendor/two' => $themeTwo]);

        $themeList = new ThemeList($magentoThemeList);

        $this->assertSame(
            ['frontend/Vendor/one' => $themeOne, 'frontend/Vendor/two' => $themeTwo],
            $themeList->getAllThemes(),
        );
    }

    public function testReturnsEmptyArrayWhenNoThemesExist(): void
    {
        $magentoThemeList = $this->createStub(MagentoThemeList::class);
        $magentoThemeList->method('getItems')->willReturn([]);

        $themeList = new ThemeList($magentoThemeList);

        $this->assertSame([], $themeList->getAllThemes());
    }
}
