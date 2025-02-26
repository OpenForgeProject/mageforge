<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\HyvaThemes;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'HyvaThemes';

    public function __construct(
        private readonly Shell $shell,
        private readonly File $fileDriver,
        private readonly StaticContentDeployer $staticContentDeployer
    ) {
    }

    public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        // Build Hyva theme
        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';

        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $io->error("Tailwind directory not found in: $tailwindPath");
            return false;
        }

        // Change to tailwind directory
        $currentDir = getcwd();
        chdir($tailwindPath);

        if ($isVerbose) {
            $io->section("Building Hyvä theme in: $tailwindPath");
        }

        // Remove node_modules if exists
        if ($this->fileDriver->isDirectory('node_modules')) {
            if ($isVerbose) {
                $io->text('Removing node_modules directory...');
            }
            $this->fileDriver->deleteDirectory('node_modules');
        }

        // Run npm ci
        if ($isVerbose) {
            $io->text('Running npm ci...');
        }
        $this->shell->execute('npm ci --quiet');

        // Run npm run build
        if ($isVerbose) {
            $io->text('Running npm run build...');
        }
        $this->shell->execute('npm run build --quiet');

        // Change back to original directory
        chdir($currentDir);

        if ($isVerbose) {
            $io->success('Hyvä theme build completed successfully.');
        }

        // Deploy static content for the theme
        $themeCode = basename($themePath);
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }

        if ($isVerbose) {
            $io->success("Hyvä theme has been successfully built.");
        }

        return true;
    }

    public function detect(string $themePath): bool
    {
        // normalize path
        $themePath = rtrim($themePath, '/');

        // First check for tailwind directory in theme folder
        if (!file_exists(filename: $themePath . '/web/tailwind')) {
            return false;
        }

        // Then check composer.json for Hyva module dependency
        if (file_exists($themePath . '/composer.json')) {
            $composerContent = file_get_contents($themePath . '/composer.json');
            if ($composerContent) {
                $composerJson = json_decode($composerContent, true);
                if (isset($composerJson['name']) && str_contains($composerJson['name'], 'hyva')) {
                    return true;
                }
            }
        }

        // check theme.xml for Hyva theme declaration
        if (file_exists($themePath . '/theme.xml')) {
            $themeXmlContent = file_get_contents($themePath . '/theme.xml');
            if ($themeXmlContent && str_contains($themeXmlContent, 'Hyva')) {
                return true;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return self::THEME_NAME;
    }
}
