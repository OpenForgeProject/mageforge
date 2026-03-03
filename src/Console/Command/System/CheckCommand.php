<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\System;

use Composer\Semver\Comparator;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Magento\Framework\Escaper;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Shell;
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
     * @param ResourceConnection $resourceConnection
     * @param ClientFactory $httpClientFactory
     * @param Shell $shell
     */
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Escaper $escaper,
        private readonly ResourceConnection $resourceConnection,
        private readonly ClientFactory $httpClientFactory,
        private readonly Shell $shell,
    ) {
        parent::__construct();
    }

    /**
     * Configure command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName($this->getCommandName('system', 'check'))
            ->setDescription('Displays system information like PHP version and Node.js version')
            ->setAliases(['system:check']);
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $phpVersion = phpversion();
        $nodeVersion = $this->getNodeVersion();
        $mysqlVersion = $this->getShortMysqlVersion();
        $dbType = $this->getDatabaseType();
        $osInfo = $this->getShortOsInfo();
        $magentoVersion = $this->productMetadata->getVersion();
        /** @var string $latestLtsNodeVersion */
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
        $this->io->table(['Component', 'Version/Status'], [
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
            ['Magento', $magentoVersion],
        ]);

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
        try {
            $output = trim($this->shell->execute('node -v 2>/dev/null'));
        } catch (\Exception $e) {
            return 'Not installed';
        }

        return $output !== '' ? ltrim($output, 'v') : 'Not installed';
    }

    /**
     * Get latest LTS Node.js version
     *
     * @return string
     */
    private function getLatestLtsNodeVersion(): string
    {
        try {
            $httpClient = $this->httpClientFactory->create();
            $httpClient->setTimeout(2);
            $httpClient->get(self::NODE_LTS_URL);

            if ($httpClient->getStatus() !== 200) {
                return 'Unknown';
            }

            $nodeData = $httpClient->getBody();
            if ($nodeData === '') {
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to fetch latest Node.js LTS version: ' . $e->getMessage());
            }
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
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()->from(null, new \Zend_Db_Expr('VERSION()'));
            $version = $connection->fetchOne($select);

            return !empty($version) ? $version : null;
        } catch (\Exception $e) {
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read MySQL version via Magento connection: ' . $e->getMessage());
            }
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
        try {
            $output = trim($this->shell->execute('mysql --version 2>/dev/null'));
        } catch (\Exception $e) {
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read MySQL version via client: ' . $e->getMessage());
            }
            return null;
        }

        if ($output !== '') {
            preg_match('/Distrib ([0-9.]+)/', $output, $matches);
            return isset($matches[1]) ? $matches[1] : null;
        }

        return null;
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
        try {
            $output = trim($this->shell->execute('composer --version 2>/dev/null'));
        } catch (\Exception $e) {
            return 'Not installed';
        }

        if ($output === '') {
            return 'Not installed';
        }

        preg_match('/Composer version ([^ ]+)/', $output, $matches);
        return isset($matches[1]) ? $matches[1] : 'Unknown';
    }

    /**
     * Get NPM version
     *
     * @return string
     */
    private function getNpmVersion(): string
    {
        try {
            $output = trim($this->shell->execute('npm --version 2>/dev/null'));
        } catch (\Exception $e) {
            return 'Not installed';
        }

        return $output !== '' ? $output : 'Not installed';
    }

    /**
     * Get Git version
     *
     * @return string
     */
    private function getGitVersion(): string
    {
        try {
            $output = trim($this->shell->execute('git --version 2>/dev/null'));
        } catch (\Exception $e) {
            return 'Not installed';
        }

        if ($output === '') {
            return 'Not installed';
        }

        preg_match('/git version (.+)/', $output, $matches);
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read search engine config from Magento: ' . $e->getMessage());
            }
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read search engine from deployment config: ' . $e->getMessage());
            }
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read search engine via resolver: ' . $e->getMessage());
            }
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to check search engine connections: ' . $e->getMessage());
            }
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
            'http://opensearch:9200',
        ];

        $envHosts = [
            'ELASTICSEARCH_HOST',
            'ES_HOST',
            'OPENSEARCH_HOST',
        ];

        foreach ($envHosts as $envVar) {
            $hostValue = $this->getEnvironmentVariable($envVar);
            if (!empty($hostValue)) {
                $port =
                    $this->getEnvironmentVariable('ELASTICSEARCH_PORT') ?? $this->getEnvironmentVariable(
                        'ES_PORT',
                    ) ?? $this->getEnvironmentVariable('OPENSEARCH_PORT') ?? '9200';
                $elasticHosts[] = "http://{$hostValue}:{$port}";
            }
        }

        return $elasticHosts;
    }

    /**
     * Format search engine version output
     *
     * @param array $info
     * @phpstan-param array<string, mixed> $info
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Search engine connection check failed: ' . $e->getMessage());
            }
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
            $httpClient = $this->httpClientFactory->create();
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
            if ($this->io->isVerbose()) {
                $this->io->warning('HTTP client request failed for search engine: ' . $e->getMessage());
            }
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
            'curl',
            'dom',
            'fileinfo',
            'gd',
            'intl',
            'json',
            'mbstring',
            'openssl',
            'pdo_mysql',
            'simplexml',
            'soap',
            'xml',
            'zip',
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

        $totalGB = round((($totalSpace / 1024) / 1024) / 1024, 2);
        $freeGB = round((($freeSpace / 1024) / 1024) / 1024, 2);
        $usedGB = round($totalGB - $freeGB, 2);
        $usedPercent = round(($usedGB / $totalGB) * 100, 2);

        return "$usedGB GB / $totalGB GB ($usedPercent%)";
    }

    /**
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
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read Magento environment value: ' . $e->getMessage());
            }
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
                return (string) $envValue;
            }
        } catch (\Exception $e) {
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read environment from deployment config: ' . $e->getMessage());
            }
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
                    return (string) $value;
                }
            }
        } catch (\Exception $e) {
            if ($this->io->isVerbose()) {
                $this->io->warning('Failed to read environment from service: ' . $e->getMessage());
            }
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
            $value = (string) ini_get($name);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
