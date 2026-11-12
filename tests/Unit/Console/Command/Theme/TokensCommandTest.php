<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Console\Command\Theme\TokensCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class TokensCommandTest extends TestCase
{
    /**
     * @var ThemeList&Stub
     */
    private $themeList;
    /**
     * @var ThemePath&Stub
     */
    private $themePath;
    /**
     * @var BuilderPool&Stub
     */
    private $builderPool;
    /**
     * @var File&Stub
     */
    private $fileDriver;
    /**
     * @var Shell&Stub
     */
    private $shell;
    /**
     * @var ThemeSuggester&Stub
     */
    private $themeSuggester;
    /**
     * @var TokensCommand
     */
    private TokensCommand $command;

    protected function setUp(): void
    {
        $this->themeList = $this->createStub(ThemeList::class);
        $this->themePath = $this->createStub(ThemePath::class);
        $this->builderPool = $this->createStub(BuilderPool::class);
        $this->fileDriver = $this->createStub(File::class);
        $this->shell = $this->createStub(Shell::class);
        $this->themeSuggester = $this->createStub(ThemeSuggester::class);
        $this->command = $this->createCommand();
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:hyva:tokens', $this->command->getName());
        $this->assertSame(['hyva:tokens'], $this->command->getAliases());
    }

    public function testGeneratesTokensForLocalHyvaTheme(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $builderPool = $this->createMock(BuilderPool::class);
        $this->givenHyvaTheme($themePath, $builderPool, '/app/design/frontend/Vendor/theme');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npx hyva-tokens', ['/app/design/frontend/Vendor/theme/web/tailwind']);
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool, shell: $shell);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Hyvä design tokens generated successfully.', $display);
        $this->assertStringContainsString(
            'Generated file: /app/design/frontend/Vendor/theme/web/tailwind/generated/hyva-tokens.css',
            $display,
        );
    }

    public function testCopiesTokensToVarGeneratedForVendorTheme(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $builderPool = $this->createMock(BuilderPool::class);
        $this->givenHyvaTheme($themePath, $builderPool, '/app/vendor/hyva-themes/theme');
        $fileDriver = $this->createMock(File::class);
        $fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => !str_contains($path, 'var/generated'));
        $fileDriver->method('isExists')->willReturn(true);
        $fileDriver
            ->expects($this->once())
            ->method('createDirectory')
            ->with($this->stringContains('var/generated/hyva-token/Vendor/theme'), 0o755);
        $fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with(
                '/app/vendor/hyva-themes/theme/web/tailwind/generated/hyva-tokens.css',
                $this->stringContains('var/generated/hyva-token/Vendor/theme/hyva-tokens.css'),
            );
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool, fileDriver: $fileDriver);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $normalized = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Hyvä design tokens generated successfully.', $normalized);
        $this->assertStringContainsString('This is a vendor theme.', $normalized);
        $this->assertStringContainsString(
            'This is a vendor theme. Tokens have been saved to var/generated/hyva-token/ instead.',
            $normalized,
        );
        $this->assertStringContainsString('Generated file: ', $normalized);
        $this->assertStringContainsString('var/generated/hyva-token/Vendor/theme/hyva-tokens.css', $normalized);
    }

    public function testVerboseModeAnnouncesWorkingDirectory(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $builderPool = $this->createMock(BuilderPool::class);
        $this->givenHyvaTheme($themePath, $builderPool, '/theme');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(
            ['themeCode' => 'Vendor/theme'],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Generating Hyvä design tokens for theme: Vendor/theme', $display);
        $this->assertStringContainsString('Working directory: /theme/web/tailwind', $display);
        $this->assertStringContainsString('Running npx hyva-tokens...', $display);
    }

    public function testFailsWhenTokenGenerationFails(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $builderPool = $this->createMock(BuilderPool::class);
        $this->givenHyvaTheme($themePath, $builderPool, '/theme');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npx not found'));
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Failed to generate Hyvä design tokens: npx not found',
            (string) preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    public function testFailsForNonHyvaTheme(): void
    {
        $this->themePath->method('getPath')->willReturn('/theme');
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('getName')->willReturn('MagentoStandard');
        $this->builderPool->method('getBuilder')->willReturn($builder);
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $command = $this->createCommand(shell: $shell);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $normalized = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Theme Vendor/theme is not a Hyvä theme.', $normalized);
        $this->assertStringNotContainsString('Tailwind directory', $normalized, 'Command must stop at theme check');
    }

    public function testFailsWhenNoBuilderIsFound(): void
    {
        $this->themePath->method('getPath')->willReturn('/theme');
        $this->builderPool->method('getBuilder')->willReturn(null);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    public function testFailsWhenTailwindDirectoryIsMissing(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $builderPool = $this->createMock(BuilderPool::class);
        $this->givenHyvaTheme($themePath, $builderPool, '/theme');
        $this->fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => str_ends_with($path, 'node_modules'));
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool, shell: $shell);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Tailwind directory not found in: /theme/web/tailwind',
            (string) preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    public function testWarnsWhenNodeModulesAreMissing(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $builderPool = $this->createMock(BuilderPool::class);
        $this->givenHyvaTheme($themePath, $builderPool, '/theme');
        $this->fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => !str_ends_with($path, 'node_modules'));
        $shell = $this->createMock(Shell::class);
        $shell->expects($this->never())->method('execute');
        $command = $this->createCommand(themePath: $themePath, builderPool: $builderPool, shell: $shell);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Node modules not found. Please run: bin/magento mageforge:theme:build Vendor/theme',
            (string) preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    public function testFailsForUnknownThemeWithoutSuggestions(): void
    {
        $this->themePath->method('getPath')->willReturn(null);
        $this->themeSuggester->method('findSimilarThemes')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/unknown']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
    }

    private function createCommand(
        ?ThemeList $themeList = null,
        ?ThemePath $themePath = null,
        ?BuilderPool $builderPool = null,
        ?File $fileDriver = null,
        ?Shell $shell = null,
        ?ThemeSuggester $themeSuggester = null,
    ): TokensCommand {
        return new TokensCommand(
            $themeList ?? $this->themeList,
            $themePath ?? $this->themePath,
            $builderPool ?? $this->builderPool,
            $fileDriver ?? $this->fileDriver,
            $shell ?? $this->shell,
            $themeSuggester ?? $this->themeSuggester,
        );
    }

    private function givenHyvaTheme(
        ThemePath&MockObject $themePath,
        BuilderPool&MockObject $builderPool,
        string $path,
    ): void {
        $themePath->expects($this->once())->method('getPath')->with('Vendor/theme')->willReturn($path);
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('getName')->willReturn('HyvaThemes');
        $builderPool->expects($this->once())->method('getBuilder')->with($path)->willReturn($builder);
    }

    public function testNormalizesTrailingSlashInThemePath(): void
    {
        $themePath = $this->createMock(ThemePath::class);
        $themePath->expects($this->once())->method('getPath')->with('Vendor/theme')->willReturn('/theme/');
        $builder = $this->createStub(BuilderInterface::class);
        $builder->method('getName')->willReturn('HyvaThemes');
        $this->builderPool->method('getBuilder')->willReturn($builder);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $shell = $this->createMock(Shell::class);
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npx hyva-tokens', ['/theme/web/tailwind']);
        $command = $this->createCommand(themePath: $themePath, shell: $shell);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
    }
}
