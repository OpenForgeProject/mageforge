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

    /**
     * Return outdated packages as a parsed array.
     *
     * Uses "|| true" to suppress the non-zero exit code npm emits when packages are outdated.
     *
     * @param string $path
     * @return array<string, mixed>
     */
    public function getOutdatedPackages(string $path): array
    {
        try {
            $output = $this->shell->execute('cd %s && npm outdated --json || true', [$path]);
            if (trim($output) === '' || trim($output) === '{}') {
                return [];
            }
            $data = json_decode($output, true);
            return is_array($data) ? $data : [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Run "npm update --latest" in the given directory.
     *
     * @param string $path
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    public function runNpmUpdate(string $path, SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            $this->shell->execute('cd %s && npm update --latest', [$path]);
            if ($isVerbose) {
                $io->success('npm update --latest completed successfully.');
            }
            return true;
        } catch (\Exception $e) {
            $io->error('npm update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return npm audit vulnerability counts as a flat array.
     *
     * Keys: total, critical, high, moderate, low, info.
     * Uses "|| true" to suppress the non-zero exit code npm emits when vulnerabilities exist.
     *
     * @param string $path
     * @return array<string, mixed>
     */
    public function getAuditResults(string $path): array
    {
        try {
            $output = $this->shell->execute('cd %s && npm audit --json || true', [$path]);
            if (trim($output) === '') {
                return [];
            }
            $data = json_decode($output, true);
            if (!is_array($data)) {
                return [];
            }
            $metadata = $data['metadata'] ?? null;
            if (!is_array($metadata)) {
                return [];
            }
            $vulnerabilities = $metadata['vulnerabilities'] ?? null;
            return is_array($vulnerabilities) ? $vulnerabilities : [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Run "npm audit fix" in the given directory.
     *
     * @param string $path
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    public function runAuditFix(string $path, SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            $this->shell->execute('cd %s && npm audit fix', [$path]);
            if ($isVerbose) {
                $io->success('npm audit fix completed successfully.');
            }
            return true;
        } catch (\Exception $e) {
            $io->error('npm audit fix failed: ' . $e->getMessage());
            return false;
        }
    }
}
