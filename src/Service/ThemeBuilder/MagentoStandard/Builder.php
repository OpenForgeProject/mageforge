<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\NodeSetupValidator;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'MagentoStandard';

    public function __construct(
        private readonly Shell $shell,
        private readonly File $fileDriver,
        private readonly StaticContentDeployer $staticContentDeployer,
        private readonly StaticContentCleaner $staticContentCleaner,
        private readonly CacheCleaner $cacheCleaner,
        private readonly SymlinkCleaner $symlinkCleaner,
        private readonly NodePackageManager $nodePackageManager,
        private readonly GruntTaskRunner $gruntTaskRunner,
        private readonly NodeSetupValidator $nodeSetupValidator
    ) {
    }

    public function detect(string $themePath): bool
    {
        // normalize path
        $themePath = rtrim($themePath, '/');

        // Check if this is a standard Magento theme by looking for theme.xml
        // and ensuring it's not a Hyva theme (no tailwind directory)
        return $this->fileDriver->isExists($themePath . '/theme.xml')
            && !$this->fileDriver->isExists($themePath . '/web/tailwind');
    }

    public function build(string $themeCode, string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        // Clean static content if in developer mode
        if (!$this->staticContentCleaner->cleanIfNeeded($themeCode, $io, $output, $isVerbose)) {
            return false;
        }

        // Check if this is a vendor theme (read-only, pre-built assets)
        if ($this->isVendorTheme($themePath)) {
            $io->warning('Vendor theme detected. Skipping Grunt steps.');
            $io->newLine(2);
        } elseif ($this->hasNodeSetup()) {
            if (!$this->processNodeSetup($themePath, $io, $output, $isVerbose)) {
                return false;
            }
        } else {
            if ($isVerbose) {
                $io->note('No Node.js/Grunt setup detected. Skipping Grunt steps.');
            }
        }

        // Deploy static content
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }

        // Clean cache using the dedicated service
        if (!$this->cacheCleaner->clean($io, $isVerbose)) {
            return false;
        }

        return true;
    }

    /**
     * Process Node.js and Grunt setup
     */
    private function processNodeSetup(
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool {
        $rootPath = '.';

        // Validate and restore Node.js setup files if needed
        if (!$this->nodeSetupValidator->validateAndRestore($rootPath, $io, $isVerbose)) {
            return false;
        }

        // Check if Node/Grunt setup exists
        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        // Clean symlinks in web/css/ directory before build
        if (!$this->symlinkCleaner->cleanSymlinks($themePath, $io, $isVerbose)) {
            return false;
        }

        // Run grunt tasks
        return $this->gruntTaskRunner->runTasks($io, $output, $isVerbose);
    }

    public function autoRepair(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        $rootPath = '.';

        // Check if node_modules is in sync with package-lock.json
        if (!$this->nodePackageManager->isNodeModulesInSync($rootPath)) {
            if ($isVerbose) {
                $io->warning('Node modules out of sync, missing, or no lock file found. Installing...');
            }
            if (!$this->nodePackageManager->installNodeModules($rootPath, $io, $isVerbose)) {
                return false;
            }
        }

        // Check for grunt
        if (!$this->installGruntIfMissing($io, $isVerbose)) {
            return false;
        }

        // Check for outdated packages
        if ($isVerbose) {
            $this->checkOutdatedPackages($io);
        }

        return true;
    }



    /**
     * Install Grunt if it's missing
     */
    private function installGruntIfMissing(SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            $this->shell->execute('which grunt');
            return true;
        } catch (\Exception $e) {
            if ($isVerbose) {
                $io->warning('Grunt not found globally. Installing grunt...');
            }

            try {
                $this->shell->execute('npm install -g grunt-cli --quiet');

                if ($isVerbose) {
                    $io->success('Grunt installed successfully.');
                }

                return true;
            } catch (\Exception $e) {
                $io->error('Failed to install grunt: ' . $e->getMessage());
                return false;
            }
        }
    }

    /**
     * Check for outdated packages and report them
     */
    private function checkOutdatedPackages(SymfonyStyle $io): void
    {
        try {
            $outdated = $this->shell->execute('npm outdated --json');
            if ($outdated) {
                $io->warning('Outdated packages found:');
                $io->writeln($outdated);
            }
        } catch (\Exception $e) {
            // Ignore errors from npm outdated as it returns non-zero when packages are outdated
        }
    }

    public function watch(string $themeCode, string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        // Vendor themes cannot be watched (read-only)
        if ($this->isVendorTheme($themePath)) {
            $io->error('Watch mode is not supported for vendor themes. Vendor themes are read-only and should have pre-built assets.');
            return false;
        }

        // Check if Node/Grunt setup is intentionally absent
        if (!$this->hasNodeSetup()) {
            $io->error('Watch mode requires Node.js/Grunt setup. No package.json, package-lock.json, node_modules, or grunt-config.json found.');
            return false;
        }

        // Clean static content if in developer mode
        if (!$this->staticContentCleaner->cleanIfNeeded($themeCode, $io, $output, $isVerbose)) {
            return false;
        }

        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        try {
            if ($isVerbose) {
                $io->text('Starting watch mode with verbose output...');
            } else {
                $io->text('Starting watch mode... (use -v for verbose output)');
            }

            $exitCode = 0;
            // phpcs:ignore Magento2.Security.InsecureFunction.Found -- passthru required for interactive watch mode
            passthru('node_modules/.bin/grunt watch', $exitCode);

            // Check if the command failed
            if ($exitCode !== 0) {
                $io->error(sprintf('Watch mode exited with error code: %d', $exitCode));
                return false;
            }
        } catch (\Exception $e) {
            $io->error('Failed to start watch mode: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return self::THEME_NAME;
    }

    /**
     * Check if Node.js/Grunt setup exists
     *
     * Returns true if at least one of the required files exists
     *
     * @return bool
     */
    private function hasNodeSetup(): bool
    {
        $rootPath = '.';

        return $this->fileDriver->isExists($rootPath . '/package.json')
            || $this->fileDriver->isExists($rootPath . '/package-lock.json')
            || $this->fileDriver->isExists($rootPath . '/gruntfile.js')
            || $this->fileDriver->isExists($rootPath . '/grunt-config.json');
    }

    /**
     * Check if theme is from vendor directory
     *
     * Vendor themes are installed via Composer and should not be modified.
     * They typically have pre-built assets and don't require compilation.
     *
     * @param string $themePath
     * @return bool
     */
    private function isVendorTheme(string $themePath): bool
    {
        return str_contains($themePath, '/vendor/');
    }
}
