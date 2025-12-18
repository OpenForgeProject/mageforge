<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

/**
 * Service for retrieving system information
 */
class SystemInfoService
{
    private const NODE_LTS_URL = 'https://nodejs.org/dist/index.json';

    /**
     * Get Node.js version
     *
     * @return string
     */
    public function getNodeVersion(): string
    {
        // phpcs:ignore Security.BadFunctions.SystemExecFunctions -- exec with static command is safe
        exec('node -v 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output) ? trim($output[0], 'v') : 'Not installed';
    }

    /**
     * Get latest LTS Node.js version
     *
     * @return string
     */
    public function getLatestLtsNodeVersion(): string
    {
        try {
            // phpcs:ignore MEQP1.Security.DiscouragedFunction -- file_get_contents with static HTTPS URL is safe
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
     * Get Composer version
     *
     * @return string
     */
    public function getComposerVersion(): string
    {
        // phpcs:ignore Security.BadFunctions.SystemExecFunctions -- exec with static command is safe
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
    public function getNpmVersion(): string
    {
        // phpcs:ignore Security.BadFunctions.SystemExecFunctions -- exec with static command is safe
        exec('npm --version 2>/dev/null', $output, $returnCode);
        return $returnCode === 0 && !empty($output) ? trim($output[0]) : 'Not installed';
    }

    /**
     * Get Git version
     *
     * @return string
     */
    public function getGitVersion(): string
    {
        // phpcs:ignore Security.BadFunctions.SystemExecFunctions -- exec with static command is safe
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
    public function getXdebugStatus(): string
    {
        return extension_loaded('xdebug') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get Redis status
     *
     * @return string
     */
    public function getRedisStatus(): string
    {
        return extension_loaded('redis') ? 'Enabled' : 'Disabled';
    }

    /**
     * Get OS info
     *
     * @return string
     */
    public function getOsInfo(): string
    {
        return php_uname('s') . ' ' . php_uname('r');
    }

    /**
     * Get important PHP extensions
     *
     * @return array
     */
    public function getImportantPhpExtensions(): array
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
    public function getPhpMemoryLimit(): string
    {
        return ini_get('memory_limit');
    }

    /**
     * Get disk space
     *
     * @return string
     */
    public function getDiskSpace(): string
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
