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
            ->setDescription('Displays system information like PHP version and Node.js version')
            ->setAliases(['system:check']);
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
        // Try different methods to get MySQL version
        $version = $this->getMysqlVersionViaMagento();
        if (!empty($version)) {
            return $version;
        }

        $version = $this->getMysqlVersionViaClient();
        if (!empty($version)) {
            return $version;
        }

        $version = $this->getMysqlVersionViaPdo();
        if (!empty($version)) {
            return $version;
        }

        return 'Unknown';
    }

    /**
     * Get MySQL version via Magento connection
     *
     * @return string|null
     */
    private function getMysqlVersionViaMagento(): ?string
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $version = $connection->fetchOne('SELECT VERSION()');

            return !empty($version) ? $version : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get MySQL version via command line client
     *
     * @return string|null
     */
    private function getMysqlVersionViaClient(): ?string
    {
        exec('mysql --version 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $versionString = $output[0];
            preg_match('/Distrib ([0-9.]+)/', $versionString, $matches);

            return isset($matches[1]) ? $matches[1] : null;
        }

        return null;
    }

    /**
     * Get MySQL version via PDO connection
     *
     * @return string|null
     */
    private function getMysqlVersionViaPdo(): ?string
    {
        try {
            $config = $this->getDatabaseConfig();

            // Default values if nothing is found
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? '3306';
            $user = $config['user'] ?? 'root';
            $pass = $config['pass'] ?? '';

            $dsn = "mysql:host=$host;port=$port";
            $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_TIMEOUT => 1]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return !empty($version) ? $version : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get database configuration from environment variables
     *
     * @return array<string, string>
     */
    private function getDatabaseConfig(): array
    {
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
                $value = $this->getEnvironmentVariable($env);
                if ($value !== null) {
                    $config[$key] = $value;
                    break;
                }
            }
        }

        return $config;
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

            // First try via deployment config
            $configResult = $this->checkSearchEngineViaDeploymentConfig($objectManager);
            if ($configResult !== null) {
                return $configResult;
            }

            // Then try via engine resolver
            $resolverResult = $this->checkSearchEngineViaEngineResolver($objectManager);
            if ($resolverResult !== null) {
                return $resolverResult;
            }
        } catch (\Exception $e) {
            // Ignore general exceptions
        }

        return null;
    }

    /**
     * Check search engine via Magento deployment config
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @return string|null
     */
    private function checkSearchEngineViaDeploymentConfig($objectManager): ?string
    {
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
            // Ignore specific exceptions
        }

        return null;
    }

    /**
     * Check search engine via Magento engine resolver
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @return string|null
     */
    private function checkSearchEngineViaEngineResolver($objectManager): ?string
    {
        try {
            $engineResolver = $objectManager->get(\Magento\Framework\Search\EngineResolverInterface::class);
            if ($engineResolver) {
                $currentEngine = $engineResolver->getCurrentSearchEngine();
                if (!empty($currentEngine)) {
                    return ucfirst($currentEngine) . ' (Magento config)';
                }
            }
        } catch (\Exception $e) {
            // Ignore specific exceptions
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
     * @return string[]
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
            $hostValue = $this->getEnvironmentVariable($envVar);
            if (!empty($hostValue)) {
                $port = $this->getEnvironmentVariable('ELASTICSEARCH_PORT') ??
                       $this->getEnvironmentVariable('ES_PORT') ??
                       $this->getEnvironmentVariable('OPENSEARCH_PORT') ?? '9200';
                $elasticHosts[] = "http://{$hostValue}:{$port}";
            }
        }

        return $elasticHosts;
    }

    /**
     * Format search engine version output
     *
     * @param array<string, mixed> $info
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
    }    /**
     * Test Elasticsearch connection and return version info
     *
     * @param string $url
     * @return array<string, mixed>|false
     */
    private function testElasticsearchConnection(string $url)
    {
        try {
            // First attempt: Try using Magento's HTTP client
            $magentoClientResult = $this->tryMagentoHttpClient($url);
            if ($magentoClientResult !== null) {
                return $magentoClientResult;
            }

            // No fallback to native approaches anymore - rely on Magento's HTTP client only
            // This avoids using discouraged functions
        } catch (\Exception $e) {
            // Ignore exceptions and return false
        }

        return false;
    }

    /**
     * Try to connect using Magento's HTTP client
     *
     * @param string $url
     * @return array<string, mixed>|null
     */
    private function tryMagentoHttpClient(string $url): ?array
    {
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
            // Ignore exceptions
        }

        return null;
    }

    /**
     * Get important PHP extensions
     *
     * @return array<int, array<int, string>>
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
    }    /**
     * Safely get environment variable value
     *
     * @param string $name Environment variable name
     * @param string|null $default Default value if not found
     * @return string|null
     */
    private function getEnvironmentVariable(string $name, ?string $default = null): ?string
    {
        // Try Magento-specific methods first
        $magentoValue = $this->getMagentoEnvironmentValue($name);
        if ($magentoValue !== null) {
            return $magentoValue;
        }

        // Try system environment variables
        $systemValue = $this->getSystemEnvironmentValue($name);
        if ($systemValue !== null) {
            return $systemValue;
        }

        return $default;
    }

    /**
     * Get environment variable from Magento
     *
     * @param string $name Environment variable name
     * @return string|null
     */
    private function getMagentoEnvironmentValue(string $name): ?string
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            // Try via deployment config
            $deploymentValue = $this->getValueFromDeploymentConfig($objectManager, $name);
            if ($deploymentValue !== null) {
                return $deploymentValue;
            }

            // Try via environment service
            $serviceValue = $this->getValueFromEnvironmentService($objectManager, $name);
            if ($serviceValue !== null) {
                return $serviceValue;
            }
        } catch (\Exception $e) {
            // Ignore exceptions
        }

        return null;
    }

    /**
     * Get value from Magento deployment config
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $name
     * @return string|null
     */
    private function getValueFromDeploymentConfig($objectManager, string $name): ?string
    {
        try {
            $deploymentConfig = $objectManager->get(\Magento\Framework\App\DeploymentConfig::class);
            $envValue = $deploymentConfig->get('system/default/environment/' . $name);
            if ($envValue !== null) {
                return (string)$envValue;
            }
        } catch (\Exception $e) {
            // Ignore exceptions
        }

        return null;
    }

    /**
     * Get value from Magento environment service
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $name
     * @return string|null
     */
    private function getValueFromEnvironmentService($objectManager, string $name): ?string
    {
        try {
            $environmentService = $objectManager->get(\Magento\Framework\App\EnvironmentInterface::class);
            $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($name))));
            if (method_exists($environmentService, $method)) {
                $value = $environmentService->$method();
                if ($value !== null) {
                    return (string)$value;
                }
            }
        } catch (\Exception $e) {
            // Ignore exceptions
        }

        return null;
    }

    /**
     * Get environment variable from the system
     *
     * @param string $name Environment variable name
     * @return string|null
     */
    private function getSystemEnvironmentValue(string $name): ?string
    {
        // Use ini_get for certain system variables as a safer alternative
        if (in_array($name, ['memory_limit', 'max_execution_time'])) {
            $value = (string)ini_get($name);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
