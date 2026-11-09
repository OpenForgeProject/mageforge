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
     * @var OverrideRemover&MockObject
     */
    private MockObject $remover;

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
        $this->remover = $this->createMock(OverrideRemover::class);
        $this->tester = $this->createTester($themeList);
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

    public function testPrintsEmptyJsonArrayWhenOnlyVendorThemesExist(): void
    {
        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn(['frontend/Magento/luma' => new FakeTheme('Magento/luma')]);
        $tester = $this->createTester($themeList);

        $exitCode = $tester->execute(['--format' => 'json']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertSame('[]', trim($tester->getDisplay()));
    }

    public function testFailOnFoundStillAppliesWhenDeleteLeavesUnsafeFindings(): void
    {
        $this->scanner->method('scan')->willReturn([
            new ObsoleteOverride(
                'Vendor/theme',
                '/magento/app/design/b.phtml',
                'Magento_Catalog::b.phtml',
                ObsoleteReason::OUTDATED,
                'Outdated',
                false,
            ),
        ]);

        $exitCode = $this->tester->execute(['--delete' => true, '--yes' => true, '--fail-on-found' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    public function testFailsWhenAFileCouldNotBeDeleted(): void
    {
        $finding = new ObsoleteOverride(
            'Vendor/theme',
            '/magento/app/design/a.phtml',
            'Magento_Catalog::a.phtml',
            ObsoleteReason::IDENTICAL,
            'Identical',
            true,
        );
        $this->scanner->method('scan')->willReturn([$finding]);
        $this->remover->method('remove')->willReturn(false);

        $exitCode = $this->tester->execute(['--delete' => true, '--yes' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    private function createTester(ThemeList $themeList): CommandTester
    {
        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/magento');

        return new CommandTester(new ObsoleteCommand(
            $themeList,
            $this->createMock(ThemeSuggester::class),
            $this->scanner,
            $this->remover,
            $this->createMock(AreaEmulator::class),
            $this->createMock(CacheCleaner::class),
            $directoryList,
        ));
    }
}
