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
        // Attempt 1: Check via PHP database connection
        try {
            // Try to use Magento connection
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $version = $connection->fetchOne('SELECT VERSION()');
            if (!empty($version)) {
                return $version;
            }
        } catch (\Exception $e) {
            // Fallback to direct PDO connection if Magento connection fails
        }

        // Attempt 2: Try the MySQL client
        exec('mysql --version 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $versionString = $output[0];
            preg_match('/Distrib ([0-9.]+)/', $versionString, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        // Attempt 3: Try generic database connection
        // Read ENV variables that might be present in different environments
        try {
            $envMapping = [
                'host' => ['DB_HOST', 'MYSQL_HOST', 'MAGENTO_DB_HOST'],
                'port' => ['DB_PORT', 'MYSQL_PORT', 'MAGENTO_DB_PORT', '3306'],
                'user' => ['DB_USER', 'MYSQL_USER', 'MAGENTO_DB_USER'],
                'pass' => ['DB_PASSWORD', 'MYSQL_PASSWORD', 'MAGENTO_DB_PASSWORD'],
                'name' => ['DB_NAME', 'MYSQL_DATABASE', 'MAGENTO_DB_NAME'],
            ];

            $config = [];
            foreach ($envMapping as $key => $envVars) {
                foreach ($envVars as $env) {
                    // First check $_SERVER
                    if (isset($_SERVER[$env])) {
                        $config[$key] = $_SERVER[$env];
                        break;
                    }
                    // Then check $_ENV
                    elseif (isset($_ENV[$env])) {
                        $config[$key] = $_ENV[$env];
                        break;
                    }
                }
            }

            // Default values if nothing is found
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? '3306';
            $user = $config['user'] ?? 'root';
            $pass = $config['pass'] ?? '';

            $dsn = "mysql:host=$host;port=$port";
            $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_TIMEOUT => 1]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            if (!empty($version)) {
                return $version;
            }
        } catch (\Exception $e) {
            // Ignore errors and return Unknown
        }

        return 'Unknown';
    }

    /**
     * Get database type
     *
     * @return string
     */
    private function getDatabaseType(): string
    {
        return 'MySQL'; // Only MySQL is supported in the current version
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
        // Method 1: Check Magento configuration
        $magentoConfigResult = $this->getSearchEngineFromMagentoConfig();
        if ($magentoConfigResult) {
            return $magentoConfigResult;
        }

        // Method 2: Check PHP extensions
        $extensionResult = $this->checkSearchEngineExtensions();
        if ($extensionResult) {
            return $extensionResult;
        }

        // Method 3: Check HTTP connection
        $connectionResult = $this->checkSearchEngineConnections();
        if ($connectionResult) {
            return $connectionResult;
        }

        return 'Not Available';
    }

    /**
     * Check search engine from Magento configuration
     *
     * @return string|null
     */
    private function getSearchEngineFromMagentoConfig(): ?string
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            // Method 1a: Via DeploymentConfig
            try {
                $deploymentConfig = $objectManager->get(\Magento\Framework\App\DeploymentConfig::class);
                $engineConfig = $deploymentConfig->get('system/search/engine');

                if (!empty($engineConfig)) {
                    $host = $deploymentConfig->get('system/search/engine_host') ?: 'localhost';
                    $port = $deploymentConfig->get('system/search/engine_port') ?: '9200';

                    $url = "http://{$host}:{$port}";
                    if ($this->testElasticsearchConnection($url)) {
                        return ucfirst($engineConfig) . ' (Magento config)';
                    }

                    return ucfirst($engineConfig) . ' (Configured but not reachable)';
                }
            } catch (\Exception $e) {
                // Proceed to next attempt
            }

            // Method 1b: Via EngineResolver for Magento 2.3+
            try {
                $engineResolver = $objectManager->get(\Magento\Framework\Search\EngineResolverInterface::class);
                if ($engineResolver) {
                    $currentEngine = $engineResolver->getCurrentSearchEngine();
                    if (!empty($currentEngine)) {
                        return ucfirst($currentEngine) . ' (Magento config)';
                    }
                }
            } catch (\Exception $e) {
                // Proceed to next attempt
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Check for search engine PHP extensions
     *
     * @return string|null
     */
    private function checkSearchEngineExtensions(): ?string
    {
        if (extension_loaded('elasticsearch')) {
            return 'Elasticsearch Available (PHP Extension)';
        }

        if (extension_loaded('opensearch')) {
            return 'OpenSearch Available (PHP Extension)';
        }

        return null;
    }

    /**
     * Check available search engine connections
     *
     * @return string|null
     */
    private function checkSearchEngineConnections(): ?string
    {
        try {
            $elasticHosts = $this->getSearchEngineHosts();

            foreach ($elasticHosts as $url) {
                $info = $this->testElasticsearchConnection($url);
                if ($info !== false) {
                    return $this->formatSearchEngineVersion($info);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Get potential search engine hosts
     *
     * @return array
     */
    private function getSearchEngineHosts(): array
    {
        $elasticHosts = [
            'http://localhost:9200',
            'http://127.0.0.1:9200',
            'http://elasticsearch:9200',
            'http://opensearch:9200'
        ];

        $envHosts = [
            'ELASTICSEARCH_HOST', 'ES_HOST', 'OPENSEARCH_HOST'
        ];

        foreach ($envHosts as $envVar) {
            $hostValue = $_SERVER[$envVar] ?? $_ENV[$envVar] ?? null;
            if (!empty($hostValue)) {
                $port = $_SERVER['ELASTICSEARCH_PORT'] ?? $_ENV['ELASTICSEARCH_PORT'] ??
                       $_SERVER['ES_PORT'] ?? $_ENV['ES_PORT'] ??
                       $_SERVER['OPENSEARCH_PORT'] ?? $_ENV['OPENSEARCH_PORT'] ?? '9200';
                $elasticHosts[] = "http://{$hostValue}:{$port}";
            }
        }

        return $elasticHosts;
    }

    /**
     * Format search engine version output
     *
     * @param array $info
     * @return string
     */
    private function formatSearchEngineVersion(array $info): string
    {
        if (isset($info['version']['distribution']) && $info['version']['distribution'] === 'opensearch') {
            return 'OpenSearch ' . $info['version']['number'];
        }

        if (isset($info['version']['number'])) {
            return 'Elasticsearch ' . $info['version']['number'];
        }

        return 'Search Engine Available';
    }

    /**
     * Test Elasticsearch connection and return version info
     *
     * @param string $url
     * @return array|bool
     */
    private function testElasticsearchConnection(string $url)
    {
        try {
            // Use Magento's HTTP client if available
            try {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $httpClientFactory = $objectManager->get(\Magento\Framework\HTTP\ClientFactory::class);
                $httpClient = $httpClientFactory->create();
                $httpClient->setTimeout(2);
                $httpClient->get($url);

                $status = $httpClient->getStatus();
                $response = $httpClient->getBody();

                if ($status === 200 && !empty($response)) {
                    $data = json_decode($response, true);
                    if (is_array($data)) {
                        return $data;
                    }
                }
            } catch (\Exception $e) {
                // Fall back to a native PHP approach
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2.0,
                        'ignore_errors' => true
                    ]
                ]);

                // Using file_get_contents with a timeout context is safer than curl
                $response = @file_get_contents($url, false, $context);

                if ($response !== false) {
                    // Check headers for status code
                    $status = 0;
                    foreach ($http_response_header ?? [] as $header) {
                        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                            $status = (int)$matches[1];
                            break;
                        }
                    }

                    if ($status === 200 && !empty($response)) {
                        $data = json_decode($response, true);
                        if (is_array($data)) {
                            return $data;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore exceptions and return false
        }

        return false;
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
