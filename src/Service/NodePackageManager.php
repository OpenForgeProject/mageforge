<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Shell;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service for managing Node.js package installation and updates
 */
class NodePackageManager
{
    /**
     * @param Shell $shell
     * @param FileDriver $fileDriver
     */
    public function __construct(
        private readonly Shell $shell,
        private readonly FileDriver $fileDriver,
    ) {
    }

    /**
     * Install node modules in the specified directory
     *
     * Uses npm ci if package-lock.json exists, otherwise falls back to npm install
     *
     * @param string $path
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    public function installNodeModules(string $path, SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            if ($this->fileDriver->isExists($path . '/package-lock.json')) {
                try {
                    $this->shell->execute('cd %s && npm ci --quiet', [$path]);
                } catch (\Exception $e) {
                    if ($isVerbose) {
                        $io->warning('npm ci failed, falling back to npm install...');
                    }
                    $this->shell->execute('cd %s && npm install --quiet', [$path]);
                }
            } else {
                if ($isVerbose) {
                    $io->warning('No package-lock.json found, running npm install...');
                }
                $this->shell->execute('cd %s && npm install --quiet', [$path]);
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

    /**
     * Check if node_modules is in sync with package-lock.json
     *
     * Verifies that installed packages match the lock file by checking:
     * 1. node_modules directory exists
     * 2. package-lock.json exists
     * 3. All packages are installed with correct versions (via npm ls)
     *
     * @param string $path
     * @return bool
     */
    public function isNodeModulesInSync(string $path): bool
    {
        if (!$this->fileDriver->isDirectory($path . '/node_modules')) {
            return false;
        }

        if (!$this->fileDriver->isExists($path . '/package-lock.json')) {
            return false;
        }

        try {
            $this->shell->execute('cd %s && npm ls --depth=0 --json > /dev/null 2>&1', [$path]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check for outdated npm packages and report them
     *
     * @param string $path
     * @param SymfonyStyle $io
     * @return void
     */
    public function checkOutdatedPackages(string $path, SymfonyStyle $io): void
    {
        try {
            $outdated = $this->shell->execute('cd %s && npm outdated --json', [$path]);
            if ($outdated) {
                $io->warning('Outdated packages found:');
                $io->writeln($outdated);
            }
        } catch (\Exception $e) {
            if ($io->isVerbose()) {
                $io->warning('Failed to check outdated packages: ' . $e->getMessage());
            }
        }
    }
}
