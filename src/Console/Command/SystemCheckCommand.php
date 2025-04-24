<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Composer\Semver\Comparator;
use GuzzleHttp\Client;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Escaper;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SystemCheckCommand extends AbstractCommand
{
    private const NODE_LTS_URL = 'https://nodejs.org/dist/index.json';

    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Escaper $escaper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mageforge:system:check');
        $this->setDescription('Displays system information like PHP version and Node.js version');
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
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

        $this->io->section('System Components');
        $this->io->table(
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

        if (!empty($phpExtensions)) {
            $this->io->section('PHP Extensions');
            $this->io->table(['Component', 'Version/Status'], $phpExtensions);
        }

        return Cli::RETURN_SUCCESS;
    }

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

    private function getShortMysqlVersion(): string
    {
        return $this->extractVersionFromCommand('mysql -V') ??
               $this->extractVersionFromCommand('mysqld --version') ??
               'Unknown';
    }

    private function extractVersionFromCommand(string $command): ?string
    {
        try {
            $output = $this->runCommand($command);

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

            return $output;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getDatabaseType(): string
    {
        return $this->detectDatabaseTypeFromCommand('mysql -V') ??
               $this->detectDatabaseTypeFromCommand('mysqld --version') ??
               'Unknown';
    }

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

    private function getShortOsInfo(): string
    {
        list($osName, , $osVersion) = explode(' ', php_uname());
        return ($osName ?? 'Unknown') . ' ' . ($osVersion ?? 'Unknown');
    }

    private function getNodeVersion(): string
    {
        return $this->runCommand('node -v');
    }

    /**
     * @throws ProcessFailedException
     */
    private function runCommand(string $command): string
    {
        $process = new Process(explode(' ', $command));
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $this->escaper->escapeHtml($process->getErrorOutput());
            $output = $this->escaper->escapeHtml($process->getOutput());

            $safeProcess = new Process(explode(' ', $command));
            $safeProcess->setOutput($output);
            $safeProcess->setErrorOutput($errorOutput);

            throw new ProcessFailedException($safeProcess);
        }

        return $this->escaper->escapeHtml(trim($process->getOutput()));
    }

    private function getComposerVersion(): string
    {
        return $this->runCommand('composer -V');
    }

    private function getNpmVersion(): string
    {
        return $this->runCommand('npm -v');
    }

    private function getGitVersion(): string
    {
        return $this->runCommand('git --version');
    }

    private function getXdebugStatus(): string
    {
        return extension_loaded('xdebug') ? 'Enabled' : 'Disabled';
    }

    private function getRedisStatus(): string
    {
        return extension_loaded('redis') ? 'Enabled' : 'Disabled';
    }

    private function getSearchEngineStatus(): string
    {
        return extension_loaded('elasticsearch') ? 'Enabled' : 'Disabled';
    }

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

    private function getPhpMemoryLimit(): string
    {
        return ini_get('memory_limit');
    }

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

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / (1024 ** $power), 2, '.', ',') . ' ' . $units[$power];
    }
}
