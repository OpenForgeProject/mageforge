<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\HyvaThemes;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\NodePackageManager;
use OpenForgeProject\MageForge\Service\StaticContentCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\SymlinkCleaner;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'HyvaThemes';

    /**
     * @param Shell $shell
     * @param File $fileDriver
     * @param StaticContentDeployer $staticContentDeployer
     * @param StaticContentCleaner $staticContentCleaner
     * @param CacheCleaner $cacheCleaner
     * @param SymlinkCleaner $symlinkCleaner
     * @param NodePackageManager $nodePackageManager
     */
    public function __construct(
        private readonly Shell $shell,
        private readonly File $fileDriver,
        private readonly StaticContentDeployer $staticContentDeployer,
        private readonly StaticContentCleaner $staticContentCleaner,
        private readonly CacheCleaner $cacheCleaner,
        private readonly SymlinkCleaner $symlinkCleaner,
        private readonly NodePackageManager $nodePackageManager
    ) {
    }

    /**
     * Detect whether the theme is a Hyva theme.
     *
     * @param string $themePath
     * @return bool
     */
    public function detect(string $themePath): bool
    {
        // normalize path
        $themePath = rtrim($themePath, '/');

        // First check for tailwind directory in theme folder
        if (!$this->fileDriver->isExists($themePath . '/web/tailwind')) {
            return false;
        }

        // Check theme.xml for Hyva theme declaration
        if ($this->fileDriver->isExists($themePath . '/theme.xml')) {
            $themeXmlContent = $this->fileDriver->fileGetContents($themePath . '/theme.xml');
            if (stripos($themeXmlContent, 'hyva') !== false) {
                return true;
            }
        }

        // Check composer.json for Hyva module dependency
        if ($this->fileDriver->isExists($themePath . '/composer.json')) {
            $composerContent = $this->fileDriver->fileGetContents($themePath . '/composer.json');
            $composerJson = json_decode($composerContent, true);
            if (isset($composerJson['name']) && str_contains($composerJson['name'], 'hyva')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build Hyva theme assets.
     *
     * @param string $themeCode
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function build(
        string $themeCode,
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        // Clean static content if in developer mode
        if (!$this->staticContentCleaner->cleanIfNeeded(
            $themeCode,
            $io,
            $output,
            $isVerbose
        )) {
            return false;
        }

        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        // Clean symlinks in web/css/ directory before build
        if (!$this->symlinkCleaner->cleanSymlinks($themePath, $io, $isVerbose)) {
            return false;
        }

        if (!$this->generateHyvaConfig($io, $isVerbose)) {
            return false;
        }

        if (!$this->buildTheme($themePath, $io, $isVerbose)) {
            return false;
        }

        // Deploy static content
        if (!$this->staticContentDeployer->deploy(
            $themeCode,
            $io,
            $output,
            $isVerbose
        )) {
            return false;
        }

        // Clean cache using the dedicated service
        return $this->cacheCleaner->clean($io, $isVerbose);
    }

    /**
     * Generate Hyva configuration
     *
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    private function generateHyvaConfig(SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            if ($isVerbose) {
                $io->text('Generating Hyvä configuration...');
            }
            $this->shell->execute('bin/magento hyva:config:generate');
            if ($isVerbose) {
                $io->success('Hyvä configuration generated successfully.');
            }
            return true;
        } catch (\Exception $e) {
            $io->error('Failed to generate Hyvä configuration: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build the Hyva theme
     *
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    private function buildTheme(string $themePath, SymfonyStyle $io, bool $isVerbose): bool
    {
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';
        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $io->error("Tailwind directory not found in: $tailwindPath");
            return false;
        }

        // Change to tailwind directory and run build
        $currentDir = getcwd();
        if ($currentDir === false) {
            $io->error('Cannot determine current directory');
            return false;
        }
        chdir($tailwindPath);

        try {
            if ($isVerbose) {
                $io->text('Running npm build...');
            }
            // Use --quiet only in non-verbose mode to suppress routine output
            $buildCommand = $isVerbose ? 'npm run build' : 'npm run build --quiet';
            $this->shell->execute($buildCommand);
            if ($isVerbose) {
                $io->success('Hyvä theme build completed successfully.');
            }
            chdir($currentDir);
            return true;
        } catch (\Exception $e) {
            $io->error('Failed to build Hyvä theme: ' . $e->getMessage());
            chdir($currentDir);
            return false;
        }
    }

    /**
     * Validate and repair Node dependencies for Hyva theme.
     *
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function autoRepair(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';

        // Check if node_modules is in sync with package-lock.json
        if (!$this->nodePackageManager->isNodeModulesInSync($tailwindPath)) {
            if ($isVerbose) {
                $io->warning('Node modules out of sync or missing. Installing dependencies...');
            }
            if (!$this->nodePackageManager->installNodeModules(
                $tailwindPath,
                $io,
                $isVerbose
            )) {
                return false;
            }
        }

        // Check for outdated packages
        if ($isVerbose) {
            $this->nodePackageManager->checkOutdatedPackages($tailwindPath, $io);
        }

        return true;
    }

    /**
     * Run watch mode for Hyva theme assets.
     *
     * @param string $themeCode
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function watch(
        string $themeCode,
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        // Clean static content if in developer mode
        if (!$this->staticContentCleaner->cleanIfNeeded(
            $themeCode,
            $io,
            $output,
            $isVerbose
        )) {
            return false;
        }

        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';
        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $io->error("Tailwind directory not found in: $tailwindPath");
            return false;
        }

        try {
            if ($isVerbose) {
                $io->text('Starting watch mode with verbose output...');
            } else {
                $io->text('Starting watch mode... (use -v for verbose output)');
            }

            chdir($tailwindPath);
            $exitCode = 0;
            // phpcs:ignore Magento2.Security.InsecureFunction.Found -- passthru required for interactive watch mode
            passthru('npm run watch', $exitCode);

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

    /**
     * Get the builder name.
     *
     * @return string
     */
    public function getName(): string
    {
        return self::THEME_NAME;
    }
}
