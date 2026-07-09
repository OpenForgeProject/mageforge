<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Theme\CleanCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeCleaner;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleanCommandTest extends TestCase
{
    private ThemeCleaner&MockObject $themeCleaner;
    private ThemeList&MockObject $themeList;
    private ThemePath&MockObject $themePath;
    private ThemeSuggester&MockObject $themeSuggester;
    private CleanCommand $command;

    protected function setUp(): void
    {
        $this->themeCleaner = $this->createMock(ThemeCleaner::class);
        $this->themeList = $this->createMock(ThemeList::class);
        $this->themePath = $this->createMock(ThemePath::class);
        $this->themeSuggester = $this->createMock(ThemeSuggester::class);
        $this->command = new CleanCommand(
            $this->themeCleaner,
            $this->themeList,
            $this->themePath,
            $this->themeSuggester,
        );
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:theme:clean', $this->command->getName());
        $this->assertSame(['frontend:clean'], $this->command->getAliases());
    }

    public function testCleansSingleThemeIncludingGlobalDirectories(): void
    {
        $this->themePath->method('getPath')->with('Vendor/theme')->willReturn('/path');
        $this->themeCleaner->method('cleanViewPreprocessed')->with('Vendor/theme')->willReturn(2);
        $this->themeCleaner->method('cleanPubStatic')->with('Vendor/theme')->willReturn(1);
        $this->themeCleaner->expects($this->once())->method('cleanPageCache')->willReturn(1);
        $this->themeCleaner->expects($this->once())->method('cleanVarTmp')->willReturn(1);
        $this->themeCleaner->expects($this->once())->method('cleanGenerated')->willReturn(1);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Cleaning static files for theme: Vendor/theme', $display);
        $this->assertStringContainsString("Cleaned 6 directories for theme 'Vendor/theme'", $display);
        $this->assertStringContainsString("Successfully cleaned 6 directories for theme 'Vendor/theme'", $display);
    }

    public function testCleansGlobalDirectoriesOnlyOnceForMultipleThemes(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(1);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(1);
        $this->themeCleaner->expects($this->once())->method('cleanPageCache')->willReturn(0);
        $this->themeCleaner->expects($this->once())->method('cleanVarTmp')->willReturn(0);
        $this->themeCleaner->expects($this->once())->method('cleanGenerated')->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/one', 'Vendor/two']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Cleaning theme 1 of 2: Vendor/one', $display);
        $this->assertStringContainsString('Cleaning theme 2 of 2: Vendor/two', $display);
        $this->assertStringContainsString('Successfully cleaned 4 directories across 2 themes', $display);
    }

    public function testDryRunDoesNotDeleteAndAnnouncesMode(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanViewPreprocessed')
            ->with('Vendor/theme', $this->anything(), true, true)
            ->willReturn(1);
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanPubStatic')
            ->with('Vendor/theme', $this->anything(), true, true)
            ->willReturn(0);
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanPageCache')
            ->with($this->anything(), true, true)
            ->willReturn(0);
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanVarTmp')
            ->with($this->anything(), true, true)
            ->willReturn(0);
        $this->themeCleaner
            ->expects($this->once())
            ->method('cleanGenerated')
            ->with($this->anything(), true, true)
            ->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme'], '--dry-run' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE: No files will be deleted', $display);
        $this->assertStringContainsString("Would clean 1 directory for theme 'Vendor/theme'", $display);
        $this->assertStringNotContainsString('directories', $display, 'Singular form required for one directory');
    }

    public function testReportsWhenNothingNeedsCleaning(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(0);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(0);
        $this->themeCleaner->method('cleanPageCache')->willReturn(0);
        $this->themeCleaner->method('cleanVarTmp')->willReturn(0);
        $this->themeCleaner->method('cleanGenerated')->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("No files to clean for theme 'Vendor/theme'", $display);
        $this->assertStringNotContainsString('Cleaned 0 director', $display);
        $this->assertStringNotContainsString('Successfully cleaned 0', $display);
    }

    public function testCleansAllThemesWithAllOption(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeThemeWithTitle('Vendor/one', 'One'),
            new FakeThemeWithTitle('Vendor/two', 'Two'),
        ]);
        $this->themePath->method('getPath')->willReturn('/path');
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(1);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(0);
        $this->themeCleaner->method('cleanPageCache')->willReturn(0);
        $this->themeCleaner->method('cleanVarTmp')->willReturn(0);
        $this->themeCleaner->method('cleanGenerated')->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--all' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('Cleaning all 2 themes...', $tester->getDisplay());
    }

    public function testAllOptionWithoutThemesShowsInfo(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([]);
        $this->themeCleaner->expects($this->never())->method('cleanViewPreprocessed');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--all' => true]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No themes found.', $display);
        $this->assertStringNotContainsString('No files were cleaned.', $display, 'Command must exit early');
    }

    public function testNoArgumentsInNonInteractiveModeListsThemes(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeThemeWithTitle('Vendor/theme', 'Vendor Theme'),
        ]);
        $this->themeCleaner->expects($this->never())->method('cleanViewPreprocessed');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No theme specified. Available themes:', $display);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('(Vendor Theme)', $display);
        $this->assertStringContainsString('Usage: bin/magento mageforge:theme:clean <theme-code>', $display);
        $this->assertStringContainsString('bin/magento mageforge:theme:clean --all', $display);
        $this->assertStringContainsString('Example: bin/magento mageforge:theme:clean Magento/luma', $display);
    }

    public function testUnknownThemeWithoutSuggestionsCountsAsFailed(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/unknown', 'Vendor/missing']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No files were cleaned.', $display);
        $this->assertStringContainsString('Failed to process 2 themes: Vendor/unknown, Vendor/missing', $display);
    }

    public function testResolvesVendorWildcardBeforeCleaning(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeThemeWithTitle('Vendor/one', 'One'),
            new FakeThemeWithTitle('Other/theme', 'Other'),
        ]);
        $this->themePath->method('getPath')->with('Vendor/one')->willReturn('/path');
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(1);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(0);
        $this->themeCleaner->method('cleanPageCache')->willReturn(0);
        $this->themeCleaner->method('cleanVarTmp')->willReturn(0);
        $this->themeCleaner->method('cleanGenerated')->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/*']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $expected = "Resolved vendor 'Vendor/*' to 1 theme(s): Vendor/one";
        $this->assertStringContainsString($expected, $tester->getDisplay());
    }

    public function testMixedSuccessAndFailureSummaryCountsCorrectly(): void
    {
        $this->themePath
            ->method('getPath')
            ->willReturnCallback(static fn(string $code): ?string => $code === 'Vendor/good' ? '/path' : null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);
        $this->themeCleaner->method('cleanViewPreprocessed')->willReturn(2);
        $this->themeCleaner->method('cleanPubStatic')->willReturn(0);
        $this->themeCleaner->method('cleanPageCache')->willReturn(0);
        $this->themeCleaner->method('cleanVarTmp')->willReturn(0);
        $this->themeCleaner->method('cleanGenerated')->willReturn(0);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/good', 'Vendor/bad']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Successfully cleaned 2 directories across 1 theme', $display);
        $this->assertStringNotContainsString('across 1 themes', $display, 'Singular form required for one theme');
        $this->assertStringContainsString('Failed to process 1 theme: Vendor/bad', $display);
    }
}
