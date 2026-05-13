<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\MultiSearchPrompt;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

/**
 * Command for checking npm dependencies of Magento themes
 */
class NpmCheckCommand extends AbstractCommand
{
    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param BuilderPool $builderPool
     * @param NodePackageManager $nodePackageManager
     * @param ThemeSuggester $themeSuggester
     * @param FileDriver $fileDriver
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly BuilderPool $builderPool,
        private readonly NodePackageManager $nodePackageManager,
        private readonly ThemeSuggester $themeSuggester,
        private readonly FileDriver $fileDriver,
    ) {
        parent::__construct();
    }

    /**
     * Configure command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName($this->getCommandName('theme', 'npm-check'))
            ->setDescription('Checks npm dependencies of Magento themes for outdated packages and vulnerabilities')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Theme codes to check (format: Vendor/theme, Vendor, ...)',
            )
            ->setAliases(['m:t:nc', 'frontend:npm-check']);
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string> $themeCodes */
        $themeCodes = $input->getArgument('themeCodes');

        if (!empty($themeCodes)) {
            $themeCodes = $this->resolveVendorThemes($themeCodes, $this->themeList);

            if (empty($themeCodes)) {
                return Command::SUCCESS;
            }
        }

        $isVerbose = $this->isVerbose($output);

        if (empty($themeCodes)) {
            $themes = $this->themeList->getAllThemes();
            $options = array_values(array_map(fn($theme) => $theme->getCode(), $themes));

            if (!$this->isInteractiveTerminal($output)) {
                $this->io->info('No theme specified. Usage: bin/magento mageforge:theme:npm-check <theme-code>');
                return Command::SUCCESS;
            }

            $this->setPromptEnvironment();

            $prompt = new MultiSearchPrompt(
                label: 'Select themes to check',
                options: fn(string $value) => empty($value)
                    ? $options
                    : array_values(array_filter($options, fn($option) => stripos((string) $option, $value) !== false)),
                placeholder: 'Type to search theme...',
                hint: 'Type to search, arrow keys to navigate, Space to toggle, Enter to confirm',
                required: false,
            );

            try {
                $themeCodes = $prompt->prompt();
                \Laravel\Prompts\Prompt::terminal()->restoreTty();
                $this->resetPromptEnvironment();

                if (empty($themeCodes)) {
                    $this->io->info('No themes selected.');
                    return Command::SUCCESS;
                }
            } catch (\Exception $e) {
                $this->resetPromptEnvironment();
                $this->io->error('Interactive mode failed: ' . $e->getMessage());
                return Command::SUCCESS;
            }
        }

        $checkedPaths = [];

        foreach ($themeCodes as $themeCode) {
            $this->processThemeNpmCheck($themeCode, $checkedPaths, $output, $isVerbose);
        }

        return Command::SUCCESS;
    }

    /**
     * Check npm dependencies for a single theme.
     *
     * @param string $themeCode
     * @param array<string> $checkedPaths Tracks already-checked npm paths for deduplication
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return void
     */
    private function processThemeNpmCheck(
        string $themeCode,
        array &$checkedPaths,
        OutputInterface $output,
        bool $isVerbose,
    ): void {
        $resolvedPath = $this->themePath->getPath($themeCode);

        if ($resolvedPath === null) {
            $this->io->warning(sprintf('Theme "%s" not found. Skipping.', $themeCode));
            return;
        }

        $npmPath = $this->getNpmPath($resolvedPath);

        if ($npmPath === null) {
            $this->io->warning(sprintf('No package-lock.json found for theme "%s". Skipping.', $themeCode));
            return;
        }

        // Deduplication: skip if this npm path was already processed
        // (relevant when multiple MagentoStandard themes share the Magento root npm)
        $canonicalPath = realpath($npmPath) ?? $npmPath;
        if (in_array($canonicalPath, $checkedPaths, true)) {
            $this->io->note(sprintf(
                'npm path "%s" was already checked (shared with another theme). Skipping "%s".',
                $npmPath,
                $themeCode,
            ));
            return;
        }
        $checkedPaths[] = $canonicalPath;

        $this->io->section(sprintf('npm dependencies: %s', $themeCode));

        if ($isVerbose) {
            $this->io->text(sprintf('npm path: %s', $npmPath));
            $this->io->newLine();
        }

        $isInteractive = $this->isInteractiveTerminal($output);

        $this->checkOutdated($npmPath, $output, $isVerbose, $isInteractive);

        $this->io->newLine();

        $this->checkAudit($npmPath, $output, $isVerbose, $isInteractive);
    }

    /**
     * Check for outdated packages and optionally run npm update --latest.
     *
     * @param string $npmPath
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @param bool $isInteractive
     * @return void
     */
    private function checkOutdated(
        string $npmPath,
        OutputInterface $output,
        bool $isVerbose,
        bool $isInteractive,
    ): void {
        if ($isVerbose) {
            $this->io->text('Checking for outdated packages...');
        }

        $outdated = $this->nodePackageManager->getOutdatedPackages($npmPath);

        if (empty($outdated)) {
            $this->io->success('All packages are up to date.');
            return;
        }

        $this->io->warning(sprintf('%d outdated package(s) found:', count($outdated)));

        $table = new Table($output);
        $table->setHeaders(['Package', 'Current', 'Wanted', 'Latest']);

        foreach ($outdated as $name => $info) {
            $table->addRow([
                $name,
                $info['current'] ?? '—',
                $info['wanted'] ?? '—',
                $info['latest'] ?? '—',
            ]);
        }

        $table->render();

        if (!$isInteractive) {
            return;
        }

        $this->setPromptEnvironment();

        try {
            $runUpdate = confirm('Run npm update --latest?', default: false);
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        } finally {
            $this->resetPromptEnvironment();
        }

        if ($runUpdate) {
            $this->nodePackageManager->runNpmUpdate($npmPath, $this->io, $isVerbose);
        }
    }

    /**
     * Check npm audit and optionally run npm audit fix.
     *
     * @param string $npmPath
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @param bool $isInteractive
     * @return void
     */
    private function checkAudit(
        string $npmPath,
        OutputInterface $output,
        bool $isVerbose,
        bool $isInteractive,
    ): void {
        if ($isVerbose) {
            $this->io->text('Running npm audit...');
        }

        $audit = $this->nodePackageManager->getAuditResults($npmPath);
        $total = $audit['total'] ?? 0;

        if ($total === 0) {
            $this->io->success('No vulnerabilities found.');
            return;
        }

        $this->io->warning(sprintf('%d vulnerability/vulnerabilities found:', $total));

        $table = new Table($output);
        $table->setHeaders(['Severity', 'Count']);

        foreach (['critical', 'high', 'moderate', 'low', 'info'] as $severity) {
            $count = $audit[$severity] ?? 0;
            if ($count > 0) {
                $table->addRow([ucfirst($severity), $count]);
            }
        }

        $table->render();

        if (!$isInteractive) {
            return;
        }

        $this->setPromptEnvironment();

        try {
            $runFix = confirm('Run npm audit fix?', default: false);
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        } finally {
            $this->resetPromptEnvironment();
        }

        if ($runFix) {
            $this->nodePackageManager->runAuditFix($npmPath, $this->io, $isVerbose);
        }
    }

    /**
     * Determine the npm path for the given theme path.
     *
     * Detection order:
     * 1. web/tailwind/ (Hyvä / TailwindCSS themes)
     * 2. Theme root (custom themes)
     * 3. Magento root "." (MagentoStandard themes, detected via BuilderPool)
     *
     * @param string $themePath Absolute filesystem path to the theme
     * @return string|null npm directory path, or null when no package-lock.json is found
     */
    private function getNpmPath(string $themePath): ?string
    {
        $tailwindPath = $themePath . '/web/tailwind';
        if ($this->fileDriver->isExists($tailwindPath . '/package-lock.json')) {
            return $tailwindPath;
        }

        if ($this->fileDriver->isExists($themePath . '/package-lock.json')) {
            return $themePath;
        }

        $builder = $this->builderPool->getBuilder($themePath);
        if ($builder !== null && $builder->getName() === 'MagentoStandard') {
            if ($this->fileDriver->isExists('./package-lock.json')) {
                return '.';
            }
        }

        return null;
    }
}
