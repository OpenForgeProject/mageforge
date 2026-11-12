<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use OpenForgeProject\MageForge\Console\Command\Theme\WatchCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class WatchCommandTest extends TestCase
{
    /**
     * @var BuilderPool&Stub
     */
    private $builderPool;
    /**
     * @var ThemeList&Stub
     */
    private $themeList;
    /**
     * @var ThemePath&Stub
     */
    private $themePath;
    /**
     * @var ThemeSuggester&Stub
     */
    private $themeSuggester;
    /**
     * @var WatchCommand
     */
    private WatchCommand $command;

    protected function setUp(): void
    {
        $this->builderPool = $this->createStub(BuilderPool::class);
        $this->themeList = $this->createStub(ThemeList::class);
        $this->themePath = $this->createStub(ThemePath::class);
        $this->themeSuggester = $this->createStub(ThemeSuggester::class);
        $this->command = $this->createCommand();
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:theme:watch', $this->command->getName());
        $this->assertSame(['frontend:watch'], $this->command->getAliases());
    }

    public function testWatchesThemeViaArgument(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $themePath->expects($this->once())->method('getPath')->with('Vendor/theme')->willReturn('/path/to/theme');
        $builder = $this->createMock(BuilderInterface::class);
        $builder
            ->expects($this->once())
            ->method('watch')
            ->with('Vendor/theme', '/path/to/theme')
            ->willReturn(true);
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->expects($this->once())->method('getBuilder')->with('/path/to/theme')->willReturn($builder);
        $command = $this->createCommand(builderPool: $builderPool, themePath: $themePath);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testWatchesThemeViaOption(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $themePath->expects($this->once())->method('getPath')->with('Vendor/theme')->willReturn('/path/to/theme');
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('watch')->willReturn(true);
        $this->builderPool->method('getBuilder')->willReturn($builder);
        $command = $this->createCommand(themePath: $themePath);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--theme' => 'Vendor/theme']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testFailsWhenWatcherReportsFailure(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('watch')->willReturn(false);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testFailsWhenNoBuilderIsFound(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $this->builderPool->method('getBuilder')->willReturn(null);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No suitable builder found for theme Vendor/theme.', $tester->getDisplay());
    }

    public function testFailsForUnknownThemeWithoutSuggestions(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/unknown']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $normalizedDisplay = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString(
            "Theme 'Vendor/unknown' is not installed and no similar themes were found.",
            $normalizedDisplay,
        );
    }

    public function testListsSuggestionsForUnknownThemeInNonInteractiveMode(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn(['Vendor/theme', 'Vendor/other']);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/them']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("Theme 'Vendor/them' is not installed.", $display);
        $this->assertStringContainsString('Did you mean one of these?', $display);
        $this->assertStringContainsString('- Vendor/theme', $display);
        $this->assertStringContainsString('- Vendor/other', $display);
    }

    private function createCommand(
        ?BuilderPool $builderPool = null,
        ?ThemeList $themeList = null,
        ?ThemePath $themePath = null,
        ?ThemeSuggester $themeSuggester = null,
    ): WatchCommand {
        return new WatchCommand(
            $builderPool ?? $this->builderPool,
            $themeList ?? $this->themeList,
            $themePath ?? $this->themePath,
            $themeSuggester ?? $this->themeSuggester,
        );
    }
}
