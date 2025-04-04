<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Shell;
use OpenForgeProject\MageForge\Service\CacheCleaner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
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
        private readonly CacheCleaner $cacheCleaner
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

    public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        // Run grunt tasks
        try {
            if ($isVerbose) {
                $io->text('Running grunt clean...');
            }
            $this->shell->execute('node_modules/.bin/grunt clean --quiet');

            if ($isVerbose) {
                $io->text('Running grunt less...');
            }
            $this->shell->execute('node_modules/.bin/grunt less --quiet');

            if ($isVerbose) {
                $io->success('Grunt tasks completed successfully.');
            }
        } catch (\Exception $e) {
            $io->error('Failed to run grunt tasks: ' . $e->getMessage());
            return false;
        }

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
        // Check for node_modules in root
        if (!$this->installNodeModulesIfMissing($io, $isVerbose)) {
            return false;
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
     * Install Node modules if they're missing
     */
    private function installNodeModulesIfMissing(SymfonyStyle $io, bool $isVerbose): bool
    {
        if (!$this->fileDriver->isDirectory('node_modules')) {
            if ($isVerbose) {
                $io->warning('Node modules not found in root directory. Running npm ci...');
            }

            try {
                if ($this->fileDriver->isExists('package-lock.json')) {
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

                return true;
            } catch (\Exception $e) {
                $io->error('Failed to install node modules: ' . $e->getMessage());
                return false;
            }
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

    public function watch(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        try {
            exec('node_modules/.bin/grunt watch');
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
}
