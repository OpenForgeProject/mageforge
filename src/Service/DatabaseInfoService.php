<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

/**
 * Service for retrieving database information
 */
class DatabaseInfoService
{
    /**
     * Get MySQL version
     *
     * @return string
     */
    public function getMysqlVersion(): string
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
     * Get database type
     *
     * @return string
     */
    public function getDatabaseType(): string
    {
        return 'MySQL'; // Only MySQL is supported in the current version
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
        // phpcs:ignore Security.BadFunctions.SystemExecFunctions -- exec with static command is safe
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
     * @return array
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
            $value = ini_get($name);
            if ($value !== false) {
                return $value;
            }
        }

        // Use Environment class if available (Magento 2.3+)
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
            // Continue with other methods
        }

        return null;
    }
}
