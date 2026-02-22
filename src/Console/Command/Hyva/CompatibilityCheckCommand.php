<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Hyva;

use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\SelectPrompt;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Service\Hyva\CompatibilityChecker;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to check Magento modules for Hyvä theme compatibility issues
 *
 * Scans modules for RequireJS, Knockout.js, jQuery, and UI Components usage
 * that would be incompatible with Hyvä themes.
 */
class CompatibilityCheckCommand extends AbstractCommand
{
    private const OPTION_SHOW_ALL = 'show-all';
    private const OPTION_THIRD_PARTY_ONLY = 'third-party-only';
    private const OPTION_INCLUDE_VENDOR = 'include-vendor';
    private const OPTION_DETAILED = 'detailed';

    private const DISPLAY_MODE_ISSUES = 'issues';
    private const DISPLAY_MODE_INCOMPATIBLE_ONLY = 'incompatible-only';
    private const DISPLAY_MODE_SHOW_ALL = 'show-all';

    private const SCOPE_THIRD_PARTY = 'third-party';
    private const SCOPE_ALL = 'all';

    /**
     * @param CompatibilityChecker $compatibilityChecker
     */
    public function __construct(
        private readonly CompatibilityChecker $compatibilityChecker
    ) {
        parent::__construct();
    }

    /**
     * Configure command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('hyva', 'compatibility:check'))
            ->setDescription('Check modules for Hyvä theme compatibility issues')
            ->setAliases(['hyva:check'])
            ->addOption(
                self::OPTION_SHOW_ALL,
                'a',
                InputOption::VALUE_NONE,
                'Show all modules including compatible ones'
            )
            ->addOption(
                self::OPTION_THIRD_PARTY_ONLY,
                't',
                InputOption::VALUE_NONE,
                'Check only third-party modules (exclude Magento_* modules)'
            )
            ->addOption(
                self::OPTION_INCLUDE_VENDOR,
                null,
                InputOption::VALUE_NONE,
                'Include Magento core modules (default: third-party modules only)'
            )
            ->addOption(
                self::OPTION_DETAILED,
                'd',
                InputOption::VALUE_NONE,
                'Show detailed file-level issues for incompatible modules'
            );
    }

    /**
     * Execute compatibility check command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        // Check if we're in interactive mode (no options provided)
        $hasOptions = $input->getOption(self::OPTION_SHOW_ALL)
            || $input->getOption(self::OPTION_THIRD_PARTY_ONLY)
            || $input->getOption(self::OPTION_INCLUDE_VENDOR)
            || $input->getOption(self::OPTION_DETAILED);

        if (!$hasOptions && $this->isInteractiveTerminal($output)) {
            return $this->runInteractiveMode($input, $output);
        }

        return $this->runDirectMode($input, $output);
    }

    /**
     * Run interactive mode with Laravel Prompts
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function runInteractiveMode(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Hyvä Theme Compatibility Check');

        // Set environment variables for Laravel Prompts
        $this->setPromptEnvironment();

        try {
            // Display mode selection
            $displayModePrompt = new SelectPrompt(
                label: 'What modules do you want to see?',
                options: [
                    self::DISPLAY_MODE_ISSUES => 'Modules with any issues (warnings or critical)',
                    self::DISPLAY_MODE_INCOMPATIBLE_ONLY => 'Only modules with critical issues',
                    self::DISPLAY_MODE_SHOW_ALL => 'All modules including compatible ones',
                ],
                default: self::DISPLAY_MODE_ISSUES,
            );

            $displayMode = $displayModePrompt->prompt();

            // Module scope selection
            $scopePrompt = new SelectPrompt(
                label: 'Which modules to scan?',
                options: [
                    self::SCOPE_THIRD_PARTY => 'Third-party modules only (exclude Magento_*)',
                    self::SCOPE_ALL => 'All modules including Magento core',
                ],
                default: self::SCOPE_THIRD_PARTY,
            );

            $scope = $scopePrompt->prompt();

            // Detailed view confirmation
            $detailedPrompt = new ConfirmPrompt(
                label: 'Show detailed file-level issues?',
                default: false,
            );

            $detailed = $detailedPrompt->prompt();

            // Map selected options to flags
            $showAll = $displayMode === self::DISPLAY_MODE_SHOW_ALL;
            $incompatibleOnly = $displayMode === self::DISPLAY_MODE_INCOMPATIBLE_ONLY;
            $includeVendor = $scope === self::SCOPE_ALL;
            $thirdPartyOnly = false; // Not needed in interactive mode

            // Show selected configuration
            $this->io->newLine();
            $config = [];
            if ($showAll) {
                $config[] = 'Show all modules';
            } elseif ($incompatibleOnly) {
                $config[] = 'Show incompatible only';
            } else {
                $config[] = 'Show modules with issues';
            }
            $config[] = $includeVendor ? 'Include Magento core' : 'Third-party modules only';
            if ($detailed) {
                $config[] = 'Detailed issues';
            }
            $this->io->comment('Configuration: ' . implode(', ', $config));
            $this->io->newLine();

            // Run scan with selected options
            return $this->runScan($showAll, $thirdPartyOnly, $includeVendor, $detailed, $incompatibleOnly, $output);
        } catch (\Throwable $e) {
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            $this->io->info('Falling back to default scan (third-party modules only)...');
            $this->io->newLine();
            return $this->runDirectMode($input, $output);
        } finally {
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            $this->resetPromptEnvironment();
        }
    }

    /**
     * Run direct mode with command line options
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function runDirectMode(InputInterface $input, OutputInterface $output): int
    {
        $showAll = $input->getOption(self::OPTION_SHOW_ALL);
        $thirdPartyOnly = $input->getOption(self::OPTION_THIRD_PARTY_ONLY);
        $includeVendor = $input->getOption(self::OPTION_INCLUDE_VENDOR);
        $detailed = $input->getOption(self::OPTION_DETAILED);

        $this->io->title('Hyvä Theme Compatibility Check');

        return $this->runScan($showAll, $thirdPartyOnly, $includeVendor, $detailed, false, $output);
    }

    /**
     * Run the actual compatibility scan
     *
     * @param bool $showAll
     * @param bool $thirdPartyOnly
     * @param bool $includeVendor
     * @param bool $detailed
     * @param bool $incompatibleOnly
     * @param OutputInterface $output
     * @return int
     */
    private function runScan(
        bool $showAll,
        bool $thirdPartyOnly,
        bool $includeVendor,
        bool $detailed,
        bool $incompatibleOnly,
        OutputInterface $output
    ): int {

        // Determine filter logic:
        // - thirdPartyOnly: Only scan non-Magento_* modules (default behavior)
        // - includeVendor: Also scan Magento_* core modules
        // - excludeVendor: Whether to exclude vendor/ directory (always false for now)
        $scanThirdPartyOnly = !$includeVendor;
        $excludeVendor = false;

        // Run the compatibility check
        $results = $this->compatibilityChecker->check(
            $this->io,
            $output,
            $showAll,
            $scanThirdPartyOnly,
            $excludeVendor
        );

        // Determine display mode:
        // showAll = show all modules including compatible ones
        // incompatibleOnly = show only modules with critical issues
        // default = show modules with any issues (critical or warnings)
        $displayShowAll = $showAll && !$incompatibleOnly;

        // Display results
        $this->displayResults($results, $displayShowAll);

        // Display detailed issues if requested
        if ($detailed && $results['hasIncompatibilities']) {
            $this->displayDetailedIssues($results);
        }

        // Display summary
        $this->displaySummary($results);

        // Display recommendations if there are issues
        if ($results['hasIncompatibilities']) {
            $this->displayRecommendations();
        }

        // Add spacing before exit
        $this->io->newLine();

        // Return appropriate exit code
        return $results['summary']['criticalIssues'] > 0
            ? Cli::RETURN_FAILURE
            : Cli::RETURN_SUCCESS;
    }

    /**
     * Display compatibility check results
     *
     * @param array<string, mixed> $results
     * @param bool $showAll
     */
    private function displayResults(array $results, bool $showAll): void
    {
        $this->io->section('Compatibility Results');

        $tableData = $this->compatibilityChecker->formatResultsForDisplay($results, $showAll);

        if (empty($tableData)) {
            $this->io->success('All scanned modules are compatible with Hyvä!');
            return;
        }

        $this->io->table(
            ['Module', 'Status', 'Issues'],
            $tableData
        );
    }

    /**
     * Display detailed file-level issues
     *
     * @param array<string, mixed> $results
     */
    private function displayDetailedIssues(array $results): void
    {
        $this->io->section('Detailed Issues');

        foreach ($results['modules'] as $moduleName => $moduleData) {
            // Only show modules with issues
            if ($moduleData['compatible'] && !$moduleData['hasWarnings']) {
                continue;
            }

            $this->io->text(sprintf('<fg=cyan>%s</>', $moduleName));

            $detailedIssues = $this->compatibilityChecker->getDetailedIssues($moduleName, $moduleData);

            foreach ($detailedIssues as $fileData) {
                $this->io->text(sprintf('  <fg=yellow>%s</>', $fileData['file']));

                foreach ($fileData['issues'] as $issue) {
                    $color = $issue['severity'] === 'critical' ? 'red' : 'yellow';
                    $symbol = $issue['severity'] === 'critical' ? '✗' : '⚠';

                    $this->io->text(sprintf(
                        '    <fg=%s>%s</> Line %d: %s',
                        $color,
                        $symbol,
                        $issue['line'],
                        $issue['description']
                    ));
                }
            }

            $this->io->newLine();
        }
    }

    /**
     * Display summary statistics
     *
     * @param array<string, mixed> $results
     */
    private function displaySummary(array $results): void
    {
        $summary = $results['summary'];

        $this->io->section('Summary');

        $summaryData = [
            ['Total Modules Scanned', $summary['total']],
            new TableSeparator(),
            ['Compatible', sprintf('<fg=green>%d</>', $summary['compatible'])],
            ['Incompatible', sprintf('<fg=red>%d</>', $summary['incompatible'])],
            ['Hyvä-Aware Modules', sprintf('<fg=cyan>%d</>', $summary['hyvaAware'])],
            new TableSeparator(),
            ['Critical Issues', sprintf('<fg=red>%d</>', $summary['criticalIssues'])],
            ['Warnings', sprintf('<fg=yellow>%d</>', $summary['warningIssues'])],
        ];

        $this->io->table([], $summaryData);

        // Final message
        if ($summary['criticalIssues'] > 0) {
            $this->io->newLine();
            $this->io->writeln(sprintf(
                '<fg=red>⚠</> Found <fg=red;options=bold>%d critical compatibility issue(s)</> in %d scanned modules.',
                $summary['criticalIssues'],
                $summary['total']
            ));
            $this->io->writeln('These modules require modifications to work with Hyvä themes.');
        } elseif ($summary['warningIssues'] > 0) {
            $this->io->newLine();
            $this->io->writeln(sprintf(
                '<fg=yellow>ℹ</> Found <fg=yellow;options=bold>%d warning(s)</> in %d scanned modules.',
                $summary['warningIssues'],
                $summary['total']
            ));
            $this->io->writeln('Review these modules for potential compatibility issues.');
        } else {
            $this->io->success('All scanned modules are Hyvä compatible!');
        }
    }

    /**
     * Display helpful recommendations
     */
    private function displayRecommendations(): void
    {
        $this->io->section('Recommendations');

        $recommendations = [
            '• Check if Hyvä compatibility packages exist for incompatible modules',
            '• Review <fg=cyan>https://hyva.io/compatibility</> for known solutions',
            '• Consider refactoring RequireJS/Knockout code to Alpine.js',
            '• Contact module vendors for Hyvä-compatible versions',
        ];

        foreach ($recommendations as $recommendation) {
            $this->io->text($recommendation);
        }
    }
}
