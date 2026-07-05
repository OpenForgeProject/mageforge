<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
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
        // Distinguishes an exact "Vendor/" prefix from an off-by-one "Vendor" prefix: this
        // theme starts with "Vendor" but not with "Vendor/" and must stay excluded.
        $themeLookalike = $this->createMock(ThemeInterface::class);
        $themeLookalike->method('getCode')->willReturn('Vendorish/other');

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$themeOne, $themeTwo, $themeOther, $themeLookalike]);

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
        // Distinguishes an exact "Vendor/" prefix from a bare "Vendor" prefix: this theme
        // starts with "Vendor" but not with "Vendor/" and must stay excluded.
        $themeLookalike = $this->createMock(ThemeInterface::class);
        $themeLookalike->method('getCode')->willReturn('Vendorish/other');

        $themeList = $this->createMock(ThemeList::class);
        $themeList->method('getAllThemes')->willReturn([$themeOne, $themeLookalike]);

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

    public function testResolveVendorThemesReindexesAfterRemovingADuplicateFromTheMiddle(): void
    {
        $themeList = $this->createMock(ThemeList::class);
        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);

        // Removing the middle duplicate leaves array_unique()'s original keys as [0, 1, 3];
        // without re-indexing, assertSame() against a freshly-keyed [0, 1, 2] array would fail.
        $result = $this->command->callResolveVendorThemes(
            ['Vendor/a', 'Vendor/b', 'Vendor/a', 'Vendor/c'],
            $themeList,
        );

        $this->assertSame(['Vendor/a', 'Vendor/b', 'Vendor/c'], $result);
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

    public function testHandleInvalidThemeProceedsPastLengthCheckAtExactly255Characters(): void
    {
        $themeSuggester = $this->createMock(ThemeSuggester::class);
        $themeSuggester->expects($this->once())->method('findSimilarThemes')->willReturn([]);
        $output = $this->createMock(OutputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $this->command->setIoForTest($io);

        $this->command->callHandleInvalidThemeWithSuggestions(str_repeat('a', 255), $themeSuggester, $output);
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
        $io->expects($this->once())->method('error')->with("Theme 'Vendor/typo' is not installed.");
        $io->expects($this->never())->method('newLine');
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

    public function testIsInteractiveTerminalIsTrueWhenDecoratedAndTtyAvailable(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDecorated')->willReturn(true);
        $this->command->setRealTtyAvailable(true);

        $this->assertTrue($this->command->callIsInteractiveTerminal($output));
    }

    public function testIsInteractiveTerminalIsFalseWhenDecoratedButNoTtyAvailable(): void
    {
        $output = $this->createMock(OutputInterface::class);
        $output->method('isDecorated')->willReturn(true);
        $this->command->setRealTtyAvailable(false);

        $this->assertFalse($this->command->callIsInteractiveTerminal($output));
    }

    public function testSetPromptEnvironmentAppliesSanitizedTerminalDefaults(): void
    {
        $this->command->callSetPromptEnvironment();

        // Read the raw storage directly: getEnvVar() caches on first read (which
        // setPromptEnvironment() itself triggers to capture the "original" values), so a
        // second getEnvVar() call would not reliably reflect writes made afterwards.
        $storage = $this->readPrivateProperty('secureEnvStorage');
        $this->assertSame('100', $storage['COLUMNS']);
        $this->assertSame('40', $storage['LINES']);
        $this->assertSame('xterm-256color', $storage['TERM']);
    }

    public function testSetPromptEnvironmentCapturesPreviousValuesInOriginalEnv(): void
    {
        $this->writePrivateProperty('secureEnvStorage', ['COLUMNS' => '12', 'LINES' => '34', 'TERM' => 'screen']);

        $this->command->callSetPromptEnvironment();

        $this->assertSame(
            ['COLUMNS' => '12', 'LINES' => '34', 'TERM' => 'screen'],
            $this->readPrivateProperty('originalEnv'),
        );
    }

    public function testResetPromptEnvironmentRemovesPreviouslyUnsetValues(): void
    {
        $this->writePrivateProperty('originalEnv', ['COLUMNS' => null]);
        $this->writePrivateProperty('secureEnvStorage', ['COLUMNS' => '100']);
        $this->writePrivateProperty('cachedEnv', ['COLUMNS' => '100']);

        $this->command->callResetPromptEnvironment();

        $this->assertArrayNotHasKey('COLUMNS', $this->readPrivateProperty('secureEnvStorage'));
        $this->assertNull($this->readPrivateProperty('cachedEnv'));
    }

    public function testResetPromptEnvironmentRestoresPreviouslySetValue(): void
    {
        $this->writePrivateProperty('originalEnv', ['COLUMNS' => '75']);
        $this->writePrivateProperty('secureEnvStorage', ['COLUMNS' => '100']);

        $this->command->callResetPromptEnvironment();

        $this->assertSame('75', $this->readPrivateProperty('secureEnvStorage')['COLUMNS']);
    }

    public function testGetSecureEnvironmentValueShortCircuitsForNamesFailingTheAnchoredPattern(): void
    {
        // "abcTEST" isn't a recognized variable name either way, so the return value alone
        // (null) can't distinguish an anchored vs. unanchored name pattern. Whether the cache
        // gets built at all does: the anchored pattern rejects the name before ever touching
        // the cache, an unanchored one (matching the trailing "TEST") would build it.
        $this->callPrivate('getSecureEnvironmentValue', ['abcTEST']);

        $this->assertNull($this->readPrivateProperty('cachedEnv'));
    }

    public function testSetEnvVarRejectsNamesFailingTheAnchoredPattern(): void
    {
        $this->callPrivate('setEnvVar', ['abcTEST', 'value']);

        $this->assertSame([], $this->readPrivateProperty('secureEnvStorage'));
    }

    public function testGetSecureEnvironmentValueRejectsNamesFailingTheDollarAnchor(): void
    {
        // "TESTabc" has a valid prefix from position 0, but a version of the pattern missing
        // the trailing "$" anchor would stop matching as soon as it found that valid prefix,
        // ignoring the invalid "abc" suffix.
        $this->callPrivate('getSecureEnvironmentValue', ['TESTabc']);

        $this->assertNull($this->readPrivateProperty('cachedEnv'));
    }

    public function testSetEnvVarRejectsNamesFailingTheDollarAnchor(): void
    {
        $this->callPrivate('setEnvVar', ['TESTabc', 'value']);

        $this->assertSame([], $this->readPrivateProperty('secureEnvStorage'));
    }

    public function testSetEnvVarDropsValuesThatFailSanitization(): void
    {
        $this->callPrivate('setEnvVar', ['COLUMNS', 'not-a-number']);

        $this->assertSame([], $this->readPrivateProperty('secureEnvStorage'));
    }

    public function testSetEnvVarStoresSanitizedValue(): void
    {
        $this->callPrivate('setEnvVar', ['TERM', 'xterm!256@color']);

        $this->assertSame('xterm256color', $this->readPrivateProperty('secureEnvStorage')['TERM']);
    }

    public function testGetEnvVarReturnsSanitizedStoredValue(): void
    {
        $this->writePrivateProperty('secureEnvStorage', ['JENKINS_URL' => 'build-42']);

        $this->assertSame('build-42', $this->callPrivate('getEnvVar', ['JENKINS_URL']));
    }

    public function testGetEnvVarReturnsNullWhenNothingStored(): void
    {
        $this->assertNull($this->callPrivate('getEnvVar', ['TEAMCITY_VERSION']));
    }

    #[DataProvider('numericValueProvider')]
    public function testSanitizeNumericValueEnforcesRange(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('sanitizeNumericValue', [$input]));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function numericValueProvider(): array
    {
        return [
            'within range' => ['150', '150'],
            'min_range boundary is accepted' => ['1', '1'],
            'zero is below min_range' => ['0', null],
            'max_range boundary is accepted' => ['9999', '9999'],
            'above max_range' => ['10000', null],
            'not numeric' => ['abc', null],
        ];
    }

    #[DataProvider('termValueProvider')]
    public function testSanitizeTermValueStripsDisallowedCharacters(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('sanitizeTermValue', [$input]));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function termValueProvider(): array
    {
        return [
            'allowed characters kept' => ['xterm-256color', 'xterm-256color'],
            'disallowed characters stripped' => ['xterm!256@color', 'xterm256color'],
            'empty after sanitizing is rejected' => ['!!!', null],
            'too long is rejected' => [str_repeat('a', 51), null],
            'max length is accepted' => [str_repeat('a', 50), str_repeat('a', 50)],
        ];
    }

    #[DataProvider('booleanValueProvider')]
    public function testSanitizeBooleanValueNormalizesRecognizedValues(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('sanitizeBooleanValue', [$input]));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function booleanValueProvider(): array
    {
        return [
            'digit one' => ['1', '1'],
            'uppercase true' => ['TRUE', 'true'],
            'padded yes' => [' yes ', 'yes'],
            'on' => ['on', 'on'],
            'unrecognized value' => ['maybe', null],
            'no is not recognized' => ['no', null],
        ];
    }

    #[DataProvider('alphanumericValueProvider')]
    public function testSanitizeAlphanumericValueStripsDisallowedCharacters(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('sanitizeAlphanumericValue', [$input]));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function alphanumericValueProvider(): array
    {
        return [
            'word chars dash and dot kept' => ['v1.2-3_build', 'v1.2-3_build'],
            'spaces and symbols stripped' => ['bad chars!!', 'badchars'],
            'empty after sanitizing is rejected' => ['!!!', null],
            'max length is accepted' => [str_repeat('a', 255), str_repeat('a', 255)],
            'too long is rejected' => [str_repeat('a', 256), null],
        ];
    }

    #[DataProvider('sanitizeDispatchProvider')]
    public function testSanitizeEnvironmentValueDispatchesByName(string $name, string $value, ?string $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('sanitizeEnvironmentValue', [$name, $value]));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function sanitizeDispatchProvider(): array
    {
        return [
            // Values below are chosen so each named arm produces a RESULT DIFFERENT from what
            // the (structurally identical) "default" arm would produce, so that removing a
            // match arm changes the observed outcome instead of accidentally still matching.
            'COLUMNS uses numeric sanitizer' => ['COLUMNS', 'not-a-number', null],
            'LINES uses numeric sanitizer' => ['LINES', 'not-a-number', null],
            'TERM uses term sanitizer' => ['TERM', 'xterm_1.0', 'xterm10'],
            'CI uses boolean sanitizer' => ['CI', 'enabled', null],
            'GITHUB_ACTIONS uses boolean sanitizer' => ['GITHUB_ACTIONS', 'enabled', null],
            'GITLAB_CI uses boolean sanitizer' => ['GITLAB_CI', 'enabled', null],
            'JENKINS_URL uses alphanumeric sanitizer' => ['JENKINS_URL', 'build-42', 'build-42'],
            'TEAMCITY_VERSION uses alphanumeric sanitizer' => ['TEAMCITY_VERSION', '2023.1', '2023.1'],
            'unknown var uses alphanumeric sanitizer by default' => ['SOME_OTHER_VAR', 'value 1', 'value1'],
        ];
    }

    /**
     * Invoke a private method on the command under test via Reflection.
     *
     * @param array<mixed> $args
     */
    private function callPrivate(string $method, array $args = []): mixed
    {
        return (new \ReflectionMethod($this->command, $method))->invokeArgs($this->command, $args);
    }

    /**
     * Read a private property declared on AbstractCommand.
     *
     * Reflecting via the parent class name (rather than the ConcreteTestCommand instance) is
     * required: PHP's Reflection API cannot resolve a property by name through a subclass when
     * it is declared `private` on the parent.
     */
    private function readPrivateProperty(string $property): mixed
    {
        return (new \ReflectionProperty(AbstractCommand::class, $property))->getValue($this->command);
    }

    private function writePrivateProperty(string $property, mixed $value): void
    {
        (new \ReflectionProperty(AbstractCommand::class, $property))->setValue($this->command, $value);
    }
}
