<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Magento\Framework\Console\Cli;
use GuzzleHttp\Client;
use Composer\Semver\Comparator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Escaper;

class SystemCheckCommand extends Command
{
    private const NODE_LTS_URL = 'https://nodejs.org/dist/index.json';

    /**
     * @inheritDoc
     */
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Escaper $escaper,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mageforge:system:check');
        $this->setDescription('Displays system information like PHP version and Node.js version');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $phpVersion = phpversion();
        $nodeVersion = $this->getNodeVersion();
        $mysqlVersion = $this->getShortMysqlVersion();
        $dbType = $this->getDatabaseType();
        $osInfo = $this->getShortOsInfo();
        $magentoVersion = $this->productMetadata->getVersion();
        $latestLtsNodeVersion = $this->escaper->escapeHtml($this->getLatestLtsNodeVersion());
        $composerVersion = $this->getComposerVersion();
        $npmVersion = $this->getNpmVersion();
        $gitVersion = $this->getGitVersion();
        $xdebugStatus = $this->getXdebugStatus();
        $redisStatus = $this->getRedisStatus();
        $searchEngineStatus = $this->getSearchEngineStatus();
        $phpExtensions = $this->getImportantPhpExtensions();
        $diskSpace = $this->getDiskSpace();

        $nodeVersionDisplay = Comparator::lessThan($nodeVersion, $latestLtsNodeVersion)
            ? "<fg=yellow>$nodeVersion</> (Latest LTS: <fg=green>$latestLtsNodeVersion</>)"
            : "$nodeVersion (Latest LTS: <fg=green>$latestLtsNodeVersion</>)";

        $dbDisplay = $dbType . ' ' . $mysqlVersion;

        $io->section('System Components');
        $io->table(
            ['Component', 'Version/Status'],
            [
                ['PHP', $phpVersion . ' (Memory limit: ' . $this->getPhpMemoryLimit() . ')'],
                new TableSeparator(),
                ['Composer', $composerVersion],
                new TableSeparator(),
                ['Node.js', $nodeVersionDisplay],
                new TableSeparator(),
                ['NPM', $npmVersion],
                new TableSeparator(),
                ['Git', $gitVersion],
                new TableSeparator(),
                ['Database', $dbDisplay],
                new TableSeparator(),
                ['Xdebug', $xdebugStatus],
                new TableSeparator(),
                ['Redis', $redisStatus],
                new TableSeparator(),
                ['Search Engine', $searchEngineStatus],
                new TableSeparator(),
                ['OS', $osInfo],
                new TableSeparator(),
                ['Disk Space', $diskSpace],
                new TableSeparator(),
                ['Magento', $magentoVersion]
            ]
        );

        // Display PHP extensions in a separate table
        if (!empty($phpExtensions)) {
            $io->section('PHP Extensions');
            $io->table(['Extension', 'Status'], $phpExtensions);
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Get the latest LTS Node.js version from the Node.js API
     */
    private function getLatestLtsNodeVersion(): string
    {
        try {
            $client = new Client();
            $response = $client->get(self::NODE_LTS_URL);
            if ($response->getStatusCode() !== 200) {
                return 'Unknown';
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return 'Unknown';
            }

            foreach ($data as $release) {
                if (!empty($release['lts'])) {
                    return $release['version'];
                }
            }

            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get a shortened MySQL version string
     */
    private function getShortMysqlVersion(): string
    {
        return $this->extractVersionFromCommand('mysql -V') ??
               $this->extractVersionFromCommand('mysqld --version') ??
               'Unknown';
    }

    /**
     * Extract version number from command output using different patterns
     */
    private function extractVersionFromCommand(string $command): ?string
    {
        try {
            $output = $this->runCommand($command);

            // List of patterns to try, in order of preference
            $patterns = [
                '/mysql Ver\s+(\d+\.\d+\.\d+)/',   // Standard MySQL Format
                '/Distrib\s+(\d+\.\d+\.\d+)/',     // Alternative format with Distrib
                '/Ver\s+(\d+\.\d+\.\d+)/',         // DDEV format or other variations
                '/(\d+\.\d+\.\d+)/'                // Basic fallback pattern
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $output, $matches)) {
                    return $matches[1];
                }
            }

            return $output; // Return the full output if no pattern matches
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get database type (MySQL or MariaDB)
     */
    private function getDatabaseType(): string
    {
        return $this->detectDatabaseTypeFromCommand('mysql -V') ??
               $this->detectDatabaseTypeFromCommand('mysqld --version') ??
               'Unknown';
    }

    /**
     * Detect database type from command output
     */
    private function detectDatabaseTypeFromCommand(string $command): ?string
    {
        try {
            $output = $this->runCommand($command);

            if (stripos($output, 'MariaDB') !== false) {
                return 'MariaDB';
            }

            if (stripos($output, 'MySQL') !== false) {
                return 'MySQL';
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get a shortened OS information string
     */
    private function getShortOsInfo(): string
    {
        list($osName, , $osVersion) = explode(' ', php_uname());
        return ($osName ?? 'Unknown') . ' ' . ($osVersion ?? 'Unknown');
    }

    /**
     * Get the Node.js version
     */
    private function getNodeVersion(): string
    {
        return $this->runCommand('node -v');
    }

    /**
     * Run a command and return the output
     *
     * @param string $command
     * @return string
     * @throws ProcessFailedException
     */
    private function runCommand(string $command): string
    {
        $process = new Process(explode(' ', $command));
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $this->escaper->escapeHtml($process->getErrorOutput());
            $output = $this->escaper->escapeHtml($process->getOutput());
            $process->setErrorOutput($errorOutput);
            $process->setOutput($output);
            throw new ProcessFailedException($process);
        }

        return $this->escaper->escapeHtml(trim($process->getOutput()));
    }

    /**
     * Get the Composer version
     */
    private function getComposerVersion(): string
    {
        return $this->runCommand('composer -V');
    }

    /**
     * Get the NPM version
     */
    private function getNpmVersion(): string
    {
        return $this->runCommand('npm -v');
    }

    /**
     * Get the Git version
     */
    private function getGitVersion(): string
    {
        return $this->runCommand('git --version');
    }

    /**
     * Get the Xdebug status
     */
    private function getXdebugStatus(): string
    {
        return extension_loaded('xdebug') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get the Redis status
     */
    private function getRedisStatus(): string
    {
        return extension_loaded('redis') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get the search engine status
     */
    private function getSearchEngineStatus(): string
    {
        return extension_loaded('elasticsearch') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get important PHP extensions
     */
    private function getImportantPhpExtensions(): array
    {
        $extensions = [
            'curl',
            'gd',
            'intl',
            'mbstring',
            'openssl',
            'pdo_mysql',
            'soap',
            'xsl',
            'zip'
        ];

        $result = [];
        foreach ($extensions as $extension) {
            $result[] = [$extension, extension_loaded($extension) ? 'Enabled' : 'Disabled'];
        }

        return $result;
    }

    /**
     * Get the PHP memory limit
     */
    private function getPhpMemoryLimit(): string
    {
        return ini_get('memory_limit');
    }

    /**
     * Get the disk space
     */
    private function getDiskSpace(): string
    {
        $freeSpace = disk_free_space('/');
        $totalSpace = disk_total_space('/');

        return sprintf(
            'Free: %s, Total: %s',
            $this->formatBytes($freeSpace),
            $this->formatBytes($totalSpace)
        );
    }

    /**
     * Format bytes to a human-readable format
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / (1024 ** $power), 2, '.', ',') . ' ' . $units[$power];
    }
}
