<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Spinner;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeChecker\CheckerPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for checking Magento themes for outdated dependencies
 */
class CheckCommand extends AbstractCommand
{
    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param CheckerPool $checkerPool
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly CheckerPool $checkerPool
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'check'))
            ->setDescription('Checks a Magento theme for outdated dependencies')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'The codes of the themes to check'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCodes = $input->getArgument('themeCodes');

        if (empty($themeCodes)) {
            $themes = $this->themeList->getAllThemes();
            $options = [];
            foreach ($themes as $theme) {
                $options[] = $theme->getCode();
            }

            $themeCodesPrompt = new MultiSelectPrompt(
                label: 'Select themes to check',
                options: $options,
                scroll: 10,
                hint: 'Arrow keys to navigate, Space to select, Enter to confirm',
            );

            $themeCodes = $themeCodesPrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        }

        return $this->processCheckThemes($themeCodes, $this->io);
    }

    /**
     * Process theme checking
     *
     * @param array $themeCodes
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @return int
     */
    private function processCheckThemes(
        array $themeCodes,
        SymfonyStyle $symfonyStyle
    ): int {
        $startTime = microtime(true);
        $results = [];
        $totalThemes = count($themeCodes);

        if (empty($themeCodes)) {
            $symfonyStyle->warning('No themes selected for checking.');
            return Command::SUCCESS;
        }

        $symfonyStyle->title(sprintf('Checking %d theme(s) for outdated dependencies', $totalThemes));

        // Process each theme
        foreach ($themeCodes as $index => $themeCode) {
            $currentTheme = $index + 1;
            // Show which theme is currently being checked
            $themeNameCyan = sprintf("<fg=cyan>%s</>", $themeCode);
            $spinner = new Spinner(sprintf("Analyzing %s (%d of %d) ...", $themeNameCyan, $currentTheme, $totalThemes));

            $themeResults = [];

            $spinner->spin(function() use ($themeCode, &$themeResults) {
                $themeResults = $this->checkTheme($themeCode);
                return true;
            });

            if (empty($themeResults)) {
                $symfonyStyle->writeln(sprintf("   Analyzing %s (%d of %d) ... <fg=yellow>no dependencies found</>", $themeNameCyan, $currentTheme, $totalThemes));
                continue;
            }

            $symfonyStyle->writeln(sprintf("   Analyzing %s (%d of %d) ... <fg=green>done</>", $themeNameCyan, $currentTheme, $totalThemes));
            $results[$themeCode] = $themeResults;
        }

        $this->displayCheckResults($symfonyStyle, $results, microtime(true) - $startTime);

        return Command::SUCCESS;
    }

    /**
     * Check a single theme for outdated dependencies
     *
     * @param string $themeCode
     * @return array
     */
    private function checkTheme(string $themeCode): array
    {
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            return ['error' => "Theme $themeCode is not installed."];
        }

        // Get the appropriate checker for this theme
        $checker = $this->checkerPool->getChecker($themePath);
        if ($checker === null) {
            return ['error' => "No suitable checker found for theme $themeCode."];
        }

        $results = [
            'path' => $themePath,
            'type' => $checker->getName(),
            'composer' => $checker->checkComposerDependencies($themePath),
            'npm' => $checker->checkNpmDependencies($themePath),
        ];

        return $results;
    }

    // Implementation moved to the Theme Checker services

    /**
     * Display check results
     *
     * @param SymfonyStyle $symfonyStyle
     * @param array $results
     * @param float $duration
     */
    private function displayCheckResults(SymfonyStyle $symfonyStyle, array $results, float $duration): void
    {
        $symfonyStyle->newLine();
        $symfonyStyle->success(sprintf(
            "🔍 Dependency check completed in %.2f seconds",
            $duration
        ));

        if (empty($results)) {
            $symfonyStyle->warning('No themes were checked successfully.');
            return;
        }

        $composerIssuesCount = 0;
        $npmIssuesCount = 0;

        foreach ($results as $themeCode => $themeResults) {
            $symfonyStyle->section(sprintf("Results for theme <fg=cyan>%s</>", $themeCode));

            if (isset($themeResults['error'])) {
                $symfonyStyle->error($themeResults['error']);
                continue;
            }

            // Display theme type if available
            if (isset($themeResults['type'])) {
                $symfonyStyle->writeln(sprintf("Theme type: <info>%s</info>", $themeResults['type']));
                $symfonyStyle->newLine();
            }

            $this->displayComposerResults($symfonyStyle, $themeResults, $composerIssuesCount);
            $this->displayNpmResults($symfonyStyle, $themeResults, $npmIssuesCount);
        }

        // Display summary
        $this->displaySummary($symfonyStyle, $composerIssuesCount, $npmIssuesCount, count($results));
    }

    /**
     * Display composer results
     *
     * @param SymfonyStyle $symfonyStyle
     * @param array $themeResults
     * @param int &$composerIssuesCount
     */
    private function displayComposerResults(SymfonyStyle $symfonyStyle, array $themeResults, int &$composerIssuesCount): void
    {
        // Display Composer outdated packages
        if (empty($themeResults['composer'])) {
            $symfonyStyle->writeln("✅ No outdated Composer packages found.");
            $symfonyStyle->newLine();
            return;
        }

        if (isset($themeResults['composer']['error'])) {
            $symfonyStyle->error($themeResults['composer']['error']);
            return;
        }

        if (isset($themeResults['composer']['warning'])) {
            $symfonyStyle->warning($themeResults['composer']['warning']);
            return;
        }

        // Check if we're using project root dependencies
        $projectRootInfo = '';

        if (isset($themeResults['composer']['_meta']) &&
            isset($themeResults['composer']['_meta']['using_project_root']) &&
            $themeResults['composer']['_meta']['using_project_root'] === true) {

            $projectRoot = $themeResults['composer']['_meta']['project_root'] ?? 'project root';
            $projectRootInfo = sprintf(" <fg=blue>[Using project dependencies from %s]</>", $projectRoot);

            // Remove meta information to prevent it from appearing in the results
            unset($themeResults['composer']['_meta']);
        }

        $symfonyStyle->writeln(sprintf("<fg=yellow>Outdated Composer packages:%s</>", $projectRootInfo));
        $table = new Table($symfonyStyle);
        $table->setHeaders(['Package', 'Current', 'Latest', 'Status']);

        foreach ($themeResults['composer'] as $package) {
            $status = $this->getStatusColor($package['latest-status'] ?? '');
            $table->addRow([
                $package['name'],
                $package['version'] ?? 'unknown',
                $package['latest'] ?? 'unknown',
                sprintf("<%s>%s</>", $status, $package['latest-status'] ?? 'unknown')
            ]);
            $composerIssuesCount++;
        }

        $table->render();
        $symfonyStyle->newLine();
    }

    /**
     * Display NPM results
     *
     * @param SymfonyStyle $symfonyStyle
     * @param array $themeResults
     * @param int &$npmIssuesCount
     */
    private function displayNpmResults(SymfonyStyle $symfonyStyle, array $themeResults, int &$npmIssuesCount): void
    {
        // Display NPM outdated packages
        if (empty($themeResults['npm'])) {
            $symfonyStyle->writeln("✅ No outdated NPM packages found.");
            $symfonyStyle->newLine();
            return;
        }

        if (isset($themeResults['npm']['error'])) {
            $symfonyStyle->error($themeResults['npm']['error']);
            return;
        }

        // Get path info for Hyvä themes
        $pathInfo = $this->getHyvaThemePathInfo($themeResults);

        $symfonyStyle->writeln(sprintf("<fg=yellow>Outdated NPM packages:%s</>", $pathInfo));
        $table = new Table($symfonyStyle);
        $table->setHeaders(['Package', 'Current', 'Wanted', 'Latest', 'Type']);

        foreach ($themeResults['npm'] as $packageName => $package) {
            $type = $this->getDependencyType($package);
            $table->addRow([
                $packageName,
                $package['current'] ?? 'unknown',
                $package['wanted'] ?? 'unknown',
                $package['latest'] ?? 'unknown',
                $type
            ]);
            $npmIssuesCount++;
        }

        $table->render();
        $symfonyStyle->newLine();
    }

    /**
     * Get color for status
     *
     * @param string $status
     * @return string
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'semver:patch' => 'fg=green',
            'semver:minor' => 'fg=yellow',
            'semver:major' => 'fg=red',
            default => 'fg=default',
        };
    }

    /**
     * Get dependency type with color
     *
     * @param array $package
     * @return string
     */
    private function getDependencyType(array $package): string
    {
        $type = $package['type'] ?? 'unknown';

        $color = match ($type) {
            'dependencies' => 'fg=white',
            'devDependencies' => 'fg=gray',
            'peerDependencies' => 'fg=cyan',
            'optionalDependencies' => 'fg=blue',
            default => 'fg=default',
        };

        return sprintf("<%s>%s</>", $color, $type);
    }

    /**
     * Display check summary
     *
     * @param SymfonyStyle $symfonyStyle
     * @param int $composerIssues
     * @param int $npmIssues
     * @param int $themeCount
     */
    private function displaySummary(
        SymfonyStyle $symfonyStyle,
        int $composerIssues,
        int $npmIssues,
        int $themeCount
    ): void {
        $symfonyStyle->section("📊 Summary");

        $totalIssues = $composerIssues + $npmIssues;

        if ($totalIssues === 0) {
            $symfonyStyle->success("All dependencies are up to date! No outdated packages found in any theme.");
            return;
        }

        $summary = [
            sprintf("Checked <fg=cyan>%d</> themes", $themeCount),
            sprintf("Found <fg=yellow>%d</> outdated packages:", $totalIssues),
            sprintf("  • <fg=blue>%d</> outdated Composer packages", $composerIssues),
            sprintf("  • <fg=blue>%d</> outdated NPM packages", $npmIssues),
        ];

        foreach ($summary as $line) {
            $symfonyStyle->writeln($line);
        }

        $symfonyStyle->newLine();

    }

    /**
     * Get path info string for themes with meta information
     *
     * @param array $themeResults
     * @return string
     */
    private function getHyvaThemePathInfo(array &$themeResults): string
    {
        // Check if this theme has meta information
        if (!isset($themeResults['npm']['_meta'])) {
            return '';
        }

        $themeName = $themeResults['npm']['_meta']['type'] ?? 'theme';
        $path = $themeResults['npm']['_meta']['path'] ?? '';

        if (empty($path)) {
            return '';
        }

        $pathInfo = sprintf(" <fg=blue>[%s, checked in %s]</>", $themeName, $path);

        // Remove meta information to prevent it from appearing in the results
        unset($themeResults['npm']['_meta']);

        return $pathInfo;
    }

    // Implementation moved to the Theme Checker services
}
