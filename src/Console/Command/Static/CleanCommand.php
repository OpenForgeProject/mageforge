<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Static;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for cleaning static files and preprocessed view files for specific themes
 */
class CleanCommand extends AbstractCommand
{
    /**
     * @param Filesystem $filesystem
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('static', 'clean'))
            ->setDescription('Clean var/view_preprocessed, pub/static, var/page_cache, var/tmp and generated directories for specific theme')
            ->addArgument(
                'themename',
                InputArgument::OPTIONAL,
                'Theme code to clean (format: Vendor/theme)'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeName = $input->getArgument('themename');

        // If no theme specified, show available themes
        if (empty($themeName)) {
            $this->io->warning('No theme specified. Available themes:');
            $themes = $this->themeList->getAllThemes();
            
            if (empty($themes)) {
                $this->io->info('No themes found.');
                return Cli::RETURN_SUCCESS;
            }

            foreach ($themes as $theme) {
                $this->io->writeln(sprintf('  - <fg=cyan>%s</> (%s)', $theme->getCode(), $theme->getThemeTitle()));
            }

            $this->io->newLine();
            $this->io->info('Usage: bin/magento mageforge:static:clean <theme-code>');
            $this->io->info('Example: bin/magento mageforge:static:clean Magento/luma');
            
            return Cli::RETURN_SUCCESS;
        }

        // Validate theme exists
        $themePath = $this->themePath->getPath($themeName);
        if ($themePath === null) {
            $this->io->error(sprintf("Theme '%s' not found.", $themeName));
            return Cli::RETURN_FAILURE;
        }

        $this->io->section(sprintf("Cleaning static files for theme: %s", $themeName));

        $cleaned = 0;

        // Clean var/view_preprocessed
        $cleaned += $this->cleanViewPreprocessed($themeName);

        // Clean pub/static
        $cleaned += $this->cleanPubStatic($themeName);

        // Clean var/page_cache
        $cleaned += $this->cleanPageCache();

        // Clean var/tmp
        $cleaned += $this->cleanVarTmp();

        // Clean generated
        $cleaned += $this->cleanGenerated();

        if ($cleaned > 0) {
            $this->io->success(sprintf(
                "Successfully cleaned %d director%s for theme '%s'",
                $cleaned,
                $cleaned === 1 ? 'y' : 'ies',
                $themeName
            ));
        } else {
            $this->io->info(sprintf("No files to clean for theme '%s'", $themeName));
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Clean var/view_preprocessed directory for the theme
     *
     * @param string $themeName
     * @return int Number of directories cleaned
     */
    private function cleanViewPreprocessed(string $themeName): int
    {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        
        // Extract vendor and theme parts
        $themeParts = $this->parseThemeName($themeName);
        if ($themeParts === null) {
            return 0;
        }

        [$vendor, $theme] = $themeParts;
        
        // Check if view_preprocessed directory exists
        if (!$varDirectory->isDirectory('view_preprocessed')) {
            return 0;
        }

        // Pattern: view_preprocessed/css/frontend/Vendor/theme
        // and view_preprocessed/source/frontend/Vendor/theme
        $pathsToClean = [
            sprintf('view_preprocessed/css/frontend/%s/%s', $vendor, $theme),
            sprintf('view_preprocessed/source/frontend/%s/%s', $vendor, $theme),
        ];

        foreach ($pathsToClean as $path) {
            if ($varDirectory->isDirectory($path)) {
                try {
                    $varDirectory->delete($path);
                    $this->io->writeln(sprintf('  <fg=green>✓</> Cleaned: var/%s', $path));
                    $cleaned++;
                } catch (\Exception $e) {
                    $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: var/%s - %s', $path, $e->getMessage()));
                }
            }
        }

        return $cleaned;
    }

    /**
     * Clean pub/static directory for the theme
     *
     * @param string $themeName
     * @return int Number of directories cleaned
     */
    private function cleanPubStatic(string $themeName): int
    {
        $cleaned = 0;
        $staticDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);
        
        // Extract vendor and theme parts
        $themeParts = $this->parseThemeName($themeName);
        if ($themeParts === null) {
            return 0;
        }

        [$vendor, $theme] = $themeParts;

        // Check if frontend directory exists in pub/static
        if (!$staticDirectory->isDirectory('frontend')) {
            return 0;
        }

        // Pattern: frontend/Vendor/theme
        $pathToClean = sprintf('frontend/%s/%s', $vendor, $theme);

        if ($staticDirectory->isDirectory($pathToClean)) {
            try {
                $staticDirectory->delete($pathToClean);
                $this->io->writeln(sprintf('  <fg=green>✓</> Cleaned: pub/static/%s', $pathToClean));
                $cleaned++;
            } catch (\Exception $e) {
                $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: pub/static/%s - %s', $pathToClean, $e->getMessage()));
            }
        }

        return $cleaned;
    }

    /**
     * Clean var/page_cache directory
     *
     * @return int Number of directories cleaned
     */
    private function cleanPageCache(): int
    {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if ($varDirectory->isDirectory('page_cache')) {
            try {
                $varDirectory->delete('page_cache');
                $this->io->writeln('  <fg=green>✓</> Cleaned: var/page_cache');
                $cleaned++;
            } catch (\Exception $e) {
                $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: var/page_cache - %s', $e->getMessage()));
            }
        }

        return $cleaned;
    }

    /**
     * Clean var/tmp directory
     *
     * @return int Number of directories cleaned
     */
    private function cleanVarTmp(): int
    {
        $cleaned = 0;
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if ($varDirectory->isDirectory('tmp')) {
            try {
                $varDirectory->delete('tmp');
                $this->io->writeln('  <fg=green>✓</> Cleaned: var/tmp');
                $cleaned++;
            } catch (\Exception $e) {
                $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: var/tmp - %s', $e->getMessage()));
            }
        }

        return $cleaned;
    }

    /**
     * Clean generated directory
     *
     * @return int Number of directories cleaned
     */
    private function cleanGenerated(): int
    {
        $cleaned = 0;
        $generatedDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::GENERATED);

        try {
            // Get all items in generated directory
            $items = $generatedDirectory->read();
            foreach ($items as $item) {
                // Skip .htaccess file
                if ($item === '.htaccess') {
                    continue;
                }
                
                try {
                    $generatedDirectory->delete($item);
                } catch (\Exception $e) {
                    $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: generated/%s - %s', $item, $e->getMessage()));
                }
            }
            
            if (!empty($items)) {
                $this->io->writeln('  <fg=green>✓</> Cleaned: generated/*');
                $cleaned++;
            }
        } catch (\Exception $e) {
            $this->io->writeln(sprintf('  <fg=red>✗</> Failed to clean: generated - %s', $e->getMessage()));
        }

        return $cleaned;
    }

    /**
     * Parse theme name into vendor and theme parts
     *
     * @param string $themeName
     * @return array|null Array with [vendor, theme] or null if invalid format
     */
    private function parseThemeName(string $themeName): ?array
    {
        $themeParts = explode('/', $themeName);
        if (count($themeParts) !== 2) {
            return null;
        }

        return $themeParts;
    }
}
