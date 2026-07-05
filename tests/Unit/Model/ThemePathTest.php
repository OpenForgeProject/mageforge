<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use OpenForgeProject\MageForge\Model\ThemePath;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ThemePathTest extends TestCase
{
    private ComponentRegistrarInterface&MockObject $componentRegistrar;
    private ThemePath $themePath;

    protected function setUp(): void
    {
        $this->componentRegistrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->themePath = new ThemePath($this->componentRegistrar);
    }

    public function testReturnsFrontendThemePath(): void
    {
        $this->componentRegistrar
            ->method('getPaths')
            ->with(ComponentRegistrar::THEME)
            ->willReturn([
                'frontend/Vendor/theme' => '/app/design/frontend/Vendor/theme',
                'adminhtml/Vendor/theme' => '/app/design/adminhtml/Vendor/theme',
            ]);

        $this->assertSame('/app/design/frontend/Vendor/theme', $this->themePath->getPath('Vendor/theme'));
    }

    public function testFallsBackToAdminhtmlThemePath(): void
    {
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturn([
                'adminhtml/Vendor/backend' => '/app/design/adminhtml/Vendor/backend',
            ]);

        $this->assertSame('/app/design/adminhtml/Vendor/backend', $this->themePath->getPath('Vendor/backend'));
    }

    public function testReturnsNullForUnknownTheme(): void
    {
        $this->componentRegistrar
            ->method('getPaths')
            ->willReturn([
                'frontend/Other/theme' => '/app/design/frontend/Other/theme',
            ]);

        $this->assertNull($this->themePath->getPath('Vendor/unknown'));
    }

    public function testReturnsNullWhenNoThemesRegistered(): void
    {
        $this->componentRegistrar->method('getPaths')->willReturn([]);

        $this->assertNull($this->themePath->getPath('Vendor/theme'));
    }
}
