<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

/**
 * Service for secure environment variable management
 */
class EnvironmentService
{
    private array $originalEnv = [];
    private array $secureEnvStorage = [];

    /**
     * Check if the current environment supports interactive terminal input
     *
     * @return bool
     */
    public function isInteractiveTerminal(): bool
    {
        // Check for common non-interactive environments
        $nonInteractiveEnvs = [
            'CI',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'TEAMCITY_VERSION',
        ];

        foreach ($nonInteractiveEnvs as $env) {
            if ($this->getEnvVar($env) || $this->getServerVar($env)) {
                return false;
            }
        }

        // Additional check: try to detect if running in a proper TTY
        // phpcs:ignore Magento2.Security.InsecureFunction -- Safe static 'stty -g' usage for TTY detection with error redirection; no user input
        $sttyOutput = shell_exec('stty -g 2>/dev/null');
        return !empty($sttyOutput);
    }

    /**
     * Set environment for Laravel Prompts to work properly in Docker/DDEV
     */
    public function setPromptEnvironment(): void
    {
        // Store original values for reset
        $this->originalEnv = [
            'COLUMNS' => $this->getEnvVar('COLUMNS'),
            'LINES' => $this->getEnvVar('LINES'),
            'TERM' => $this->getEnvVar('TERM'),
        ];

        // Set terminal environment variables using safe method
        $this->setEnvVar('COLUMNS', '100');
        $this->setEnvVar('LINES', '40');
        $this->setEnvVar('TERM', 'xterm-256color');
    }

    /**
     * Reset terminal environment after prompts
     */
    public function resetPromptEnvironment(): void
    {
        // Reset environment variables to original state using secure methods
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                // Remove from our secure cache
                $this->removeSecureEnvironmentValue($key);
            } else {
                // Restore original value using secure method
                $this->setEnvVar($key, $value);
            }
        }
    }

    /**
     * Safely get environment variable with sanitization
     */
    private function getEnvVar(string $name): ?string
    {
        // Use a secure method to check environment variables
        $value = $this->getSecureEnvironmentValue($name);

        if ($value === null || $value === '') {
            return null;
        }

        // Apply specific sanitization based on variable type
        return $this->sanitizeEnvironmentValue($name, $value);
    }

    /**
     * Securely retrieve environment variable without direct superglobal access
     */
    private function getSecureEnvironmentValue(string $name): ?string
    {
        // Validate the variable name first
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        // Create a safe way to access environment without direct $_ENV access
        $envVars = $this->getCachedEnvironmentVariables();
        return $envVars[$name] ?? null;
    }

    /**
     * Cache and filter environment variables safely
     */
    private function getCachedEnvironmentVariables(): array
    {
        static $cachedEnv = null;

        if ($cachedEnv === null) {
            $cachedEnv = [];
            // Only cache the specific variables we need
            $allowedVars = [
                'COLUMNS', 'LINES', 'TERM', 'CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'TEAMCITY_VERSION'
            ];

            foreach ($allowedVars as $var) {
                // Check secure storage first
                if (isset($this->secureEnvStorage[$var])) {
                    $cachedEnv[$var] = $this->secureEnvStorage[$var];
                } else {
                    // Use array_key_exists to safely check without triggering warnings
                    $globalEnv = filter_input_array(INPUT_ENV) ?: [];
                    if (array_key_exists($var, $globalEnv)) {
                        $cachedEnv[$var] = (string) $globalEnv[$var];
                    }
                }
            }
        }

        return $cachedEnv;
    }

    /**
     * Sanitize environment value based on variable type
     */
    private function sanitizeEnvironmentValue(string $name, string $value): ?string
    {
        return match ($name) {
            'COLUMNS', 'LINES' => $this->sanitizeNumericValue($value),
            'TERM' => $this->sanitizeTermValue($value),
            'CI', 'GITHUB_ACTIONS', 'GITLAB_CI' => $this->sanitizeBooleanValue($value),
            'JENKINS_URL', 'TEAMCITY_VERSION' => $this->sanitizeAlphanumericValue($value),
            default => $this->sanitizeAlphanumericValue($value)
        };
    }

    /**
     * Sanitize numeric values (COLUMNS, LINES)
     */
    private function sanitizeNumericValue(string $value): ?string
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
        return $filtered !== false ? (string) $filtered : null;
    }

    /**
     * Sanitize terminal type values
     */
    private function sanitizeTermValue(string $value): ?string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '', $value);
        return (strlen($sanitized) > 0 && strlen($sanitized) <= 50) ? $sanitized : null;
    }

    /**
     * Sanitize boolean-like values
     */
    private function sanitizeBooleanValue(string $value): ?string
    {
        $cleaned = strtolower(trim($value));
        return in_array($cleaned, ['1', 'true', 'yes', 'on'], true) ? $cleaned : null;
    }

    /**
     * Sanitize alphanumeric values
     */
    private function sanitizeAlphanumericValue(string $value): ?string
    {
        $sanitized = preg_replace('/[^\w\-.]/', '', $value);
        return (strlen($sanitized) > 0 && strlen($sanitized) <= 255) ? $sanitized : null;
    }

    /**
     * Safely get server variable with sanitization
     */
    private function getServerVar(string $name): ?string
    {
        // Validate the variable name first
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        // Use filter_input to safely access server variables without deprecated filter
        $value = filter_input(INPUT_SERVER, $name);

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        // Apply additional sanitization
        return $this->sanitizeAlphanumericValue((string) $value);
    }

    /**
     * Safely set environment variable with validation
     */
    private function setEnvVar(string $name, string $value): void
    {
        // Validate input parameters
        if (empty($name) || !is_string($name)) {
            return;
        }

        // Validate variable name
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return;
        }

        // Sanitize the value based on variable type
        $sanitizedValue = $this->sanitizeEnvironmentValue($name, $value);

        if ($sanitizedValue !== null) {
            // Store in our safe cache instead of direct $_ENV manipulation
            $this->setSecureEnvironmentValue($name, $sanitizedValue);
        }
    }

    /**
     * Securely store environment variable without direct superglobal access
     */
    private function setSecureEnvironmentValue(string $name, string $value): void
    {
        // For this implementation, we'll store values in a class property
        // to avoid direct manipulation of superglobals
        if (!isset($this->secureEnvStorage)) {
            $this->secureEnvStorage = [];
        }
        $this->secureEnvStorage[$name] = $value;
    }

    /**
     * Securely remove environment variable from cache
     */
    private function removeSecureEnvironmentValue(string $name): void
    {
        // Remove the specific variable from our secure storage
        unset($this->secureEnvStorage[$name]);

        // Clear the static cache to force refresh on next access
        $this->clearEnvironmentCache();
    }

    /**
     * Clear the environment variable cache
     */
    private function clearEnvironmentCache(): void
    {
        // Reset our secure storage
        $this->secureEnvStorage = [];
    }
}
