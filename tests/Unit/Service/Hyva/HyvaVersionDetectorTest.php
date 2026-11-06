<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Hyva;

use OpenForgeProject\MageForge\Service\Hyva\HyvaVersionDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HyvaVersionDetectorTest extends TestCase
{
    /**
     * @param array<string, string> $installed Composer package => pretty version
     */
    private function detector(array $installed): HyvaVersionDetector
    {
        return new class ($installed) extends HyvaVersionDetector {
            /**
             * @param array<string, string> $installed
             */
            public function __construct(private readonly array $installed)
            {
            }

            protected function getInstalledVersion(string $package): ?string
            {
                return $this->installed[$package] ?? null;
            }
        };
    }

    /**
     * @param array<string, string> $installed
     */
    #[DataProvider('versionProvider')]
    public function testGetVersion(array $installed, ?string $expected): void
    {
        $this->assertSame($expected, $this->detector($installed)->getVersion());
    }

    /**
     * @return array<string, array{array<string, string>, string|null}>
     */
    public static function versionProvider(): array
    {
        return [
            'default theme 1.4.0' => [['hyva-themes/magento2-default-theme' => '1.4.0'], '1.4.0'],
            'default theme with v prefix' => [['hyva-themes/magento2-default-theme' => 'v1.4.2'], '1.4.2'],
            'default theme 1.3.18' => [['hyva-themes/magento2-default-theme' => '1.3.18'], '1.3.18'],
            'default theme wins over a newer theme module' => [
                [
                    'hyva-themes/magento2-default-theme' => '1.3.18',
                    'hyva-themes/magento2-theme-module' => '1.4.0',
                ],
                '1.3.18',
            ],
            'theme module as fallback' => [['hyva-themes/magento2-theme-module' => '1.4.1'], '1.4.1'],
            'dev branch with digits is not a release' => [
                ['hyva-themes/magento2-default-theme' => 'dev-feature-42'],
                null,
            ],
            'dev branch is not numeric' => [['hyva-themes/magento2-default-theme' => 'dev-main'], null],
            'not installed' => [[], null],
        ];
    }
}
