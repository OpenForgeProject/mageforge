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
    /**
     * @var int
     */
    private int $executeCommandReturn = Cli::RETURN_SUCCESS;
    /**
     * @var ?\Throwable
     */
    private ?\Throwable $throwOnExecute = null;
    /**
     * @var ?bool
     */
    private ?bool $realTtyAvailableOverride = null;

    /**
     * Configures the command name.
     */
    protected function configure(): void
    {
        $this->setName('test:concrete-command');
    }

    /**
     * Returns the preconfigured exit code, or throws the preconfigured exception.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        if ($this->throwOnExecute !== null) {
            throw $this->throwOnExecute;
        }

        return $this->executeCommandReturn;
    }

    /**
     * Returns the overridden TTY availability, falling back to the parent implementation.
     */
    protected function isRealTtyAvailable(): bool
    {
        return $this->realTtyAvailableOverride ?? parent::isRealTtyAvailable();
    }

    /**
     * Overrides the TTY availability reported to the command.
     *
     * @param ?bool $available
     */
    public function setRealTtyAvailable(?bool $available): void
    {
        $this->realTtyAvailableOverride = $available;
    }

    /**
     * Sets the exit code returned by executeCommand().
     *
     * @param int $code
     */
    public function setExecuteCommandReturn(int $code): void
    {
        $this->executeCommandReturn = $code;
    }

    /**
     * Makes executeCommand() throw the given exception.
     *
     * @param \Throwable $exception
     */
    public function setThrowOnExecute(\Throwable $exception): void
    {
        $this->throwOnExecute = $exception;
    }

    /**
     * Injects the SymfonyStyle IO used by the command.
     *
     * @param SymfonyStyle $io
     */
    public function setIoForTest(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    /**
     * Exposes the protected getCommandName().
     *
     * @param string $group
     * @param string $command
     */
    public function callGetCommandName(string $group, string $command): string
    {
        return $this->getCommandName($group, $command);
    }

    /**
     * Exposes the protected isVerbose().
     *
     * @param OutputInterface $output
     */
    public function callIsVerbose(OutputInterface $output): bool
    {
        return $this->isVerbose($output);
    }

    /**
     * Exposes the protected isVeryVerbose().
     *
     * @param OutputInterface $output
     */
    public function callIsVeryVerbose(OutputInterface $output): bool
    {
        return $this->isVeryVerbose($output);
    }

    /**
     * Exposes the protected isDebug().
     *
     * @param OutputInterface $output
     */
    public function callIsDebug(OutputInterface $output): bool
    {
        return $this->isDebug($output);
    }

    /**
     * Exposes the protected resolveVendorThemes().
     *
     * @param array<string> $themeCodes
     * @param ThemeList $themeList
     * @return array<string>
     */
    public function callResolveVendorThemes(array $themeCodes, ThemeList $themeList): array
    {
        return $this->resolveVendorThemes($themeCodes, $themeList);
    }

    /**
     * Exposes the protected handleInvalidThemeWithSuggestions().
     *
     * @param string $invalidTheme
     * @param ThemeSuggester $themeSuggester
     * @param OutputInterface $output
     */
    public function callHandleInvalidThemeWithSuggestions(
        string $invalidTheme,
        ThemeSuggester $themeSuggester,
        OutputInterface $output,
    ): ?string {
        return $this->handleInvalidThemeWithSuggestions($invalidTheme, $themeSuggester, $output);
    }

    /**
     * Exposes the protected isInteractiveTerminal().
     *
     * @param OutputInterface $output
     */
    public function callIsInteractiveTerminal(OutputInterface $output): bool
    {
        return $this->isInteractiveTerminal($output);
    }

    /**
     * Exposes the protected setPromptEnvironment().
     */
    public function callSetPromptEnvironment(): void
    {
        $this->setPromptEnvironment();
    }

    /**
     * Exposes the protected resetPromptEnvironment().
     */
    public function callResetPromptEnvironment(): void
    {
        $this->resetPromptEnvironment();
    }
}
