<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Terminal;

/**
 * Service for handling terminal environment variables securely
 */
class TerminalEnvironmentService
{
    private array $originalEnv = [];
    private array $secureEnvStorage = [];

    /**
     * Safely get environment variable with sanitization
     */
    public function getEnvVar(string $name): ?string
    {
        $value = $this->getSecureEnvironmentValue($name);

        if ($value === null || $value === '') {
            return null;
        }

        return $this->sanitizeEnvironmentValue($name, $value);
    }

    /**
     * Safely get server variable with sanitization
     */
    public function getServerVar(string $name): ?string
    {
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

        $value = filter_input(INPUT_SERVER, $name);

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        return $this->sanitizeAlphanumericValue((string) $value);
    }

    /**
     * Safely set environment variable with validation
     */
    public function setEnvVar(string $name, string $value): void
    {
        if (empty($name) || !is_string($name)) {
            return;
        }

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return;
        }

        $sanitizedValue = $this->sanitizeEnvironmentValue($name, $value);

        if ($sanitizedValue !== null) {
            $this->setSecureEnvironmentValue($name, $sanitizedValue);
        }
    }

    /**
     * Set environment for Laravel Prompts
     */
    public function setPromptEnvironment(): void
    {
        $this->originalEnv = [
            'COLUMNS' => $this->getEnvVar('COLUMNS'),
            'LINES' => $this->getEnvVar('LINES'),
            'TERM' => $this->getEnvVar('TERM'),
        ];

        $this->setEnvVar('COLUMNS', '100');
        $this->setEnvVar('LINES', '40');
        $this->setEnvVar('TERM', 'xterm-256color');
    }

    /**
     * Reset terminal environment after prompts
     */
    public function resetPromptEnvironment(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                $this->removeSecureEnvironmentValue($key);
            } else {
                $this->setEnvVar($key, $value);
            }
        }
    }

    /**
     * Securely retrieve environment variable
     */
    private function getSecureEnvironmentValue(string $name): ?string
    {
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            return null;
        }

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
            $allowedVars = ['COLUMNS', 'LINES', 'TERM', 'CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'TEAMCITY_VERSION'];

            foreach ($allowedVars as $var) {
                if (isset($this->secureEnvStorage[$var])) {
                    $cachedEnv[$var] = $this->secureEnvStorage[$var];
                } else {
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
        return match($name) {
            'COLUMNS', 'LINES' => $this->sanitizeNumericValue($value),
            'TERM' => $this->sanitizeTermValue($value),
            'CI', 'GITHUB_ACTIONS', 'GITLAB_CI' => $this->sanitizeBooleanValue($value),
            'JENKINS_URL', 'TEAMCITY_VERSION' => $this->sanitizeAlphanumericValue($value),
            default => $this->sanitizeAlphanumericValue($value)
        };
    }

    /**
     * Sanitize numeric values
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
     * Securely store environment variable
     */
    private function setSecureEnvironmentValue(string $name, string $value): void
    {
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
        unset($this->secureEnvStorage[$name]);
        $this->clearEnvironmentCache();
    }

    /**
     * Clear the environment variable cache
     */
    private function clearEnvironmentCache(): void
    {
        $this->secureEnvStorage = [];
    }
}
