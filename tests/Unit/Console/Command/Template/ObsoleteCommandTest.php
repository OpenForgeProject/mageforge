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
     * @var CacheCleaner&MockObject
     */
    private MockObject $cacheCleaner;

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
        $this->cacheCleaner = $this->createMock(CacheCleaner::class);
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

    public function testPrintsTableWithSafeMarkerAndRelativePaths(): void
    {
        $this->scanner->method('scan')->willReturn([
            $this->finding('a', ObsoleteReason::IDENTICAL, true),
            $this->finding('b', ObsoleteReason::OUTDATED, false),
        ]);

        $exitCode = $this->tester->execute([]);
        $display = $this->tester->getDisplay();

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('Reason', $display);
        $this->assertStringContainsString(ObsoleteReason::IDENTICAL->label() . ' *', $display);
        $this->assertStringNotContainsString(ObsoleteReason::OUTDATED->label() . ' *', $display);
        $this->assertStringContainsString('app/design/a.phtml', $display);
        $this->assertStringNotContainsString('/magento/app/design/a.phtml', $display);
        $this->assertStringContainsString('2 obsolete override(s) found, 1 marked with *', $display);
    }

    public function testReportsCheckedThemesWhenNothingIsFound(): void
    {
        $this->scanner->method('scan')->willReturn([]);

        $this->tester->execute([]);

        $this->assertStringContainsString('No obsolete overrides found in: Vendor/theme', $this->tester->getDisplay());
    }

    public function testChecksExplicitlyGivenVendorTheme(): void
    {
        $this->scanner->expects($this->once())->method('scan')->willReturn([]);

        $this->tester->execute(['themeCodes' => ['Magento/luma']]);

        $this->assertStringContainsString('Magento/luma', $this->tester->getDisplay());
    }

    public function testDoesNotDeleteWhenConfirmationIsDeclined(): void
    {
        $this->scanner->method('scan')->willReturn([$this->finding('a', ObsoleteReason::IDENTICAL, true)]);
        $this->remover->expects($this->never())->method('remove');

        $this->tester->setInputs(['no', 'no']);
        $this->tester->execute(['--delete' => true], ['interactive' => true]);

        $this->assertStringContainsString('Delete 1 override file(s)?', $this->tester->getDisplay());
        $this->assertStringContainsString('Aborted, no files were deleted.', $this->tester->getDisplay());
    }

    public function testDeclinesByDefaultWhenConfirmationIsAnsweredWithEnter(): void
    {
        $this->scanner->method('scan')->willReturn([$this->finding('a', ObsoleteReason::IDENTICAL, true)]);
        $this->remover->expects($this->never())->method('remove');

        $this->tester->setInputs(['no', '']);
        $this->tester->execute(['--delete' => true], ['interactive' => true]);
    }

    public function testDeletesAfterConfirmationAndCleansCaches(): void
    {
        $this->scanner->method('scan')->willReturn([$this->finding('a', ObsoleteReason::IDENTICAL, true)]);
        $this->remover->expects($this->once())->method('remove')->willReturn(true);
        $this->cacheCleaner->expects($this->once())->method('clean')->willReturn(true);

        $this->tester->setInputs(['no', 'yes']);
        $exitCode = $this->tester->execute(['--delete' => true, '--fail-on-found' => true], ['interactive' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Deleted 1 override file(s).', $this->tester->getDisplay());
    }

    public function testAsksForDeepScanInteractivelyAndPassesAnswerToScanner(): void
    {
        $this->scanner->expects($this->once())->method('scan')->with($this->anything(), true)->willReturn([]);

        $this->tester->setInputs(['yes']);
        $this->tester->execute([], ['interactive' => true]);

        $this->assertStringContainsString('--deep', $this->tester->getDisplay());
    }

    public function testDoesNotAskAnyQuestionWhenNonInteractive(): void
    {
        $this->scanner->expects($this->once())->method('scan')->with($this->anything(), false)->willReturn([
            $this->finding('a', ObsoleteReason::IDENTICAL, true),
        ]);
        $this->remover->expects($this->never())->method('remove');

        $this->tester->execute([], ['interactive' => false]);

        $this->assertStringNotContainsString('Delete 1 override', $this->tester->getDisplay());
    }

    public function testSkipsDeepQuestionWhenFlagIsGiven(): void
    {
        $this->scanner->expects($this->once())->method('scan')->with($this->anything(), true)->willReturn([]);

        $this->tester->execute(['--deep' => true], ['interactive' => true]);

        $this->assertStringNotContainsString('slower)?', $this->tester->getDisplay());
    }

    public function testDeletesOnlySafeFindingsAndNamesFailedFiles(): void
    {
        $safe = $this->finding('a', ObsoleteReason::IDENTICAL, true);
        $unsafe = $this->finding('b', ObsoleteReason::OUTDATED, false);
        $this->scanner->method('scan')->willReturn([$safe, $unsafe]);
        $this->remover->expects($this->once())->method('remove')->with($safe)->willReturn(false);
        $this->cacheCleaner->expects($this->never())->method('clean');

        $exitCode = $this->tester->execute(['--delete' => true, '--yes' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('Could not delete app/design/a.phtml', $this->tester->getDisplay());
    }

    public function testFailsWhenCacheCleaningFailsAfterDeletion(): void
    {
        $this->scanner->method('scan')->willReturn([$this->finding('a', ObsoleteReason::IDENTICAL, true)]);
        $this->remover->method('remove')->willReturn(true);
        $this->cacheCleaner->method('clean')->willReturn(false);

        $exitCode = $this->tester->execute(['--delete' => true, '--yes' => true]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    public function testReportsNothingToDeleteWhenNoFindingIsSafe(): void
    {
        $this->scanner->method('scan')->willReturn([$this->finding('b', ObsoleteReason::OUTDATED, false)]);
        $this->remover->expects($this->never())->method('remove');

        $this->tester->execute(['--delete' => true, '--yes' => true]);

        $this->assertStringContainsString('Nothing to delete.', $this->tester->getDisplay());
    }

    public function testJsonOutputIsPrettyPrintedWithUnescapedSlashes(): void
    {
        $this->scanner->method('scan')->willReturn([$this->finding('a', ObsoleteReason::IDENTICAL, true)]);

        $this->tester->execute(['--format' => 'json']);

        $this->assertStringContainsString("\n    {", $this->tester->getDisplay());
        $this->assertStringContainsString('"template": "Magento_Catalog::a.phtml"', $this->tester->getDisplay());
        $this->assertStringContainsString('"safe_to_delete": true', $this->tester->getDisplay());
        $this->assertStringNotContainsString('\\/', $this->tester->getDisplay());
    }

    public function testRejectsUnknownFormat(): void
    {
        $this->assertSame(Cli::RETURN_FAILURE, $this->tester->execute(['--format' => 'xml']));
    }

    private function finding(string $name, ObsoleteReason $reason, bool $safe): ObsoleteOverride
    {
        return new ObsoleteOverride(
            'Vendor/theme',
            '/magento/app/design/' . $name . '.phtml',
            'Magento_Catalog::' . $name . '.phtml',
            $reason,
            'Details',
            $safe,
        );
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
            $this->cacheCleaner,
            $directoryList,
        ));
    }
}
