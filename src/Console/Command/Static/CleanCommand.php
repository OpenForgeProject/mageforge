<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Static;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning Magento static files for specific themes
 */
class CleanCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('static', 'clean'))
            ->setDescription('Cleans var/view_preprocessed and pub/static folders for specific theme(s)')
            ->addArgument(
                'themeCodes',
                InputArgument::IS_ARRAY,
                'Theme codes to clean (format: Vendor/theme). If empty, will prompt for selection.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCodes = $input->getArgument('themeCodes');

        if (empty($themeCodes)) {
            $this->displayAvailableThemes();
            $this->io->warning('No theme specified. Please provide theme code(s) to clean.');
            $this->io->info('Usage: bin/magento mageforge:static:clean <theme-code> [<theme-code>...]');
            $this->io->info('Example: bin/magento mageforge:static:clean Magento/luma');
            return Cli::RETURN_SUCCESS;
        }

        return $this->processCleanThemes($themeCodes);
    }

    /**
     * Display available themes
     *
     * @return void
     */
    private function displayAvailableThemes(): void
    {
        $themes = $this->themeList->getAllThemes();

        if (empty($themes)) {
            $this->io->info('No themes found.');
            return;
        }

        $this->io->section('Available Themes:');
        $table = new Table($this->io);
        $table->setHeaders(['Theme Code', 'Title']);

        foreach ($themes as $theme) {
            $table->addRow([$theme->getCode(), $theme->getThemeTitle()]);
        }

        $table->render();
    }

    /**
     * Process cleaning themes
     *
     * @param array $themeCodes
     * @return int
     */
    private function processCleanThemes(array $themeCodes): int
    {
        $startTime = microtime(true);
        $successList = [];
        $failureList = [];
        $totalThemes = count($themeCodes);

        $this->io->title(sprintf('Cleaning static files for %d theme(s)', $totalThemes));

        foreach ($themeCodes as $themeCode) {
            $this->io->section(sprintf('Cleaning theme: %s', $themeCode));

            if (!$this->validateTheme($themeCode)) {
                $failureList[] = $themeCode;
                $this->io->error("Theme $themeCode is not installed.");
                continue;
            }

            $cleaned = $this->cleanThemeFiles($themeCode);

            if ($cleaned) {
                $successList[] = $themeCode;
                $this->io->success(sprintf('Successfully cleaned files for theme: %s', $themeCode));
            } else {
                $failureList[] = $themeCode;
            }
        }

        $this->displayCleanSummary($successList, $failureList, microtime(true) - $startTime);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Validate if theme exists
     *
     * @param string $themeCode
     * @return bool
     */
    private function validateTheme(string $themeCode): bool
    {
        $themes = $this->themeList->getAllThemes();

        foreach ($themes as $theme) {
            if ($theme->getCode() === $themeCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean theme files from var/view_preprocessed and pub/static
     *
     * @param string $themeCode
     * @return bool
     */
    private function cleanThemeFiles(string $themeCode): bool
    {
        $success = true;

        // Clean var/view_preprocessed
        $viewPreprocessedCleaned = $this->cleanViewPreprocessed($themeCode);
        if (!$viewPreprocessedCleaned) {
            $success = false;
        }

        // Clean pub/static
        $staticCleaned = $this->cleanPubStatic($themeCode);
        if (!$staticCleaned) {
            $success = false;
        }

        return $success;
    }

    /**
     * Clean var/view_preprocessed for theme
     *
     * @param string $themeCode
     * @return bool
     */
    private function cleanViewPreprocessed(string $themeCode): bool
    {
        try {
            $varDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            $viewPreprocessedPath = $varDir->getAbsolutePath('view_preprocessed');

            if (!is_dir($viewPreprocessedPath)) {
                $this->io->writeln('  ℹ var/view_preprocessed directory does not exist');
                return true;
            }

            $cleaned = $this->cleanThemeDirectories($viewPreprocessedPath, $themeCode);

            if ($cleaned > 0) {
                $this->io->writeln(sprintf('  ✓ Cleaned %d item(s) from var/view_preprocessed', $cleaned));
            } else {
                $this->io->writeln('  ℹ No files found in var/view_preprocessed');
            }

            return true;
        } catch (\Exception $e) {
            $this->io->error('Failed to clean var/view_preprocessed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean pub/static for theme
     *
     * @param string $themeCode
     * @return bool
     */
    private function cleanPubStatic(string $themeCode): bool
    {
        try {
            $staticDir = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);
            $staticPath = $staticDir->getAbsolutePath();

            if (!is_dir($staticPath)) {
                $this->io->writeln('  ℹ pub/static directory does not exist');
                return true;
            }

            $cleaned = $this->cleanThemeDirectories($staticPath, $themeCode);

            if ($cleaned > 0) {
                $this->io->writeln(sprintf('  ✓ Cleaned %d item(s) from pub/static', $cleaned));
            } else {
                $this->io->writeln('  ℹ No files found in pub/static');
            }

            return true;
        } catch (\Exception $e) {
            $this->io->error('Failed to clean pub/static: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean theme directories from a base path
     *
     * @param string $basePath
     * @param string $themeCode
     * @return int Number of items cleaned
     */
    private function cleanThemeDirectories(string $basePath, string $themeCode): int
    {
        $cleaned = 0;

        // Scan for areas (frontend, adminhtml, etc.)
        $areas = ['frontend'];
        foreach ($areas as $area) {
            $areaPath = $basePath . DIRECTORY_SEPARATOR . $area;
            if (!is_dir($areaPath)) {
                continue;
            }

            // Look for the specific theme directory
            $themePath = $areaPath . DIRECTORY_SEPARATOR . $themeCode;
            if (is_dir($themePath)) {
                if ($this->removeDirectory($themePath)) {
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return bool
     */
    private function removeDirectory(string $dir): bool
    {
        try {
            if (!is_dir($dir)) {
                return false;
            }

            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }

            return rmdir($dir);
        } catch (\Exception $e) {
            $this->io->error('Failed to remove directory: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Display clean summary
     *
     * @param array $successList
     * @param array $failureList
     * @param float $duration
     * @return void
     */
    private function displayCleanSummary(array $successList, array $failureList, float $duration): void
    {
        $this->io->newLine();
        $this->io->section(sprintf('Clean process completed in %.2f seconds', $duration));

        if (!empty($successList)) {
            $this->io->success('Successfully cleaned themes:');
            foreach ($successList as $themeCode) {
                $this->io->writeln(sprintf('  ✅ %s', $themeCode));
            }
        }

        if (!empty($failureList)) {
            $this->io->warning('Failed to clean themes:');
            foreach ($failureList as $themeCode) {
                $this->io->writeln(sprintf('  ❌ %s', $themeCode));
            }
        }

        if (empty($successList) && empty($failureList)) {
            $this->io->info('No themes were processed.');
        }
    }
}
