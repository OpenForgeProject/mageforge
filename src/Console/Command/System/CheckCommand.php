<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\System;

use Composer\Semver\Comparator;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Escaper;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for checking system information
 */
class CheckCommand extends AbstractCommand
{
    private const NODE_LTS_URL = 'https://nodejs.org/dist/index.json';

    /**
     * @param ProductMetadataInterface $productMetadata
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Escaper $escaper,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('system', 'check'))
            ->setDescription('Displays system information like PHP version and Node.js version');
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * Get Node.js version
     *
     * @return string
     */
    private function getNodeVersion(): string
    {
        exec('node -v 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output) ? trim($output[0], 'v') : 'Not installed';
    }

    /**
     * Get latest LTS Node.js version
     *
     * @return string
     */
    private function getLatestLtsNodeVersion(): string
    {
        try {
            $nodeData = file_get_contents(self::NODE_LTS_URL);
            if ($nodeData === false) {
                return 'Unknown';
            }

            $nodes = json_decode($nodeData, true);
            if (!is_array($nodes)) {
                return 'Unknown';
            }

            foreach ($nodes as $node) {
                if (isset($node['lts']) && $node['lts'] !== false) {
                    return trim($node['version'], 'v');
                }
            }
            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get MySQL version
     *
     * @return string
     */
    private function getShortMysqlVersion(): string
    {
        exec('mysql --version 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0 || empty($output)) {
            return 'Not available';
        }

        $versionString = $output[0];
        preg_match('/Distrib ([0-9.]+)/', $versionString, $matches);
        return isset($matches[1]) ? $matches[1] : 'Unknown';
    }

    /**
     * Get database type
     *
     * @return string
     */
    private function getDatabaseType(): string
    {
        return 'MySQL'; // In der aktuellen Version ist nur MySQL unterstützt
    }

    /**
     * Get OS info
     *
     * @return string
     */
    private function getShortOsInfo(): string
    {
        return php_uname('s') . ' ' . php_uname('r');
    }

    /**
     * Get Composer version
     *
     * @return string
     */
    private function getComposerVersion(): string
    {
        exec('composer --version 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0 || empty($output)) {
            return 'Not installed';
        }

        preg_match('/Composer version ([^ ]+)/', $output[0], $matches);
        return isset($matches[1]) ? $matches[1] : 'Unknown';
    }

    /**
     * Get NPM version
     *
     * @return string
     */
    private function getNpmVersion(): string
    {
        exec('npm --version 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output) ? trim($output[0]) : 'Not installed';
    }

    /**
     * Get Git version
     *
     * @return string
     */
    private function getGitVersion(): string
    {
        exec('git --version 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0 || empty($output)) {
            return 'Not installed';
        }

        preg_match('/git version (.+)/', $output[0], $matches);
        return isset($matches[1]) ? $matches[1] : 'Unknown';
    }

    /**
     * Get Xdebug status
     *
     * @return string
     */
    private function getXdebugStatus(): string
    {
        return extension_loaded('xdebug') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get Redis status
     *
     * @return string
     */
    private function getRedisStatus(): string
    {
        return extension_loaded('redis') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get search engine status
     *
     * @return string
     */
    private function getSearchEngineStatus(): string
    {
        if (extension_loaded('elasticsearch')) {
            return 'Elasticsearch Available';
        } elseif (extension_loaded('opensearch')) {
            return 'OpenSearch Available';
        }
        return 'Not Available';
    }

    /**
     * Get important PHP extensions
     *
     * @return array
     */
    private function getImportantPhpExtensions(): array
    {
        $extensions = [];
        $requiredExtensions = [
            'curl', 'dom', 'fileinfo', 'gd', 'intl', 'json', 'mbstring',
            'openssl', 'pdo_mysql', 'simplexml', 'soap', 'xml', 'zip'
        ];

        foreach ($requiredExtensions as $ext) {
            $status = extension_loaded($ext) ? 'Enabled' : 'Disabled';
            $extensions[] = [$ext, $status];
        }

        return $extensions;
    }

    /**
     * Get PHP memory limit
     *
     * @return string
     */
    private function getPhpMemoryLimit(): string
    {
        return ini_get('memory_limit');
    }

    /**
     * Get disk space
     *
     * @return string
     */
    private function getDiskSpace(): string
    {
        $totalSpace = disk_total_space('.');
        $freeSpace = disk_free_space('.');

        $totalGB = round($totalSpace / 1024 / 1024 / 1024, 2);
        $freeGB = round($freeSpace / 1024 / 1024 / 1024, 2);
        $usedGB = round($totalGB - $freeGB, 2);
        $usedPercent = round(($usedGB / $totalGB) * 100, 2);

        return "$usedGB GB / $totalGB GB ($usedPercent%)";
    }
}
