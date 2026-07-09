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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class TokensCommandTest extends TestCase
{
    /**
     * @var ThemeList&MockObject
     */
    private $themeList;
    /**
     * @var ThemePath&MockObject
     */
    private $themePath;
    /**
     * @var BuilderPool&MockObject
     */
    private $builderPool;
    /**
     * @var File&MockObject
     */
    private $fileDriver;
    /**
     * @var Shell&MockObject
     */
    private $shell;
    /**
     * @var ThemeSuggester&MockObject
     */
    private $themeSuggester;
    /**
     * @var TokensCommand
     */
    private TokensCommand $command;

    protected function setUp(): void
    {
        $this->themeList = $this->createMock(ThemeList::class);
        $this->themePath = $this->createMock(ThemePath::class);
        $this->builderPool = $this->createMock(BuilderPool::class);
        $this->fileDriver = $this->createMock(File::class);
        $this->shell = $this->createMock(Shell::class);
        $this->themeSuggester = $this->createMock(ThemeSuggester::class);
        $this->command = new TokensCommand(
            $this->themeList,
            $this->themePath,
            $this->builderPool,
            $this->fileDriver,
            $this->shell,
            $this->themeSuggester,
        );
    }

    public function testCommandNameAndAlias(): void
    {
        $this->assertSame('mageforge:hyva:tokens', $this->command->getName());
        $this->assertSame(['hyva:tokens'], $this->command->getAliases());
    }

    public function testGeneratesTokensForLocalHyvaTheme(): void
    {
        $this->givenHyvaTheme('/app/design/frontend/Vendor/theme');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npx hyva-tokens', ['/app/design/frontend/Vendor/theme/web/tailwind']);

        $tester = new CommandTester($this->command);
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
        $this->givenHyvaTheme('/app/vendor/hyva-themes/theme');
        $this->fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => !str_contains($path, 'var/generated'));
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver
            ->expects($this->once())
            ->method('createDirectory')
            ->with($this->stringContains('var/generated/hyva-token/Vendor/theme'), 0o755);
        $this->fileDriver
            ->expects($this->once())
            ->method('copy')
            ->with(
                '/app/vendor/hyva-themes/theme/web/tailwind/generated/hyva-tokens.css',
                $this->stringContains('var/generated/hyva-token/Vendor/theme/hyva-tokens.css'),
            );

        $tester = new CommandTester($this->command);
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
        $this->givenHyvaTheme('/theme');
        $this->fileDriver->method('isDirectory')->willReturn(true);

        $tester = new CommandTester($this->command);
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
        $this->givenHyvaTheme('/theme');
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell->method('execute')->willThrowException(new \RuntimeException('npx not found'));

        $tester = new CommandTester($this->command);
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
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('MagentoStandard');
        $this->builderPool->method('getBuilder')->willReturn($builder);
        $this->shell->expects($this->never())->method('execute');

        $tester = new CommandTester($this->command);
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
        $this->givenHyvaTheme('/theme');
        $this->fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => str_ends_with($path, 'node_modules'));
        $this->shell->expects($this->never())->method('execute');

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Tailwind directory not found in: /theme/web/tailwind',
            (string) preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    public function testWarnsWhenNodeModulesAreMissing(): void
    {
        $this->givenHyvaTheme('/theme');
        $this->fileDriver
            ->method('isDirectory')
            ->willReturnCallback(static fn(string $path): bool => !str_ends_with($path, 'node_modules'));
        $this->shell->expects($this->never())->method('execute');

        $tester = new CommandTester($this->command);
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

    private function givenHyvaTheme(string $path): void
    {
        $this->themePath->method('getPath')->with('Vendor/theme')->willReturn($path);
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('HyvaThemes');
        $this->builderPool->method('getBuilder')->with($path)->willReturn($builder);
    }

    public function testNormalizesTrailingSlashInThemePath(): void
    {
        $this->themePath->method('getPath')->with('Vendor/theme')->willReturn('/theme/');
        $builder = $this->createMock(BuilderInterface::class);
        $builder->method('getName')->willReturn('HyvaThemes');
        $this->builderPool->method('getBuilder')->willReturn($builder);
        $this->fileDriver->method('isDirectory')->willReturn(true);
        $this->shell
            ->expects($this->once())
            ->method('execute')
            ->with('cd %s && npx hyva-tokens', ['/theme/web/tailwind']);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['themeCode' => 'Vendor/theme']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
    }
}
