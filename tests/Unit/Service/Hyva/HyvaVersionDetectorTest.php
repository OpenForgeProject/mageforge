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
    public function testIsAtLeast(array $installed, bool $expected): void
    {
        $this->assertSame($expected, $this->detector($installed)->isAtLeast('1.4'));
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function versionProvider(): array
    {
        return [
            'default theme 1.4.0' => [['hyva-themes/magento2-default-theme' => '1.4.0'], true],
            'default theme with v prefix' => [['hyva-themes/magento2-default-theme' => 'v1.4.2'], true],
            'default theme 1.3.18' => [['hyva-themes/magento2-default-theme' => '1.3.18'], false],
            'newer module does not count when default theme is older' => [
                [
                    'hyva-themes/magento2-default-theme' => '1.3.18',
                    'hyva-themes/magento2-theme-module' => '1.4.0',
                ],
                false,
            ],
            'theme module as fallback' => [['hyva-themes/magento2-theme-module' => '1.4.1'], true],
            'dev branch is not numeric' => [['hyva-themes/magento2-default-theme' => 'dev-main'], false],
            'not installed' => [[], false],
        ];
    }

    public function testGetVersionReturnsNormalisedVersion(): void
    {
        $detector = $this->detector(['hyva-themes/magento2-default-theme' => 'v1.4.2']);

        $this->assertSame('1.4.2', $detector->getVersion());
    }
}
