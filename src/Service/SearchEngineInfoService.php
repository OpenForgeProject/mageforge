<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Magento\Framework\HTTP\ClientFactory;

/**
 * Service for retrieving search engine information
 */
class SearchEngineInfoService
{
    /**
     * @param ClientFactory $httpClientFactory
     */
    public function __construct(
        private readonly ClientFactory $httpClientFactory,
    ) {
    }

    /**
     * Get search engine status
     *
     * @return string
     */
    public function getSearchEngineStatus(): string
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
            // Ignore exceptions
        }

        return false;
    }

    /**
     * Get environment variable value
     *
     * @param string $name Environment variable name
     * @return string|null
     */
    private function getEnvironmentVariable(string $name): ?string
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $env = $objectManager->get(\Magento\Framework\App\Environment::class);
            if (method_exists($env, 'getEnv')) {
                $value = $env->getEnv($name);
                if ($value !== false && $value !== null) {
                    return $value;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return null;
    }
}
