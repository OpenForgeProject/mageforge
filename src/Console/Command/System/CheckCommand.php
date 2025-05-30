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
        // Versuch 1: Überprüfe über PHP-Datenbankverbindung
        try {
            // Versuche Magento-Verbindung zu verwenden
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $version = $connection->fetchOne('SELECT VERSION()');
            if (!empty($version)) {
                return $version;
            }
        } catch (\Exception $e) {
            // Fallback zur direkten PDO-Verbindung, wenn Magento-Verbindung fehlschlägt
        }

        // Versuch 2: Versuche den MySQL-Client
        exec('mysql --version 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $versionString = $output[0];
            preg_match('/Distrib ([0-9.]+)/', $versionString, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
        }

        // Versuch 3: Versuche generische Datenbankverbindung
        // Lese ENV-Variablen, die in verschiedenen Umgebungen vorhanden sein könnten
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
                    $value = getenv($env);
                    if ($value !== false) {
                        $config[$key] = $value;
                        break;
                    }
                }
            }

            // Standardwerte, wenn nichts gefunden wurde
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
            // Ignoriere Fehler und gib Unknown zurück
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
        // Schritt 1: Zuerst versuche, die Magento-Konfiguration zu prüfen
        try {
            // Verwende ObjectManager, um die aktuelle Suchmaschine aus der Magento-Konfiguration zu ermitteln
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            // Prüfe zuerst die Suchmaschinen-Einstellung
            try {
                $deploymentConfig = $objectManager->get(\Magento\Framework\App\DeploymentConfig::class);
                $engineConfig = $deploymentConfig->get('system/search/engine');
                if (!empty($engineConfig)) {
                    // Prüfe die Verbindung zur Suchmaschine basierend auf der Konfiguration
                    $host = $deploymentConfig->get('system/search/engine_host') ?: 'localhost';
                    $port = $deploymentConfig->get('system/search/engine_port') ?: '9200';

                    // Versuche zu verbinden
                    $url = "http://{$host}:{$port}";
                    if ($this->testElasticsearchConnection($url)) {
                        return ucfirst($engineConfig) . ' (Magento config)';
                    }

                    return ucfirst($engineConfig) . ' (Configured but not reachable)';
                }
            } catch (\Exception $e) {
                // Ignorieren und zum nächsten Versuch übergehen
            }

            // Alternative Methode für Magento 2.3+
            try {
                $engineResolver = $objectManager->get(\Magento\Framework\Search\EngineResolverInterface::class);
                if ($engineResolver) {
                    $currentEngine = $engineResolver->getCurrentSearchEngine();
                    if (!empty($currentEngine)) {
                        return ucfirst($currentEngine) . ' (Magento config)';
                    }
                }
            } catch (\Exception $e) {
                // Ignorieren und zum nächsten Versuch übergehen
            }
        } catch (\Exception $e) {
            // Ignoriere Fehler beim Zugriff auf Magento-Konfiguration
        }

        // Schritt 2: Prüfe, ob die PHP-Erweiterungen vorhanden sind
        if (extension_loaded('elasticsearch')) {
            return 'Elasticsearch Available (PHP Extension)';
        } elseif (extension_loaded('opensearch')) {
            return 'OpenSearch Available (PHP Extension)';
        }

        // Schritt 3: Prüfe, ob Elasticsearch über HTTP erreichbar ist (generische Ansatz)
        try {
            // Versuche mehrere übliche Hostnamen und Ports
            $elasticHosts = [
                'http://localhost:9200',
                'http://127.0.0.1:9200',
                'http://elasticsearch:9200',
                'http://opensearch:9200'
            ];

            // Env-Variablen prüfen für Hostkonfiguration
            $envHosts = [
                'ELASTICSEARCH_HOST', 'ES_HOST', 'OPENSEARCH_HOST'
            ];

            foreach ($envHosts as $envVar) {
                $hostValue = getenv($envVar);
                if (!empty($hostValue)) {
                    $port = getenv('ELASTICSEARCH_PORT') ?: getenv('ES_PORT') ?: getenv('OPENSEARCH_PORT') ?: '9200';
                    $elasticHosts[] = "http://{$hostValue}:{$port}";
                }
            }

            // Teste alle möglichen Verbindungen
            foreach ($elasticHosts as $url) {
                $info = $this->testElasticsearchConnection($url);
                if ($info !== false) {
                    if (isset($info['version']['distribution']) && $info['version']['distribution'] === 'opensearch') {
                        return 'OpenSearch ' . $info['version']['number'];
                    } elseif (isset($info['version']['number'])) {
                        return 'Elasticsearch ' . $info['version']['number'];
                    }
                    return 'Search Engine Available';
                }
            }
        } catch (\Exception $e) {
            // Fehler beim Prüfen ignorieren
        }

        return 'Not Available';
    }

    /**
     * Test Elasticsearch connection and return version info
     *
     * @param string $url
     * @return array|bool
     */
    private function testElasticsearchConnection(string $url)
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] === 200 && !empty($response)) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                return $data;
            }
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
