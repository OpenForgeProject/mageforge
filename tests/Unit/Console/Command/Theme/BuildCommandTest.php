<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use Laravel\Prompts\Prompt;
use OpenForgeProject\MageForge\Console\Command\Theme\BuildCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class BuildCommandTest extends TestCase
{
    /**
     * @var ThemePath&Stub
     */
    private $themePath;
    /**
     * @var ThemeList&Stub
     */
    private $themeList;
    /**
     * @var BuilderPool&Stub
     */
    private $builderPool;
    /**
     * @var ThemeSuggester&Stub
     */
    private $themeSuggester;
    /**
     * @var BuildCommand
     */
    private BuildCommand $command;

    protected function setUp(): void
    {
        // The non-verbose build path renders a Laravel Prompts spinner, which
        // writes ANSI animation frames directly to STDOUT past the CommandTester
        // and garbles the PHPUnit progress output. Send it to a buffer instead.
        Prompt::setOutput(new BufferedOutput());

        $this->themePath = $this->createStub(ThemePath::class);
        $this->themeList = $this->createStub(ThemeList::class);
        $this->builderPool = $this->createStub(BuilderPool::class);
        $this->themeSuggester = $this->createStub(ThemeSuggester::class);
        $this->command = $this->createCommand();
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:theme:build', $this->command->getName());
        $this->assertSame(['frontend:build'], $this->command->getAliases());
    }

    public function testBuildsThemeVerboseAndReportsSummary(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $themePath
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->with('Vendor/theme')
            ->willReturn('/path/to/theme');
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('tailwind');
        $builder
            ->expects($this->once())
            ->method('build')
            ->with('Vendor/theme', '/path/to/theme')
            ->willReturn(true);
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->expects($this->once())->method('getBuilder')->with('/path/to/theme')->willReturn($builder);
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(
            ['themeCodes' => ['Vendor/theme']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Building 1 theme(s)', $display);
        $this->assertStringContainsString('Building theme Vendor/theme using tailwind builder', $display);
        $this->assertStringContainsString('Successfully built 1 theme(s)', $display);
        $this->assertStringContainsString('Summary:', $display);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('Built successfully using tailwind builder', $display);
        $this->assertMatchesRegularExpression(
            '/Build process completed in \d{1,2}\.\d{2} seconds/',
            (string) preg_replace('/\s+/', ' ', $display),
            'Duration must be a small positive number of seconds',
        );
    }

    public function testBuildsThemeNonVerboseWithProgressLine(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createNamedBuilder('hyva');
        $builder->method('build')->willReturn(true);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Building Vendor/theme (1 of 1) ... done', $display);
        $this->assertStringContainsString('Successfully built 1 theme(s)', $display);
    }

    public function testReportsFailureLineWhenBuildFails(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createNamedBuilder('hyva');
        $builder->method('build')->willReturn(false);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/theme']]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Building Vendor/theme (1 of 1) ... failed', $display);
        $this->assertStringContainsString('no themes were built successfully', $display);
    }

    public function testReportsErrorWhenNoBuilderFound(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $this->builderPool->method('getBuilder')->willReturn(null);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(
            ['themeCodes' => ['Vendor/theme']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(Command::FAILURE, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('No suitable builder found for theme Vendor/theme.', $display);
        $this->assertStringContainsString('no themes were built successfully', $display);
    }

    public function testSkipsUnknownThemeWithoutSuggestions(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->expects($this->never())->method('getBuilder');
        $command = $this->createCommand(builderPool: $builderPool);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(
            ['themeCodes' => ['Vendor/unknown']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(Command::FAILURE, $exitCode);
        $normalized = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString(
            "Theme 'Vendor/unknown' is not installed and no similar themes were found.",
            $normalized,
        );
    }

    public function testNoArgumentsInNonInteractiveModeListsThemes(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeThemeWithTitle('Vendor/theme', 'Vendor Theme'),
        ]);
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->expects($this->never())->method('getBuilder');
        $command = $this->createCommand(builderPool: $builderPool);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Theme Code', $display);
        $this->assertStringContainsString('Title', $display);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('Vendor Theme', $display);
        $this->assertStringContainsString('Usage: bin/magento mageforge:theme:build <theme-code>', $display);
        $this->assertStringNotContainsString('No themes selected.', $display);
        $this->assertStringNotContainsString('Interactive mode failed', $display);
    }

    public function testResolvesVendorWildcardToAllVendorThemes(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeThemeWithTitle('Vendor/one', 'One'),
            new FakeThemeWithTitle('Vendor/two', 'Two'),
            new FakeThemeWithTitle('Other/theme', 'Other'),
        ]);
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('grunt');
        $builder->expects($this->exactly(2))->method('build')->willReturn(true);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(
            ['themeCodes' => ['Vendor']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("Resolved vendor 'Vendor' to 2 theme(s): Vendor/one, Vendor/two", $display);
        $this->assertStringContainsString('Successfully built 2 theme(s)', $display);
    }

    public function testWildcardWithoutMatchesWarnsAndExits(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([
            new FakeThemeWithTitle('Other/theme', 'Other'),
        ]);
        $builderPool = $this->createMock(BuilderPool::class);
        $builderPool->expects($this->never())->method('getBuilder');
        $command = $this->createCommand(builderPool: $builderPool);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/*']]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString("No themes found for vendor/prefix 'Vendor/'", $display);
        $this->assertStringNotContainsString('Usage:', $display, 'Command must exit before listing themes');
    }

    private function createCommand(
        ?ThemePath $themePath = null,
        ?ThemeList $themeList = null,
        ?BuilderPool $builderPool = null,
        ?ThemeSuggester $themeSuggester = null,
    ): BuildCommand {
        return new BuildCommand(
            $themePath ?? $this->themePath,
            $themeList ?? $this->themeList,
            $builderPool ?? $this->builderPool,
            $themeSuggester ?? $this->themeSuggester,
        );
    }

    private function createNamedBuilder(string $name): BuilderInterface&Stub
    {
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('getName')->willReturn($name);

        return $builder;
    }

    public function testVerboseBuildFailureReportsExactError(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createNamedBuilder('hyva');
        $builder->method('build')->willReturn(false);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(
            ['themeCodes' => ['Vendor/theme']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Failed to build theme Vendor/theme.', $tester->getDisplay());
    }

    public function testReturnsFailureWhenOnlyOneOfSeveralThemesFails(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createNamedBuilder('hyva');
        $builder->method('build')->willReturnOnConsecutiveCalls(true, false);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCodes' => ['Vendor/good', 'Vendor/broken']]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Building Vendor/good (1 of 2) ... done', $display);
        $this->assertStringContainsString('Building Vendor/broken (2 of 2) ... failed', $display);
    }

    public function testSummaryColorsBuilderNameInDecoratedOutput(): void
    {
        $this->themePath->method('getPath')->willReturn('/path/to/theme');
        $builder = $this->createNamedBuilder('tailwind');
        $builder->method('build')->willReturn(true);
        $this->builderPool->method('getBuilder')->willReturn($builder);

        $tester = new CommandTester($this->command);
        $tester->execute(
            ['themeCodes' => ['Vendor/theme']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => true],
        );

        $this->assertStringContainsString(
            "using \033[35mtailwind\033[39m builder",
            $tester->getDisplay(),
            'Builder name must be rendered in magenta in the summary',
        );
    }
}
