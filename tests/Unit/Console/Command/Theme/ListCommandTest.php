<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command\Theme;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\Theme\ListCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListCommandTest extends TestCase
{
    /**
     * @var ThemeList&MockObject
     */
    private $themeList;
    /**
     * @var ListCommand
     */
    private ListCommand $command;

    protected function setUp(): void
    {
        $this->themeList = $this->createMock(ThemeList::class);
        $this->command = new ListCommand($this->themeList);
    }

    public function testDisplaysInfoMessageWhenNoThemesExist(): void
    {
        $this->themeList->method('getAllThemes')->willReturn([]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('No themes found.', $tester->getDisplay());
    }

    public function testDisplaysTableOfAvailableThemes(): void
    {
        $theme = new FakeThemeWithTitle('Vendor/theme', 'Vendor Theme');

        $this->themeList->method('getAllThemes')->willReturn(['frontend/Vendor/theme' => $theme]);

        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([]);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Available Themes:', $display);
        $this->assertStringContainsString('Vendor/theme', $display);
        $this->assertStringContainsString('Vendor Theme', $display);
        $this->assertStringContainsString('frontend/Vendor/theme', $display);
    }

    public function testCommandNameAndAliases(): void
    {
        $this->assertSame('mageforge:theme:list', $this->command->getName());
        $this->assertSame(['frontend:list'], $this->command->getAliases());
    }
}
