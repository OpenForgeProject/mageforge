<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use OpenForgeProject\MageForge\Console\Command\Theme\WatchCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class WatchCommandTest extends TestCase
{
    /**
     * @var BuilderPool&MockObject
     */
    private $builderPool;
    /**
     * @var ThemeList&MockObject
     */
    private $themeList;
    /**
     * @var ThemePath&MockObject
     */
    private $themePath;
    /**
     * @var ThemeSuggester&MockObject
     */
    private $themeSuggester;
    /**
     * @var WatchCommand
     */
    private WatchCommand $command;

    protected function setUp(): void
    {
        $this->builderPool = $this->createMock(BuilderPool::class);
        $this->themeList = $this->createMock(ThemeList::class);
        $this->themePath = $this->createMock(ThemePath::class);
        $this->themeSuggester = $this->createMock(ThemeSuggester::class);
        $this->command = new WatchCommand(
            $this->builderPool,
            $this->themeList,
            $this->themePath,
            $this->themeSuggester,
        );
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:theme:watch', $this->command->getName());
        $this->assertSame(['frontend:watch'], $this->command->getAliases());
    }

    public function testWatchesThemeViaArgument(): void
    {
        $this->themePath->method('getPath')->with('Vendor/theme')->willReturn('/path/to/theme');
        $builder = $this->createMock(BuilderInterface::class);
        $builder
            ->expects($this->once())
            ->method('watch')
            ->with('Vendor/theme', '/path/to/theme')
            ->willReturn(true);
        $this->builderPool->method('getBuilder')->with('/path/to/theme')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testWatchesThemeViaOption(): void
    {
        $this->themePath->method('getPath')->with('Vendor/theme')->willReturn('/path/to/theme');
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('watch')->willReturn(true);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--theme' => 'Vendor/theme']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testFailsWhenWatcherReportsFailure(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createMock(BuilderInterface::class);
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
}
