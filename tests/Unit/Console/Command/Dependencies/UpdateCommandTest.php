<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Dependencies;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Dependencies\UpdateCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\DependencyUpdateResult;
use OpenForgeProject\MageForge\Service\DependencyUpdater;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme\FakeThemeWithTitle;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateCommandTest extends TestCase
{
    /**
     * @var DependencyUpdater&Stub
     */
    private $dependencyUpdater;
    /**
     * @var ThemePath&Stub
     */
    private $themePath;
    /**
     * @var ThemeList&Stub
     */
    private $themeList;
    /**
     * @var ThemeSuggester&Stub
     */
    private $themeSuggester;
    /**
     * @var UpdateCommand
     */
    private UpdateCommand $command;

    protected function setUp(): void
    {
        $this->dependencyUpdater = $this->createStub(DependencyUpdater::class);
        $this->themePath = $this->createStub(ThemePath::class);
        $this->themeList = $this->createStub(ThemeList::class);
        $this->themeSuggester = $this->createStub(ThemeSuggester::class);
        $this->command = $this->createCommand();
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:dependencies:update', $this->command->getName());
        $this->assertSame(['dependencies:update'], $this->command->getAliases());
    }

    public function testUpdatesSingleThemeAndSuggestsRebuild(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $themePath->expects($this->atLeastOnce())->method('getPath')->with('Vendor/theme')->willReturn('/path');
        $dependencyUpdater = $this->createMock(DependencyUpdater::class);
        $dependencyUpdater
            ->expects($this->once())
            ->method('updateThemeDependencies')
            ->with('Vendor/theme', '/path', $this->anything(), false, false, false)
            ->willReturn(DependencyUpdateResult::Updated);

        $command = $this->createCommand(dependencyUpdater: $dependencyUpdater, themePath: $themePath);
        $tester = new CommandTester($command);
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
        $dependencyUpdater = $this->createMock(DependencyUpdater::class);
        $dependencyUpdater
            ->expects($this->once())
            ->method('updateThemeDependencies')
            ->with('Vendor/theme', '/path', $this->anything(), false, true, true)
            ->willReturn(DependencyUpdateResult::Updated);

        $tester = new CommandTester($this->createCommand(dependencyUpdater: $dependencyUpdater));
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
        $this->dependencyUpdater->method('updateThemeDependencies')->willReturn(DependencyUpdateResult::Updated);

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
        $this->dependencyUpdater->method('updateThemeDependencies')->willReturn(DependencyUpdateResult::Failed);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No theme dependencies were updated.', $display);
        $this->assertStringContainsString('Failed to process 1 theme(s): Vendor/theme', $display);
    }

    public function testReturnsSuccessWhenAllThemesAreSkipped(): void
    {
        $this->themePath->method('getPath')->willReturn('/path');
        $this->dependencyUpdater->method('updateThemeDependencies')->willReturn(DependencyUpdateResult::Skipped);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Skipped 1 theme(s): Vendor/theme', $display);
        $this->assertStringNotContainsString('Failed to process', $display);
    }

    public function testReportsInvalidThemeWithoutCallingService(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);
        $dependencyUpdater = $this->createMock(DependencyUpdater::class);
        $dependencyUpdater->expects($this->never())->method('updateThemeDependencies');

        $tester = new CommandTester($this->createCommand(dependencyUpdater: $dependencyUpdater));
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
        $dependencyUpdater = $this->createMock(DependencyUpdater::class);
        $dependencyUpdater->expects($this->never())->method('updateThemeDependencies');

        $tester = new CommandTester($this->createCommand(dependencyUpdater: $dependencyUpdater));
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
            ->willReturnOnConsecutiveCalls(DependencyUpdateResult::Failed, DependencyUpdateResult::Updated);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/broken', 'Vendor/fine']]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Updated dependencies for 1 theme(s)', $display);
        $this->assertStringContainsString('Failed to process 1 theme(s): Vendor/broken', $display);
    }

    private function createCommand(
        ?DependencyUpdater $dependencyUpdater = null,
        ?ThemePath $themePath = null,
        ?ThemeList $themeList = null,
        ?ThemeSuggester $themeSuggester = null,
    ): UpdateCommand {
        return new UpdateCommand(
            $dependencyUpdater ?? $this->dependencyUpdater,
            $themePath ?? $this->themePath,
            $themeList ?? $this->themeList,
            $themeSuggester ?? $this->themeSuggester,
        );
    }
}
