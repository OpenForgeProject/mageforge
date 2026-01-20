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
    public function __construct(
        private readonly Shell $shell,
        private readonly FileDriver $fileDriver
    ) {}

    /**
     * Install node modules in the specified directory
     *
     * Uses npm ci if package-lock.json exists, otherwise falls back to npm install
     */
    public function installNodeModules(string $path, SymfonyStyle $io, bool $isVerbose): bool
    {
        $currentDir = getcwd();
        chdir($path);

        try {
            if ($this->fileDriver->isExists($path . '/package-lock.json')) {
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
     * Check for outdated npm packages and report them
     *
     * Note: npm outdated returns non-zero exit code when packages are outdated,
     * so exceptions are caught and ignored
     */
    public function checkOutdatedPackages(string $path, SymfonyStyle $io): void
    {
        $currentDir = getcwd();
        chdir($path);

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
}
