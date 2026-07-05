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
     * Map of npm dependency types to their install save flags
     */
    private const SAVE_FLAGS = [
        'dependencies' => '--save',
        'devDependencies' => '--save-dev',
        'optionalDependencies' => '--save-optional',
        'peerDependencies' => '--save-peer',
    ];

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
     * Get a normalized list of outdated npm packages for the given directory
     *
     * The npm process exits with a non-zero code when outdated packages exist, so the
     * exit code is neutralized with a trailing "true" and an empty output is treated as
     * "everything up to date". A "|| true" must not be used here because the Magento
     * shell command renderer converts "||" into a pipe that swallows the npm output.
     *
     * @param string $path
     * @return array<int,array{name:string,current:string,wanted:string,latest:string,type:string}>
     */
    public function getOutdatedPackages(string $path): array
    {
        try {
            $output = $this->shell->execute('cd %s && npm outdated --json --long 2>/dev/null; true', [$path]);
        } catch (\Exception $e) {
            return [];
        }

        if (trim($output) === '') {
            return [];
        }

        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return [];
        }

        $packages = [];
        foreach ($decoded as $name => $info) {
            // npm may report a list of entries per package (one per dependent)
            if (is_array($info) && array_is_list($info)) {
                $info = $info[0] ?? null;
            }
            if (!is_array($info)) {
                continue;
            }

            $packages[] = [
                'name' => (string) $name,
                'current' => $this->getStringValue($info, 'current'),
                'wanted' => $this->getStringValue($info, 'wanted'),
                'latest' => $this->getStringValue($info, 'latest'),
                'type' => $this->getStringValue($info, 'type', 'dependencies'),
            ];
        }

        return $packages;
    }

    /**
     * Update npm packages within the version ranges defined in package.json
     *
     * Only node_modules and package-lock.json are updated; package.json is left untouched.
     *
     * @param string $path
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    public function updatePackages(string $path, SymfonyStyle $io, bool $isVerbose): bool
    {
        try {
            if ($isVerbose) {
                $io->text('Running npm update...');
            }
            $this->shell->execute('cd %s && npm update --quiet', [$path]);
            if ($isVerbose) {
                $io->success('Packages updated within their semver ranges.');
            }
            return true;
        } catch (\Exception $e) {
            $io->error('Failed to update packages: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Install specific package versions with the save flag matching the dependency type
     *
     * @param string $path
     * @param string $dependencyType npm dependency type (e.g. 'dependencies', 'devDependencies')
     * @param array<string,string> $packageVersions Map of package name to version
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    public function installPackageVersions(
        string $path,
        string $dependencyType,
        array $packageVersions,
        SymfonyStyle $io,
        bool $isVerbose,
    ): bool {
        if (empty($packageVersions)) {
            return true;
        }

        $saveFlag = self::SAVE_FLAGS[$dependencyType] ?? '--save';

        $specs = [];
        foreach ($packageVersions as $name => $version) {
            $specs[] = $name . '@' . $version;
        }

        $placeholders = implode(' ', array_fill(0, count($specs), '%s'));

        try {
            if ($isVerbose) {
                $io->text(sprintf('Installing %s: %s', $dependencyType, implode(', ', $specs)));
            }
            $this->shell->execute(
                sprintf('cd %%s && npm install %s --quiet %s', $saveFlag, $placeholders),
                array_merge([$path], $specs),
            );
            if ($isVerbose) {
                $io->success(sprintf('Installed %d package(s) as %s.', count($specs), $dependencyType));
            }
            return true;
        } catch (\Exception $e) {
            $io->error('Failed to install packages: ' . $e->getMessage());
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
     * Read a string value from decoded npm JSON output
     *
     * @param array<mixed> $info
     * @param string $key
     * @param string $default
     * @return string
     */
    private function getStringValue(array $info, string $key, string $default = '-'): string
    {
        $value = $info[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
