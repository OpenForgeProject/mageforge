<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service;

use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeParentResolver;
use PHPUnit\Framework\TestCase;

class ThemeParentResolverTest extends TestCase
{
    public function testReturnsBuildableParentsTopmostFirst(): void
    {
        $base = $this->theme('Vendor/base', null);
        $video = $this->theme('Vendor/video', $base);
        $child = $this->theme('Vendor/child', $video);

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$base, $video, $child]);

        $themePath = $this->createMock(ThemePath::class);
        $themePath->method('getPath')->willReturnCallback(static fn(string $code): ?string => $code === 'Vendor/video'
            ? null
            : '/path/' . $code);

        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->method('getBuilder')->willReturn($this->createMock(BuilderInterface::class));

        $resolver = new ThemeParentResolver($themeList, $themePath, $builderPool);

        $this->assertSame(['Vendor/base'], $resolver->getBuildableParents('Vendor/child'));
        $this->assertSame([], $resolver->getBuildableParents('Vendor/base'));
        $this->assertSame([], $resolver->getBuildableParents('Unknown/theme'));
    }

    public function testOrdersFullChainWithTopmostFirst(): void
    {
        $base = $this->theme('Vendor/base', null);
        $video = $this->theme('Vendor/video', $base);
        $child = $this->theme('Vendor/child', $video);

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$child]);
        $themePath = $this->createMock(ThemePath::class);
        $themePath->method('getPath')->willReturn('/path');
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->method('getBuilder')->willReturn($this->createMock(BuilderInterface::class));

        $resolver = new ThemeParentResolver($themeList, $themePath, $builderPool);

        $this->assertSame(['Vendor/base', 'Vendor/video'], $resolver->getBuildableParents('Vendor/child'));
    }

    public function testSkipsVendorThemes(): void
    {
        $base = $this->theme('App/base', null);
        $vendor = $this->theme('Hyva/default', $base);
        $child = $this->theme('App/child', $vendor);

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$child]);
        $themePath = $this->createMock(ThemePath::class);
        $themePath->method('getPath')->willReturnCallback(static fn(string $code): string => $code === 'Hyva/default'
            ? '/var/www/html/vendor/hyva-themes/default-theme'
            : '/var/www/html/app/design/frontend/' . $code);
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->method('getBuilder')->willReturn($this->createMock(BuilderInterface::class));

        $resolver = new ThemeParentResolver($themeList, $themePath, $builderPool);

        $this->assertSame(['App/base'], $resolver->getBuildableParents('App/child'));
    }

    private function theme(string $code, ?ThemeInterface $parent): ThemeInterface
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($code);
        $theme->method('getParentTheme')->willReturn($parent);

        return $theme;
    }
}
