<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Hyva;

use Laravel\Prompts\MultiSelectPrompt;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Service\Hyva\CompatibilityChecker;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompatibilityCheckCommand extends AbstractCommand
{
    private const OPTION_SHOW_ALL = 'show-all';
    private const OPTION_THIRD_PARTY_ONLY = 'third-party-only';
    private const OPTION_INCLUDE_VENDOR = 'include-vendor';
    private const OPTION_DETAILED = 'detailed';

    private array $originalEnv = [];
    private array $secureEnvStorage = [];

    public function __construct(
        private readonly CompatibilityChecker $compatibilityChecker
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName($this->getCommandName('hyva', 'compatibility:check'))
            ->setDescription('Check modules for Hyvä theme compatibility issues')
            ->setAliases(['m:h:c:c', 'hyva:check'])
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
                'Include vendor modules in scan (default: excluded)'
            )
            ->addOption(
                self::OPTION_DETAILED,
                'd',
                InputOption::VALUE_NONE,
                'Show detailed file-level issues for incompatible modules'
            );
    }

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
     */
    private function runInteractiveMode(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Hyvä Theme Compatibility Check');

        // Set environment variables for Laravel Prompts
        $this->setPromptEnvironment();

        try {
            $scanOptionsPrompt = new MultiSelectPrompt(
                label: 'Select scan options',
                options: [
                    'show-all' => 'Show all modules including compatible ones',
                    'include-vendor' => 'Include Magento core modules (default: third-party only)',
                    'detailed' => 'Show detailed file-level issues with line numbers',
                ],
                default: [],
                hint: 'Space to toggle, Enter to confirm. Default: third-party modules only',
                required: false,
            );

            $selectedOptions = $scanOptionsPrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            $this->resetPromptEnvironment();

            // Apply selected options to input
            $showAll = in_array('show-all', $selectedOptions);
            $includeVendor = in_array('include-vendor', $selectedOptions);
            $detailed = in_array('detailed', $selectedOptions);
            $thirdPartyOnly = false; // Not needed in interactive mode

            // Show selected configuration
            $this->io->newLine();
            $config = [];
            if ($showAll) {
                $config[] = 'Show all modules';
            }
            if ($includeVendor) {
                $config[] = 'Include Magento core';
            } else {
                $config[] = 'Third-party modules only';
            }
            if ($detailed) {
                $config[] = 'Detailed issues';
            }
            $this->io->comment('Configuration: ' . implode(', ', $config));
            $this->io->newLine();

            // Run scan with selected options
            return $this->runScan($showAll, $thirdPartyOnly, $includeVendor, $detailed, $output);
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            $this->io->info('Falling back to default scan (third-party modules only)...');
            $this->io->newLine();
            return $this->runDirectMode($input, $output);
        }
    }

    /**
     * Run direct mode with command line options
     */
    private function runDirectMode(InputInterface $input, OutputInterface $output): int
    {
        $showAll = $input->getOption(self::OPTION_SHOW_ALL);
        $thirdPartyOnly = $input->getOption(self::OPTION_THIRD_PARTY_ONLY);
        $includeVendor = $input->getOption(self::OPTION_INCLUDE_VENDOR);
        $detailed = $input->getOption(self::OPTION_DETAILED);

        $this->io->title('Hyvä Theme Compatibility Check');

        return $this->runScan($showAll, $thirdPartyOnly, $includeVendor, $detailed, $output);
    }

    /**
     * Run the actual compatibility scan
     */
    private function runScan(
        bool $showAll,
        bool $thirdPartyOnly,
        bool $includeVendor,
        bool $detailed,
        OutputInterface $output
    ): int {

        // Determine filter logic:
        // - Default (no flags): Scan third-party only (exclude Magento_* but include vendor third-party)
        // - With --include-vendor: Scan everything including Magento_*
        // - With --third-party-only: Explicitly scan only third-party
        $scanThirdPartyOnly = $thirdPartyOnly || (!$includeVendor && !$thirdPartyOnly);
        $excludeVendor = false; // Always include vendor for third-party scanning

        // Run the compatibility check
        $results = $this->compatibilityChecker->check(
            $this->io,
            $output,
            $showAll,
            $scanThirdPartyOnly,
            $excludeVendor
        );

        // Display results
        $this->displayResults($results, $showAll || $detailed);

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

        // Return appropriate exit code
        return $results['summary']['criticalIssues'] > 0
            ? Cli::RETURN_FAILURE
            : Cli::RETURN_SUCCESS;
    }

    /**
     * Display compatibility check results
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
            $this->io->error(sprintf(
                'Found %d critical compatibility issue(s) that need attention.',
                $summary['criticalIssues']
            ));
        } elseif ($summary['warningIssues'] > 0) {
            $this->io->warning(sprintf(
                'Found %d warning(s). Review these for potential issues.',
                $summary['warningIssues']
            ));
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

    /**
     * Check if running in an interactive terminal
     */
    private function isInteractiveTerminal(OutputInterface $output): bool
    {
        // Check if output is decorated (supports ANSI codes)
        if (!$output->isDecorated()) {
            return false;
        }

        // Check if STDIN is available and readable
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return false;
        }

        // Check for common non-interactive environments
        $nonInteractiveEnvs = [
            'CI',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'TEAMCITY_VERSION',
        ];

        foreach ($nonInteractiveEnvs as $env) {
            if ($this->getEnvVar($env) || $this->getServerVar($env)) {
                return false;
            }
        }

        // Additional check: try to detect if running in a proper TTY
        $sttyOutput = shell_exec('stty -g 2>/dev/null');
        return !empty($sttyOutput);
    }

    /**
     * Set environment for Laravel Prompts to work properly in Docker/DDEV
     */
    private function setPromptEnvironment(): void
    {
        // Store original values for reset
        $this->originalEnv = [
            'COLUMNS' => $this->getEnvVar('COLUMNS'),
            'LINES' => $this->getEnvVar('LINES'),
            'TERM' => $this->getEnvVar('TERM'),
        ];

        // Set terminal environment variables using safe method
        $this->setEnvVar('COLUMNS', '100');
        $this->setEnvVar('LINES', '40');
        $this->setEnvVar('TERM', 'xterm-256color');
    }

    /**
     * Reset terminal environment after prompts
     */
    private function resetPromptEnvironment(): void
    {
        // Reset environment variables to original state using secure methods
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                // Remove from our secure cache
                $this->removeSecureEnvironmentValue($key);
            } else {
                // Restore original value using secure method
                $this->setEnvVar($key, $value);
            }
        }
    }

    /**
     * Securely remove environment variable from cache
     */
    private function removeSecureEnvironmentValue(string $name): void
    {
        // Remove the specific variable from our secure storage
        unset($this->secureEnvStorage[$name]);

        // Clear the static cache to force refresh on next access
        $this->clearEnvironmentCache();
    }

    /**
     * Simplified environment variable getter
     */
    private function getEnvVar(string $name): ?string
    {
        // Check secure storage first
        if (isset($this->secureEnvStorage[$name])) {
            return $this->secureEnvStorage[$name];
        }

        // Fall back to system environment
        $value = getenv($name);
        return $value !== false ? $value : null;
    }

    /**
     * Simplified server variable getter
     */
    private function getServerVar(string $name): ?string
    {
        return $_SERVER[$name] ?? null;
    }

    /**
     * Simplified environment variable setter
     */
    private function setEnvVar(string $name, string $value): void
    {
        $this->secureEnvStorage[$name] = $value;
        putenv("$name=$value");
    }

    /**
     * Clear environment cache
     */
    private function clearEnvironmentCache(): void
    {
        // Force refresh on next access by clearing our storage
        $this->secureEnvStorage = array_filter(
            $this->secureEnvStorage,
            fn($key) => in_array($key, ['COLUMNS', 'LINES', 'TERM']),
            ARRAY_FILTER_USE_KEY
        );
    }
}
