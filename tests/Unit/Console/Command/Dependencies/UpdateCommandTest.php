<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Dependencies;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Dependencies\UpdateCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\DependencyUpdater;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme\FakeThemeWithTitle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateCommandTest extends TestCase
{
    private DependencyUpdater&MockObject $dependencyUpdater;
    private ThemePath&MockObject $themePath;
    private ThemeList&MockObject $themeList;
    private ThemeSuggester&MockObject $themeSuggester;
    private UpdateCommand $command;

    protected function setUp(): void
    {
        $this->dependencyUpdater = $this->createMock(DependencyUpdater::class);
        $this->themePath = $this->createMock(ThemePath::class);
        $this->themeList = $this->createMock(ThemeList::class);
        $this->themeSuggester = $this->createMock(ThemeSuggester::class);
        $this->command = new UpdateCommand(
            $this->dependencyUpdater,
            $this->themePath,
            $this->themeList,
            $this->themeSuggester,
        );
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:dependencies:update', $this->command->getName());
        $this->assertSame(['dependencies:update'], $this->command->getAliases());
    }

    public function testUpdatesSingleThemeAndSuggestsRebuild(): void
    {
        $this->themePath->method('getPath')->with('Vendor/theme')->willReturn('/path');
        $this->dependencyUpdater
            ->expects($this->once())
            ->method('updateThemeDependencies')
            ->with('Vendor/theme', '/path', $this->anything(), false, false, false)
            ->willReturn(true);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Updating dependencies for theme: Vendor/theme', $display);
        $this->assertStringContainsString('Updated dependencies for 1 theme(s)', $display);
        $this->assertStringContainsString('mageforge:theme:build Vendor/theme', $display);
    }

    public function testPassesLatestAndDryRunOptionsToService(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->dependencyUpdater
            ->expects($this->once())
            ->method('updateThemeDependencies')
            ->with('Vendor/theme', '/path', $this->anything(), false, true, true)
            ->willReturn(true);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            'themeCodes' => ['Vendor/theme'],
            '--latest' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE: No dependencies will be changed', $display);
        $this->assertStringContainsString('Checked dependencies for 1 theme(s)', $display);
        $this->assertStringNotContainsString('mageforge:theme:build', $display);
    }

    public function testProcessesMultipleThemesWithSectionsPerTheme(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->dependencyUpdater->method('updateThemeDependencies')->willReturn(true);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/one', 'Vendor/two']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Updating dependencies 1 of 2: Vendor/one', $display);
        $this->assertStringContainsString('Updating dependencies 2 of 2: Vendor/two', $display);
        $this->assertStringContainsString('Updated dependencies for 2 theme(s)', $display);
    }

    public function testReturnsFailureWhenAllThemesFail(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->dependencyUpdater->method('updateThemeDependencies')->willReturn(false);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No theme dependencies were updated.', $display);
        $this->assertStringContainsString('Failed to process 1 theme(s): Vendor/theme', $display);
    }

    public function testReportsInvalidThemeWithoutCallingService(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);
        $this->dependencyUpdater->expects($this->never())->method('updateThemeDependencies');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/unknown']]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("Theme 'Vendor/unknown' is not installed", $display);
        $this->assertStringContainsString('Failed to process 1 theme(s): Vendor/unknown', $display);
    }

    public function testListsAvailableThemesWhenNoThemeGivenNonInteractively(): void
    {
        $theme = new FakeThemeWithTitle('Vendor/theme', 'Vendor Theme');
        $this->themeList->method('getAllThemes')->willReturn([$theme]);
        $this->dependencyUpdater->expects($this->never())->method('updateThemeDependencies');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No theme specified. Available themes:', $display);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('mageforge:dependencies:update <theme-code>', $display);
    }

    public function testContinuesWithRemainingThemesWhenOneFails(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->dependencyUpdater
            ->method('updateThemeDependencies')
            ->willReturnOnConsecutiveCalls(false, true);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/broken', 'Vendor/fine']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Updated dependencies for 1 theme(s)', $display);
        $this->assertStringContainsString('Failed to process 1 theme(s): Vendor/broken', $display);
    }
}
