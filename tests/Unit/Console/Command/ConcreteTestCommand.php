<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Console\Command;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Minimal concrete command exposing AbstractCommand's protected API for direct testing.
 */
class ConcreteTestCommand extends AbstractCommand
{
    private int $executeCommandReturn = Cli::RETURN_SUCCESS;
    private ?\Throwable $throwOnExecute = null;
    private ?bool $realTtyAvailableOverride = null;

    protected function configure(): void
    {
        $this->setName('test:concrete-command');
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        if ($this->throwOnExecute !== null) {
            throw $this->throwOnExecute;
        }

        return $this->executeCommandReturn;
    }

    protected function isRealTtyAvailable(): bool
    {
        return $this->realTtyAvailableOverride ?? parent::isRealTtyAvailable();
    }

    public function setRealTtyAvailable(?bool $available): void
    {
        $this->realTtyAvailableOverride = $available;
    }

    public function setExecuteCommandReturn(int $code): void
    {
        $this->executeCommandReturn = $code;
    }

    public function setThrowOnExecute(\Throwable $exception): void
    {
        $this->throwOnExecute = $exception;
    }

    public function setIoForTest(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function callGetCommandName(string $group, string $command): string
    {
        return $this->getCommandName($group, $command);
    }

    public function callIsVerbose(OutputInterface $output): bool
    {
        return $this->isVerbose($output);
    }

    public function callIsVeryVerbose(OutputInterface $output): bool
    {
        return $this->isVeryVerbose($output);
    }

    public function callIsDebug(OutputInterface $output): bool
    {
        return $this->isDebug($output);
    }

    /**
     * @param array<string> $themeCodes
     * @return array<string>
     */
    public function callResolveVendorThemes(array $themeCodes, ThemeList $themeList): array
    {
        return $this->resolveVendorThemes($themeCodes, $themeList);
    }

    public function callHandleInvalidThemeWithSuggestions(
        string $invalidTheme,
        ThemeSuggester $themeSuggester,
        OutputInterface $output,
    ): ?string {
        return $this->handleInvalidThemeWithSuggestions($invalidTheme, $themeSuggester, $output);
    }

    public function callIsInteractiveTerminal(OutputInterface $output): bool
    {
        return $this->isInteractiveTerminal($output);
    }

    public function callSetPromptEnvironment(): void
    {
        $this->setPromptEnvironment();
    }

    public function callResetPromptEnvironment(): void
    {
        $this->resetPromptEnvironment();
    }
}
