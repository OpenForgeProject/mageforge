<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard\Builder as MagentoStandardBuilder;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Service for updating the Node.js dependencies of a theme
 *
 * Locates the theme-owned package.json files (e.g. web/tailwind for Hyva and
 * TailwindCSS themes), reports outdated packages and updates them either within
 * their semver ranges (default) or to the latest available versions (--latest).
 * Standard Magento themes (e.g. Magento/luma) have no own package.json; for them
 * the Node.js setup in the Magento root is updated instead, at most once per run.
 */
class DependencyUpdater
{
    private const TAILWIND_DIR = 'web/tailwind';
    private const PACKAGE_JSON = 'package.json';
    private const NODE_MODULES = 'node_modules';

    /**
     * Pattern for valid npm package names (validate-npm-package-name rules)
     */
    private const PACKAGE_NAME_PATTERN = '/^(?:@[a-z0-9\-~][a-z0-9\-._~]*\/)?[a-z0-9\-~][a-z0-9\-._~]*$/';

    /**
     * Pattern for valid npm package versions
     */
    private const VERSION_PATTERN = '/^[0-9a-zA-Z.+\-]+$/';

    /**
     * Result of the Magento root update in this run, null while not yet processed
     *
     * @var DependencyUpdateResult|null
     */
    private ?DependencyUpdateResult $magentoRootResult = null;

    /**
     * @param File $fileDriver
     * @param NodePackageManager $nodePackageManager
     * @param BuilderPool $builderPool
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly File $fileDriver,
        private readonly NodePackageManager $nodePackageManager,
        private readonly BuilderPool $builderPool,
        private readonly DirectoryList $directoryList,
    ) {
    }

    /**
     * Update the Node.js dependencies of a theme
     *
     * @param string $themeCode
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @param bool $latest Update beyond semver ranges to the latest versions
     * @param bool $dryRun Only report outdated packages without changing anything
     * @return DependencyUpdateResult
     */
    public function updateThemeDependencies(
        string $themeCode,
        string $themePath,
        SymfonyStyle $io,
        bool $isVerbose,
        bool $latest,
        bool $dryRun,
    ): DependencyUpdateResult {
        $themePath = rtrim($themePath, '/');

        if ($this->isVendorTheme($themePath)) {
            $io->warning(sprintf(
                "Theme '%s' is installed in the vendor directory and is managed by Composer. Skipping.",
                $themeCode,
            ));
            return DependencyUpdateResult::Skipped;
        }

        $packageDirectories = $this->getPackageDirectories($themePath);
        if (empty($packageDirectories)) {
            return $this->updateMagentoRootDependencies($themeCode, $themePath, $io, $isVerbose, $latest, $dryRun);
        }

        $result = true;
        foreach ($packageDirectories as $directory) {
            if (!$this->processPackageDirectory($directory, $io, $isVerbose, $latest, $dryRun)) {
                $result = false;
            }
        }

        return $result ? DependencyUpdateResult::Updated : DependencyUpdateResult::Failed;
    }

    /**
     * Update the Magento root Node.js setup used to build standard Magento themes
     *
     * Standard Magento themes (e.g. Magento/luma) ship no own package.json; their
     * assets are built with Grunt from the Node.js setup in the Magento root. That
     * setup is shared by all standard themes, so it is processed at most once per
     * run and the result is replayed for further standard themes.
     *
     * @param string $themeCode
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @param bool $latest
     * @param bool $dryRun
     * @return DependencyUpdateResult
     */
    private function updateMagentoRootDependencies(
        string $themeCode,
        string $themePath,
        SymfonyStyle $io,
        bool $isVerbose,
        bool $latest,
        bool $dryRun,
    ): DependencyUpdateResult {
        if (!$this->builderPool->getBuilder($themePath) instanceof MagentoStandardBuilder) {
            $io->warning(sprintf("Theme '%s' has no own package.json. Skipping.", $themeCode));
            return DependencyUpdateResult::Skipped;
        }

        $rootPath = rtrim($this->directoryList->getRoot(), '/');
        if (!$this->fileDriver->isExists($rootPath . '/' . self::PACKAGE_JSON)) {
            $io->warning(sprintf(
                "Theme '%s' is built from the Node.js setup in the Magento root, but the Magento root has "
                . 'no package.json. Copy package.json.sample to package.json to set it up.',
                $themeCode,
            ));
            return DependencyUpdateResult::Skipped;
        }

        if ($this->magentoRootResult !== null) {
            $io->writeln(sprintf(
                "  Theme '%s' uses the Magento root Node.js setup, which was already processed in this run.",
                $themeCode,
            ));
            return $this->magentoRootResult;
        }

        $io->text(sprintf(
            "Theme '%s' has no own package.json. Updating the Magento root Node.js setup it is built from.",
            $themeCode,
        ));

        $this->magentoRootResult = $this->processPackageDirectory($rootPath, $io, $isVerbose, $latest, $dryRun)
            ? DependencyUpdateResult::Updated
            : DependencyUpdateResult::Failed;

        return $this->magentoRootResult;
    }

    /**
     * Get the theme-owned directories containing a package.json
     *
     * @param string $themePath
     * @return array<string>
     */
    public function getPackageDirectories(string $themePath): array
    {
        $themePath = rtrim($themePath, '/');
        $candidates = [$themePath . '/' . self::TAILWIND_DIR, $themePath];

        $directories = [];
        foreach ($candidates as $candidate) {
            if ($this->fileDriver->isExists($candidate . '/' . self::PACKAGE_JSON)) {
                $directories[] = $candidate;
            }
        }

        return $directories;
    }

    /**
     * Check if the theme is installed in the vendor directory (managed by Composer)
     *
     * @param string $themePath
     * @return bool
     */
    public function isVendorTheme(string $themePath): bool
    {
        return str_contains($themePath, '/vendor/');
    }

    /**
     * Report and update the outdated packages of a single package directory
     *
     * @param string $directory
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @param bool $latest
     * @param bool $dryRun
     * @return bool
     */
    private function processPackageDirectory(
        string $directory,
        SymfonyStyle $io,
        bool $isVerbose,
        bool $latest,
        bool $dryRun,
    ): bool {
        // Install first so the "current" column of the outdated report is meaningful
        if (!$dryRun && !$this->fileDriver->isDirectory($directory . '/' . self::NODE_MODULES)) {
            if ($isVerbose) {
                $io->text('node_modules missing. Installing dependencies first...');
            }
            if (!$this->nodePackageManager->installNodeModules($directory, $io, $isVerbose)) {
                return false;
            }
        }

        $outdated = $this->nodePackageManager->getOutdatedPackages($directory);
        if ($outdated === null) {
            $io->warning(sprintf(
                'Could not check for outdated packages in %s. Make sure Node.js and npm are available.',
                $directory,
            ));
            return false;
        }

        if (empty($outdated)) {
            $io->writeln(sprintf('  <fg=green>✓</> All packages in %s are up to date.', $directory));
            return true;
        }

        $this->renderOutdatedTable($directory, $outdated, $io);

        if ($dryRun) {
            $target = $latest ? 'the latest versions' : 'the versions within the semver ranges of package.json';
            $io->note(sprintf(
                'Dry run: would update %d package(s) in %s to %s.',
                count($outdated),
                $directory,
                $target,
            ));
            return true;
        }

        $success = $latest
            ? $this->updateToLatest($directory, $outdated, $io, $isVerbose)
            : $this->nodePackageManager->updatePackages($directory, $io, $isVerbose);

        if (!$success) {
            return false;
        }

        $this->reportRemainingOutdatedPackages($directory, $latest, $io);

        return true;
    }

    /**
     * Update all outdated packages to their latest versions, grouped by dependency type
     *
     * @param string $directory
     * @param array<int,array{name:string,current:string,wanted:string,latest:string,type:string}> $outdated
     * @param SymfonyStyle $io
     * @param bool $isVerbose
     * @return bool
     */
    private function updateToLatest(string $directory, array $outdated, SymfonyStyle $io, bool $isVerbose): bool
    {
        $packagesByType = [];
        foreach ($outdated as $package) {
            if ($package['latest'] === '-' || $package['latest'] === $package['current']) {
                continue;
            }
            if (!$this->isSafePackageSpec($package['name'], $package['latest'])) {
                $io->warning(sprintf(
                    'Skipping package with unexpected name or version: %s@%s',
                    $package['name'],
                    $package['latest'],
                ));
                continue;
            }
            $packagesByType[$package['type']][$package['name']] = $package['latest'];
        }

        $result = true;
        foreach ($packagesByType as $type => $packages) {
            if (!$this->nodePackageManager->installPackageVersions($directory, $type, $packages, $io, $isVerbose)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Report packages that are still outdated after the update
     *
     * @param string $directory
     * @param bool $latest
     * @param SymfonyStyle $io
     * @return void
     */
    private function reportRemainingOutdatedPackages(string $directory, bool $latest, SymfonyStyle $io): void
    {
        $remaining = $this->nodePackageManager->getOutdatedPackages($directory);

        if ($remaining === null) {
            $io->warning(sprintf('Could not verify the update result for %s.', $directory));
            return;
        }

        if (empty($remaining)) {
            $io->writeln(sprintf('  <fg=green>✓</> All packages in %s are now up to date.', $directory));
            return;
        }

        if ($latest) {
            $io->warning(sprintf(
                '%d package(s) in %s are still outdated. Check the npm output for details.',
                count($remaining),
                $directory,
            ));
            return;
        }

        $io->note(sprintf(
            '%d package(s) in %s have newer versions outside their semver range. '
            . 'Run again with --latest to update them (may contain breaking changes).',
            count($remaining),
            $directory,
        ));
    }

    /**
     * Render the outdated packages as a table
     *
     * @param string $directory
     * @param array<int,array{name:string,current:string,wanted:string,latest:string,type:string}> $outdated
     * @param SymfonyStyle $io
     * @return void
     */
    private function renderOutdatedTable(string $directory, array $outdated, SymfonyStyle $io): void
    {
        $io->writeln(sprintf('  Outdated packages in <fg=cyan>%s</>:', $directory));

        $rows = [];
        foreach ($outdated as $package) {
            $rows[] = [
                sprintf('<fg=cyan>%s</>', $package['name']),
                $package['current'],
                $package['wanted'],
                $this->formatLatestVersion($package['current'], $package['latest']),
                $package['type'],
            ];
        }

        $io->table(['Package', 'Current', 'Wanted', 'Latest', 'Type'], $rows);
    }

    /**
     * Highlight the latest version in yellow when it is a major update
     *
     * @param string $current
     * @param string $latest
     * @return string
     */
    private function formatLatestVersion(string $current, string $latest): string
    {
        $currentMajor = explode('.', $current)[0];
        $latestMajor = explode('.', $latest)[0];

        if ($currentMajor !== $latestMajor) {
            return sprintf('<fg=yellow>%s</>', $latest);
        }

        return sprintf('<fg=green>%s</>', $latest);
    }

    /**
     * Validate that a package name and version are safe to pass to npm
     *
     * @param string $name
     * @param string $version
     * @return bool
     */
    private function isSafePackageSpec(string $name, string $version): bool
    {
        return preg_match(self::PACKAGE_NAME_PATTERN, $name) === 1 && preg_match(self::VERSION_PATTERN, $version) === 1;
    }
}
