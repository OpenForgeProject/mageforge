<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Template;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Template\ObsoleteCommand;
use OpenForgeProject\MageForge\Model\ObsoleteOverride;
use OpenForgeProject\MageForge\Model\ObsoleteReason;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\TemplateOverride\AreaEmulator;
use OpenForgeProject\MageForge\Service\TemplateOverride\ObsoleteOverrideScanner;
use OpenForgeProject\MageForge\Service\TemplateOverride\OverrideRemover;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride\FakeTheme;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ObsoleteCommandTest extends TestCase
{
    /**
     * @var ObsoleteOverrideScanner&MockObject
     */
    private MockObject $scanner;

    /**
     * @var CommandTester
     */
    private CommandTester $tester;

    protected function setUp(): void
    {
        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([
            'frontend/Vendor/theme' => new FakeTheme('Vendor/theme'),
            'frontend/Magento/luma' => new FakeTheme('Magento/luma'),
        ]);
        $this->scanner = $this->createMock(ObsoleteOverrideScanner::class);
        $this->scanner->method('isVendorTheme')
            ->willReturnCallback(static fn(FakeTheme $theme): bool => $theme->getCode() === 'Magento/luma');
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/magento');

        $command = new ObsoleteCommand(
            $themeList,
            $this->createMock(ThemeSuggester::class),
            $this->scanner,
            $this->createMock(OverrideRemover::class),
            $this->createMock(AreaEmulator::class),
            $this->createMock(CacheCleaner::class),
            $directoryList,
        );
        $this->tester = new CommandTester($command);
    }

    public function testChecksOnlyNonVendorThemesByDefaultAndPrintsJson(): void
    {
        $this->scanner->expects($this->once())->method('scan')->willReturn([
            new ObsoleteOverride(
                'Vendor/theme',
                '/magento/app/design/a.phtml',
                'Magento_Catalog::a.phtml',
                ObsoleteReason::IDENTICAL,
                'Identical',
                true,
            ),
        ]);

        $exitCode = $this->tester->execute(['--format' => 'json', '--fail-on-found' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        /** @var array<int, array<string, mixed>> $json */
        $json = json_decode($this->tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('identical', $json[0]['reason']);
        $this->assertSame('app/design/a.phtml', $json[0]['file']);
    }

    public function testRejectsDeleteWithJsonFormat(): void
    {
        $exitCode = $this->tester->execute(['--delete' => true, '--format' => 'json']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }
}
