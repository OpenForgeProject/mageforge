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
        $currentDir = getcwd();
        if ($currentDir === false) {
            $io->error('Cannot determine current directory');
            return false;
        }

        chdir($path);

        try {
            if ($this->fileDriver->isExists($path . '/package-lock.json')) {
                try {
                    $this->shell->execute('npm ci --quiet');
                } catch (\Exception $e) {
                    if ($isVerbose) {
                        $io->warning('npm ci failed, falling back to npm install...');
                    }
                    $this->shell->execute('npm install --quiet');
                }
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
            chdir($currentDir);
            $this->diagnoseAndReportNpmFailure($path, $io, $e);
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

        $currentDir = getcwd();
        if ($currentDir === false) {
            return false;
        }

        chdir($path);

        try {
            $this->shell->execute('npm ls --depth=0 --json > /dev/null 2>&1');
            chdir($currentDir);
            return true;
        } catch (\Exception $e) {
            chdir($currentDir);
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
        $currentDir = getcwd();
        if ($currentDir === false) {
            return;
        }

        chdir($path);

        try {
            $outdated = $this->shell->execute('npm outdated --json');
            if ($outdated) {
                $io->warning('Outdated packages found:');
                $io->writeln($outdated);
            }
        } catch (\Exception $e) {
            // npm outdated returns non-zero exit code when packages are outdated
        }

        chdir($currentDir);
    }

    /**
     * Diagnose npm installation failure and provide specific error messages
     *
     * @param string $path
     * @param SymfonyStyle $io
     * @param \Exception $exception
     * @return void
     */
    private function diagnoseAndReportNpmFailure(string $path, SymfonyStyle $io, \Exception $exception): void
    {
        $io->error('Failed to install node modules.');
        $io->newLine();

        // Check 1: npm availability
        if (!$this->isCommandAvailable('npm')) {
            $io->error('npm is not installed or not available in PATH.');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  Install Node.js and npm: https://nodejs.org/');
            return;
        }

        // Check 2: node availability
        if (!$this->isCommandAvailable('node')) {
            $io->error('Node.js is not installed or not available in PATH.');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  Install Node.js: https://nodejs.org/');
            return;
        }

        // Check 3: package.json validity
        if (!$this->isPackageJsonValid($path)) {
            $io->error('package.json is missing or contains invalid JSON.');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  Verify package.json exists at: ' . $path . '/package.json');
            return;
        }

        // Check 4: package-lock.json corruption
        if ($this->fileDriver->isExists($path . '/package-lock.json')) {
            if (!$this->isPackageLockValid($path)) {
                $io->error('package-lock.json is corrupted or invalid.');
                $io->writeln('<fg=yellow>Fix:</>');
                $io->writeln('  rm -rf node_modules package-lock.json');
                $io->writeln('  npm install');
                return;
            }
        }

        // Check 5: Permission issues
        if ($this->hasPermissionIssues($path)) {
            $io->error('Permission denied when accessing node_modules directory.');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  sudo chown -R $(whoami) ' . $path . '/node_modules');
            $io->writeln('  Or delete and reinstall: rm -rf node_modules && npm install');
            return;
        }

        // Check 6: Disk space
        if (!$this->hasSufficientDiskSpace($path)) {
            $io->error('Insufficient disk space to install node modules.');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  Free up disk space and try again.');
            return;
        }

        // Check 7: npm cache corruption
        $errorMessage = $exception->getMessage();
        if (str_contains($errorMessage, 'EINTEGRITY') || str_contains($errorMessage, 'sha')) {
            $io->error('npm cache is corrupted (integrity checksum mismatch).');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  npm cache clean --force');
            $io->writeln('  rm -rf node_modules package-lock.json');
            $io->writeln('  npm install');
            return;
        }

        // Check 8: Network issues
        if (str_contains($errorMessage, 'ENOTFOUND') || str_contains($errorMessage, 'ETIMEDOUT')) {
            $io->error('Network error: Cannot reach npm registry.');
            $io->writeln('<fg=yellow>Fix:</>');
            $io->writeln('  Check your internet connection and try again.');
            $io->writeln('  Or configure a different registry: npm config set registry https://registry.npmjs.org/');
            return;
        }

        // Fallback: Show actual npm error
        $io->error('npm install failed with an unknown error.');
        $io->writeln('<fg=yellow>Error details:</>');
        $io->writeln('  ' . $exception->getMessage());
        $io->newLine();
        $io->writeln('<fg=yellow>Suggested fix:</>');
        $io->writeln('  1. npm cache clean --force');
        $io->writeln('  2. rm -rf node_modules package-lock.json');
        $io->writeln('  3. npm install');
    }

    /**
     * Check if a command is available
     *
     * @param string $command
     * @return bool
     */
    private function isCommandAvailable(string $command): bool
    {
        try {
            $this->shell->execute('which ' . $command . ' > /dev/null 2>&1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if package.json is valid
     *
     * @param string $path
     * @return bool
     */
    private function isPackageJsonValid(string $path): bool
    {
        $packageJsonPath = $path . '/package.json';
        if (!$this->fileDriver->isExists($packageJsonPath)) {
            return false;
        }

        try {
            $content = $this->fileDriver->fileGetContents($packageJsonPath);
            json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if package-lock.json is valid
     *
     * @param string $path
     * @return bool
     */
    private function isPackageLockValid(string $path): bool
    {
        $lockPath = $path . '/package-lock.json';
        if (!$this->fileDriver->isExists($lockPath)) {
            return true; // If it doesn't exist, it's not invalid
        }

        try {
            $content = $this->fileDriver->fileGetContents($lockPath);
            json_decode($content, true);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check for permission issues in node_modules
     *
     * @param string $path
     * @return bool
     */
    private function hasPermissionIssues(string $path): bool
    {
        $nodeModulesPath = $path . '/node_modules';
        if (!$this->fileDriver->isDirectory($nodeModulesPath)) {
            return false; // Directory doesn't exist yet, so no permission issues
        }

        try {
            // Try to write a test file
            $testFile = $nodeModulesPath . '/.write-test-' . time();
            $this->fileDriver->filePutContents($testFile, 'test');
            $this->fileDriver->deleteFile($testFile);
            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Check if there is sufficient disk space
     *
     * @param string $path
     * @return bool
     */
    private function hasSufficientDiskSpace(string $path): bool
    {
        try {
            $freeSpace = disk_free_space($path);
            // Require at least 100MB free space
            return $freeSpace !== false && $freeSpace > 100 * 1024 * 1024;
        } catch (\Exception $e) {
            return true; // If we can't check, assume it's fine
        }
    }
}
