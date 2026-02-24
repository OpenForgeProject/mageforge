<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\TailwindCSS;

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
use Symfony\Component\Process\Process;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'TailwindCSS';

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
     * Detect whether the theme is a TailwindCSS theme (non-Hyva).
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

        // Check for theme.xml file in theme folder and ensure it's not a Hyva theme
        if ($this->fileDriver->isExists($themePath . '/theme.xml')) {
            $themeXmlContent = $this->fileDriver->fileGetContents($themePath . '/theme.xml');
            if (stripos($themeXmlContent, 'hyva') === false) {
                return true;
            }
        }

        // Check for composer.json file in theme folder and ensure it's not a Hyva theme
        if ($this->fileDriver->isExists($themePath . '/composer.json')) {
            $composerContent = $this->fileDriver->fileGetContents($themePath . '/composer.json');
            $composerJson = json_decode($composerContent, true);
            if (!isset($composerJson['name']) && str_contains($composerJson['name'], 'hyva')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build TailwindCSS theme assets.
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
    ): bool {
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

        // Build Hyva theme
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';
        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $io->error("Tailwind directory not found in: $tailwindPath");
            return false;
        }

        try {
            if ($isVerbose) {
                $io->text('Running npm build...');
            }
            // Use --quiet only in non-verbose mode to suppress routine output
            $buildCommand = $isVerbose ? 'npm run build' : 'npm run build --quiet';
            $this->shell->execute('cd %s && ' . $buildCommand, [$tailwindPath]);
            if ($isVerbose) {
                $io->success('Custom TailwindCSS theme build completed successfully.');
            }
        } catch (\Exception $e) {
            $io->error('Failed to build custom TailwindCSS theme: ' . $e->getMessage());
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
        if (!$this->cacheCleaner->clean($io, $isVerbose)) {
            return false;
        }

        return true;
    }

    /**
     * Validate and repair Node dependencies for the theme.
     *
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function autoRepair(
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool {
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';

        // Check if node_modules is in sync with package-lock.json
        if (!$this->nodePackageManager->isNodeModulesInSync($tailwindPath)) {
            if ($isVerbose) {
                $io->warning('Node modules out of sync or missing. Installing npm dependencies...');
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
     * Get the builder name.
     *
     * @return string
     */
    public function getName(): string
    {
        return self::THEME_NAME;
    }

    /**
     * Run watch mode for TailwindCSS theme assets.
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
    ): bool {
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

        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';
        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $io->error("Tailwind directory not found in: $tailwindPath");
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

            $process = new Process(['npm', 'run', 'watch'], $tailwindPath);
            $process->setTimeout(null);

            if (Process::isTtySupported() && $output->isDecorated()) {
                try {
                    $process->setTty(true);
                } catch (\RuntimeException $exception) {
                    if ($isVerbose) {
                        $io->warning(
                            'TTY mode is not supported in this environment; ' .
                            'running watch without TTY.'
                        );
                    }
                }
            }

            $exitCode = $process->run(function ($type, $buffer) use ($output): void {
                $output->write($buffer);
            });

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
}
