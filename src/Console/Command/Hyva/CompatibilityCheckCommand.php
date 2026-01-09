<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Hyva;

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
        $showAll = $input->getOption(self::OPTION_SHOW_ALL);
        $thirdPartyOnly = $input->getOption(self::OPTION_THIRD_PARTY_ONLY);
        $includeVendor = $input->getOption(self::OPTION_INCLUDE_VENDOR);
        $detailed = $input->getOption(self::OPTION_DETAILED);

        $this->io->title('Hyvä Theme Compatibility Check');

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

        // Provide helpful recommendations
        $this->displayRecommendations($results);
    }

    /**
     * Display helpful recommendations
     */
    private function displayRecommendations(array $results): void
    {
        if ($results['summary']['incompatible'] === 0) {
            return;
        }

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
