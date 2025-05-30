<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\TailwindCSS;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'TailwindCSS';

    public function __construct(
        private readonly Shell $shell,
        private readonly File $fileDriver,
        private readonly StaticContentDeployer $staticContentDeployer,
        private readonly CacheCleaner $cacheCleaner
    ) {
    }

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
            if (!str_contains($themeXmlContent, 'Hyva')) {
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

    public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        // Build Hyva theme
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';
        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $io->error("Tailwind directory not found in: $tailwindPath");
            return false;
        }

        // Change to tailwind directory and run build
        $currentDir = getcwd();
        chdir($tailwindPath);

        try {
            if ($isVerbose) {
                $io->text('Running npm build...');
            }
            $this->shell->execute('npm run build --quiet');
            if ($isVerbose) {
                $io->success('Custom TailwindCSS theme build completed successfully.');
            }
        } catch (\Exception $e) {
            $io->error('Failed to build custom TailwindCSS theme: ' . $e->getMessage());
            chdir($currentDir);
            return false;
        }

        chdir($currentDir);

        // Deploy static content
        $themeCode = basename($themePath);
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }

        // Clean cache using the dedicated service
        if (!$this->cacheCleaner->clean($io, $isVerbose)) {
            return false;
        }

        return true;
    }

    public function autoRepair(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';

        // Check for node_modules directory
        if (!$this->fileDriver->isDirectory($tailwindPath . '/node_modules')) {
            if (!$this->installNodeModules($tailwindPath, $io, $isVerbose)) {
                return false;
            }
        }

        // Check for outdated packages
        if ($isVerbose) {
            $this->checkOutdatedPackages($tailwindPath, $io);
        }

        return true;
    }

    /**
     * Install Node modules in the specified path
     */
    private function installNodeModules(string $tailwindPath, SymfonyStyle $io, bool $isVerbose): bool
    {
        if ($isVerbose) {
            $io->warning('Node modules not found in tailwind directory. Installing npm dependencies ...');
        }

        $currentDir = getcwd();
        chdir($tailwindPath);

        try {
            if ($this->fileDriver->isExists($tailwindPath . '/package-lock.json')) {
                $this->shell->execute('npm ci --quiet');
            } else {
                if ($isVerbose) {
                    $io->warning('No package-lock.json found, running npm install...');
                }
                $this->shell->execute('npm install --quiet');
            }
            if ($isVerbose) {
                $io->success('Node modules installed successfully.');
            }
            chdir($currentDir);
            return true;
        } catch (\Exception $e) {
            $io->error('Failed to install node modules: ' . $e->getMessage());
            chdir($currentDir);
            return false;
        }
    }

    /**
     * Check for outdated packages and report them
     */
    private function checkOutdatedPackages(string $tailwindPath, SymfonyStyle $io): void
    {
        $currentDir = getcwd();
        chdir($tailwindPath);
        try {
            $outdated = $this->shell->execute('npm outdated --json');
            if ($outdated) {
                $io->warning('Outdated packages found:');
                $io->writeln($outdated);
            }
        } catch (\Exception $e) {
            // Ignore errors from npm outdated as it returns non-zero when packages are outdated
        }
        chdir($currentDir);
    }

    public function getName(): string
    {
        return self::THEME_NAME;
    }

    public function watch(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
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
            chdir($tailwindPath);
            passthru('npm run watch');
        } catch (\Exception $e) {
            $io->error('Failed to start watch mode: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
