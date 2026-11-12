<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\HyvaThemeDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HyvaThemeDetectorTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: array<string, mixed>|null, 2: bool}>
     */
    public static function themeFilesProvider(): array
    {
        return [
            'hyva in theme.xml' => ['<theme><parent>Hyva/default</parent></theme>', null, true],
            'hyva in theme.xml, other composer name' => [
                '<theme><parent>Hyva/default</parent></theme>',
                ['name' => 'vendor/custom-theme'],
                true,
            ],
            'neutral theme.xml, hyva composer name' => [
                '<theme><title>Custom</title></theme>',
                ['name' => 'hyva-themes/theme-default'],
                true,
            ],
            'neutral theme.xml and composer name' => [
                '<theme><title>Custom</title></theme>',
                ['name' => 'vendor/custom-theme'],
                false,
            ],
            'composer json without name' => [null, ['require' => []], false],
            'no theme files' => [null, null, false],
        ];
    }

    /**
     * @param string|null $themeXml
     * @param array<string, mixed>|null $composerJson
     * @param bool $expected
     */
    #[DataProvider('themeFilesProvider')]
    public function testIsHyvaTheme(?string $themeXml, ?array $composerJson, bool $expected): void
    {
        $fileDriver = $this->createStub(File::class);
        $fileDriver->method('isExists')->willReturnMap([
            ['/theme/theme.xml', $themeXml !== null],
            ['/theme/composer.json', $composerJson !== null],
        ]);
        $fileDriver->method('fileGetContents')->willReturnMap([
            ['/theme/theme.xml', null, null, (string) $themeXml],
            ['/theme/composer.json', null, null, json_encode($composerJson)],
        ]);

        $this->assertSame($expected, (new HyvaThemeDetector($fileDriver))->isHyvaTheme('/theme/'));
    }
}
