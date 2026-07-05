<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class AbstractCommandTest extends TestCase
{
    private ConcreteTestCommand $command;

    protected function setUp(): void
    {
        $this->command = new ConcreteTestCommand();
    }

    public function testGetCommandNameBuildsPrefixedName(): void
    {
        $this->assertSame('mageforge:theme:build', $this->command->callGetCommandName('theme', 'build'));
    }

    public function testExecuteReturnsExecuteCommandResult(): void
    {
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
    }

    public function testExecuteReturnsFailureCodeFromExecuteCommand(): void
    {
        $this->command->setExecuteCommandReturn(Cli::RETURN_FAILURE);
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    public function testExecuteCatchesExceptionsAndReturnsFailure(): void
    {
        $this->command->setThrowOnExecute(new \RuntimeException('boom'));
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString('boom', $tester->getDisplay());
    }

    #[DataProvider('verbosityProvider')]
    public function testVerbosityHelpersReflectOutputVerbosity(
        int $verbosity,
        bool $expectedVerbose,
        bool $expectedVeryVerbose,
        bool $expectedDebug,
    ): void {
        $output = $this->createMock(OutputInterface::class);
        $output->method('getVerbosity')->willReturn($verbosity);

        $this->assertSame($expectedVerbose, $this->command->callIsVerbose($output));
        $this->assertSame($expectedVeryVerbose, $this->command->callIsVeryVerbose($output));
        $this->assertSame($expectedDebug, $this->command->callIsDebug($output));
    }

    /**
     * @return array<string, array{0: int, 1: bool, 2: bool, 3: bool}>
     */
    public static function verbosityProvider(): array
    {
        return [
            'normal' => [OutputInterface::VERBOSITY_NORMAL, false, false, false],
            'verbose' => [OutputInterface::VERBOSITY_VERBOSE, true, false, false],
            'very verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, true, true, false],
            'debug' => [OutputInterface::VERBOSITY_DEBUG, true, true, true],
        ];
    }

    public function testResolveVendorThemesReturnsExplicitCodesUnchanged(): void
    {
        $themeList = $this->createMock(ThemeList::class);
        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);
        $io->expects($this->never())->method('warning');

        $result = $this->command->callResolveVendorThemes(['Vendor/theme'], $themeList);

        $this->assertSame(['Vendor/theme'], $result);
    }

    public function testResolveVendorThemesExpandsWildcard(): void
    {
        $themeOne = $this->createMock(ThemeInterface::class);
        $themeOne->method('getCode')->willReturn('Vendor/one');
        $themeTwo = $this->createMock(ThemeInterface::class);
        $themeTwo->method('getCode')->willReturn('Vendor/two');
        $themeOther = $this->createMock(ThemeInterface::class);
        $themeOther->method('getCode')->willReturn('Other/three');

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$themeOne, $themeTwo, $themeOther]);

        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);
        $io->expects($this->once())->method('note');

        $result = $this->command->callResolveVendorThemes(['Vendor/*'], $themeList);

        $this->assertSame(['Vendor/one', 'Vendor/two'], $result);
    }

    public function testResolveVendorThemesExpandsVendorOnlyName(): void
    {
        $themeOne = $this->createMock(ThemeInterface::class);
        $themeOne->method('getCode')->willReturn('Vendor/one');

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$themeOne]);

        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);

        $result = $this->command->callResolveVendorThemes(['Vendor'], $themeList);

        $this->assertSame(['Vendor/one'], $result);
    }

    public function testResolveVendorThemesWarnsAndKeepsCodeWhenVendorNotFound(): void
    {
        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([]);

        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);
        $io->expects($this->once())->method('warning');

        $result = $this->command->callResolveVendorThemes(['Unknown'], $themeList);

        $this->assertSame(['Unknown'], $result);
    }

    public function testResolveVendorThemesDeduplicatesResults(): void
    {
        $themeList = $this->createMock(ThemeList::class);
        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);

        $result = $this->command->callResolveVendorThemes(['Vendor/theme', 'Vendor/theme'], $themeList);

        $this->assertSame(['Vendor/theme'], $result);
    }

    public function testHandleInvalidThemeReturnsNullForTooLongThemeName(): void
    {
        $themeSuggester = $this->createMock(ThemeSuggester::class);
        $themeSuggester->expects($this->never())->method('findSimilarThemes');
        $output = $this->createMock(OutputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');
        $this->command->setIoForTest($io);

        $result = $this->command->callHandleInvalidThemeWithSuggestions(
            str_repeat('a', 256),
            $themeSuggester,
            $output,
        );

        $this->assertNull($result);
    }

    public function testHandleInvalidThemeReturnsNullWhenNoSuggestionsFound(): void
    {
        $themeSuggester = $this->createMock(ThemeSuggester::class);
        $themeSuggester->method('findSimilarThemes')->willReturn([]);
        $output = $this->createMock(OutputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('error');
        $this->command->setIoForTest($io);

        $result = $this->command->callHandleInvalidThemeWithSuggestions('Vendor/typo', $themeSuggester, $output);

        $this->assertNull($result);
    }

    public function testHandleInvalidThemeDisplaysSuggestionsInNonInteractiveMode(): void
    {
        $themeSuggester = $this->createMock(ThemeSuggester::class);
        $themeSuggester->method('findSimilarThemes')->willReturn(['Vendor/similar']);
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDecorated')->willReturn(false);
        $io = $this->createMock(SymfonyStyle::class);
        $writtenLines = [];
        $io->method('writeln')->willReturnCallback(function ($line) use (&$writtenLines): void {
            $writtenLines[] = $line;
        });
        $this->command->setIoForTest($io);

        $result = $this->command->callHandleInvalidThemeWithSuggestions('Vendor/typo', $themeSuggester, $output);

        $this->assertNull($result);
        $this->assertSame(['  - Vendor/similar'], array_slice($writtenLines, 1));
    }

    public function testIsInteractiveTerminalIsFalseWhenOutputNotDecorated(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDecorated')->willReturn(false);

        $this->assertFalse($this->command->callIsInteractiveTerminal($output));
    }

    public function testSetAndResetPromptEnvironmentDoNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        $this->command->callSetPromptEnvironment();
        $this->command->callResetPromptEnvironment();
    }
}
