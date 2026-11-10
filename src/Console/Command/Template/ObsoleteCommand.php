<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Template;

use Laravel\Prompts\MultiSearchPrompt;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\View\Design\ThemeInterface;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ObsoleteOverride;
use OpenForgeProject\MageForge\Model\ObsoleteReason;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\TemplateOverride\AreaEmulator;
use OpenForgeProject\MageForge\Service\TemplateOverride\ObsoleteOverrideScanner;
use OpenForgeProject\MageForge\Service\TemplateOverride\OverrideRemover;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command that finds theme overrides which are no longer needed
 *
 * Reports overrides that are identical to the file they override, overrides whose module or
 * source file is gone, templates that are not referenced anywhere (--deep) and overrides that
 * were copied from an older module version.
 */
class ObsoleteCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     * @param ThemeSuggester $themeSuggester
     * @param ObsoleteOverrideScanner $scanner
     * @param OverrideRemover $remover
     * @param AreaEmulator $areaEmulator
     * @param CacheCleaner $cacheCleaner
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ThemeSuggester $themeSuggester,
        private readonly ObsoleteOverrideScanner $scanner,
        private readonly OverrideRemover $remover,
        private readonly AreaEmulator $areaEmulator,
        private readonly CacheCleaner $cacheCleaner,
        private readonly DirectoryList $directoryList,
    ) {
        parent::__construct();
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName($this->getCommandName('template', 'obsolete'))
            ->setDescription('Finds theme template overrides that are no longer needed')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Theme codes to check (format: Vendor/theme). Without it a theme selection is shown',
            )
            ->addOption(
                'deep',
                null,
                InputOption::VALUE_NONE,
                'Also list templates with no found reference (slower, heuristic, may include templates still in use)',
            )
            ->addOption(
                'delete',
                null,
                InputOption::VALUE_NONE,
                'Delete the overrides that are safe to remove (identical or belonging to a missing module)',
            )
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Delete without asking for confirmation')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table or json', 'table')
            ->addOption(
                'fail-on-found',
                null,
                InputOption::VALUE_NONE,
                'Return a failure exit code when obsolete overrides were found (for CI)',
            )
            ->setAliases(['template:obsolete']);
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $format */
        $format = $input->getOption('format');
        $isDelete = (bool) $input->getOption('delete');
        if (!in_array($format, ['table', 'json'], true)) {
            $this->io->error("Invalid format '$format'. Use 'table' or 'json'.");
            return Cli::RETURN_FAILURE;
        }
        if ($isDelete && $format === 'json') {
            $this->io->error('--delete cannot be combined with --format=json.');
            return Cli::RETURN_FAILURE;
        }

        $isInteractive = $format === 'table' && $input->isInteractive() && $this->isInteractiveTerminal($output);

        $themes = $this->resolveThemes($input, $output, $isInteractive);
        if ($themes === null) {
            return Cli::RETURN_FAILURE;
        }
        if ($themes === []) {
            if ($format === 'json') {
                $this->io->writeln('[]');
            } else {
                $this->io->warning('No frontend themes found to check.');
            }
            return Cli::RETURN_SUCCESS;
        }

        $isDeep = (bool) $input->getOption('deep');
        if (!$isDeep && $isInteractive) {
            $isDeep = $this->io->confirm(
                'Also list templates without a found reference (--deep, slower, results may be false positives)?',
                false,
            );
        }

        $findings = [];
        foreach ($themes as $theme) {
            $this->areaEmulator->emulate($theme->getArea());
            array_push($findings, ...$this->scanner->scan($theme, $isDeep));
        }

        if ($format === 'json') {
            $this->io->writeln($this->toJson($findings));
        } else {
            $this->displayFindings($findings, array_map(static fn($theme) => $theme->getCode(), $themes));
        }

        $deletionFailed = false;
        $hasSafeFindings = array_filter($findings, static fn($finding) => $finding->isSafeToDelete()) !== [];
        if ($isDelete || $isInteractive && $hasSafeFindings) {
            [$findings, $deletionFailed] = $this->deleteSafeFindings(
                $findings,
                (bool) $input->getOption('yes'),
                $output,
            );
        }

        if ($deletionFailed) {
            return Cli::RETURN_FAILURE;
        }

        $failOnFound = (bool) $input->getOption('fail-on-found');

        return $failOnFound && $findings !== [] ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    /**
     * Determine the themes to check
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param bool $isInteractive
     * @return ThemeInterface[]|null Null when a given theme is invalid
     */
    private function resolveThemes(InputInterface $input, OutputInterface $output, bool $isInteractive): ?array
    {
        /** @var array<string, ThemeInterface> $frontendThemes */
        $frontendThemes = [];
        /** @var ThemeInterface[] $allThemes */
        $allThemes = $this->themeList->getAllThemes();
        foreach ($allThemes as $theme) {
            if ($theme->getArea() === Area::AREA_FRONTEND) {
                $frontendThemes[$theme->getCode()] = $theme;
            }
        }

        /** @var string[] $themeCodes */
        $themeCodes = $input->getArgument('themeCodes');
        if ($themeCodes === [] && $isInteractive) {
            $themeCodes = $this->promptForThemes(array_keys($frontendThemes));
            if ($themeCodes === []) {
                $this->io->info('No themes selected.');
                return [];
            }
        }

        if ($themeCodes === []) {
            return array_values(array_filter(
                $frontendThemes,
                fn(ThemeInterface $theme): bool => !$this->scanner->isVendorTheme($theme),
            ));
        }

        $themes = [];
        foreach ($themeCodes as $themeCode) {
            if (!isset($frontendThemes[$themeCode])) {
                $corrected = $this->handleInvalidThemeWithSuggestions($themeCode, $this->themeSuggester, $output);
                if ($corrected === null || !isset($frontendThemes[$corrected])) {
                    return null;
                }
                $themeCode = $corrected;
            }
            $themes[$themeCode] = $frontendThemes[$themeCode];
        }

        return array_values($themes);
    }

    /**
     * Let the user pick the themes in the same searchable menu the build command uses
     *
     * @param string[] $options
     * @return string[]
     */
    protected function promptForThemes(array $options): array
    {
        $this->setPromptEnvironment();

        try {
            $prompt = new MultiSearchPrompt(
                label: 'Select themes to check for obsolete overrides',
                options: static fn(string $value) => $value === ''
                    ? $options
                    : array_values(array_filter($options, static fn($option) => stripos($option, $value) !== false)),
                placeholder: 'Type to search theme...',
                hint: 'Type to search, arrow keys to navigate, Space to toggle, Enter to confirm',
                required: false,
            );
            /** @var string[] $selected */
            $selected = $prompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();

            return $selected;
        } catch (\Exception $e) {
            $this->io->error('Interactive mode failed: ' . $e->getMessage());

            return [];
        } finally {
            $this->resetPromptEnvironment();
        }
    }

    /**
     * Display the findings grouped by theme
     *
     * @param ObsoleteOverride[] $findings
     * @param string[] $themeCodes
     * @return void
     */
    private function displayFindings(array $findings, array $themeCodes): void
    {
        $this->io->title('Obsolete template overrides');

        if ($findings === []) {
            $this->io->success(sprintf('No obsolete overrides found in: %s', implode(', ', $themeCodes)));
            return;
        }

        $byTheme = [];
        foreach ($findings as $finding) {
            $byTheme[$finding->getThemeCode()][] = $finding;
        }

        foreach ($byTheme as $themeCode => $themeFindings) {
            $this->io->section($themeCode);
            $this->io->table(['Reason', 'Template', 'File', 'Details'], array_map(
                fn(ObsoleteOverride $finding): array => [
                    $finding->getReason()->label() . ($finding->isSafeToDelete() ? ' *' : ''),
                    $finding->getTemplateId(),
                    $this->toRelativePath($finding->getFile()),
                    $finding->getDetails(),
                ],
                $themeFindings,
            ));
        }

        $hasPossiblyUnused = array_filter(
            $findings,
            static fn($finding) => $finding->getReason() === ObsoleteReason::POSSIBLY_UNUSED,
        ) !== [];
        $safeCount = count(array_filter($findings, static fn($finding) => $finding->isSafeToDelete()));
        $this->io->text(sprintf(
            '%d obsolete override(s) found, %d marked with * can be deleted safely (--delete).',
            count($findings),
            $safeCount,
        ));
        $this->io->text('Outdated/Orphaned (source missing) results need a manual review.');
        if ($hasPossiblyUnused) {
            $this->io->warning(
                '"Possibly unused" is only a hint. Templates can still be used through widget.xml, di.xml, '
                . 'the database or dynamically built paths. Do not delete them without checking.',
            );
        }
    }

    /**
     * Delete the overrides that can be removed without changing the rendered result
     *
     * @param ObsoleteOverride[] $findings
     * @param bool $assumeYes
     * @param OutputInterface $output
     * @return array{0: ObsoleteOverride[], 1: bool} Remaining findings and whether the deletion failed
     */
    private function deleteSafeFindings(array $findings, bool $assumeYes, OutputInterface $output): array
    {
        $safe = array_values(array_filter($findings, static fn($finding) => $finding->isSafeToDelete()));
        if ($safe === []) {
            $this->io->text('Nothing to delete.');
            return [$findings, false];
        }

        if (!$assumeYes && !$this->io->confirm(sprintf('Delete %d override file(s)?', count($safe)), false)) {
            $this->io->text('Aborted, no files were deleted.');
            return [$findings, false];
        }

        $deleted = [];
        $failed = false;
        foreach ($safe as $finding) {
            if (!$this->remover->remove($finding)) {
                $this->io->warning('Could not delete ' . $this->toRelativePath($finding->getFile()));
                $failed = true;
                continue;
            }
            $deleted[] = $finding;
        }

        $remaining = array_values(array_filter($findings, static fn($finding) => !in_array($finding, $deleted, true)));
        if ($deleted === []) {
            return [$remaining, $failed];
        }

        $this->io->success(sprintf('Deleted %d override file(s).', count($deleted)));
        $cacheCleaned = $this->cacheCleaner->clean($this->io, $this->isVerbose($output));

        return [$remaining, $failed || !$cacheCleaned];
    }

    /**
     * Encode the findings as JSON
     *
     * @param ObsoleteOverride[] $findings
     * @return string
     */
    private function toJson(array $findings): string
    {
        return json_encode(
            array_map(fn(ObsoleteOverride $finding): array => [
                'theme' => $finding->getThemeCode(),
                'template' => $finding->getTemplateId(),
                'file' => $this->toRelativePath($finding->getFile()),
                'reason' => $finding->getReason()->value,
                'details' => $finding->getDetails(),
                'safe_to_delete' => $finding->isSafeToDelete(),
            ], $findings),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Make a path relative to the Magento root directory for display
     *
     * @param string $path
     * @return string
     */
    private function toRelativePath(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->directoryList->getRoot()), '/');
        $normalized = str_replace('\\', '/', $path);

        return $root !== '' && str_starts_with($normalized, $root . '/')
            ? substr($normalized, strlen($root) + 1)
            : $path;
    }
}
