<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Exception\FetchLatestVersionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use GuzzleHttp\Client;
use Magento\Framework\Filesystem\Driver\File;

class VersionCommand extends Command
{
    private const API_URL = 'https://api.github.com/repos/openforgeproject/mageforge/releases/latest';
    private const PACKAGE_NAME = 'openforgeproject/mageforge';
    private const UNKNOWN_VERSION = 'Unknown';

    /**
     * VersionCommand constructor.
     *
     * @param File $fileDriver
     */
    public function __construct(
        private readonly File $fileDriver
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mageforge:version');
        $this->setDescription('Displays the module version and the latest version');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moduleVersion = $this->getModuleVersion();
        $latestVersion = $this->getLatestVersion();

        $output->writeln("Module Version: $moduleVersion");
        $output->writeln("Latest Version: $latestVersion");

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Get the module version from the composer.lock file
     */
    private function getModuleVersion(): string
    {
        $composerLockPath = __DIR__ . '/../../../../../../composer.lock';
        if (!$this->fileDriver->isExists($composerLockPath)) {
            return self::UNKNOWN_VERSION;
        }

        $composerLock = json_decode($this->fileDriver->fileGetContents($composerLockPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::UNKNOWN_VERSION;
        }

        $moduleVersion = self::UNKNOWN_VERSION;
        foreach ($composerLock['packages'] as $package) {
            if ($package['name'] === self::PACKAGE_NAME) {
                $moduleVersion = $package['version'];
                break;
            }
        }

        return $moduleVersion;
    }

    /**
     * Get the latest version from the GitHub API
     */
    private function getLatestVersion(): string
    {
        try {
            return $this->fetchLatestVersion();
        } catch (\Exception $e) {
            return self::UNKNOWN_VERSION;
        }
    }

    /**
     * Fetch the latest version from the GitHub API
     */
    private function fetchLatestVersion(): string
    {
        $client = new Client();
        $response = $client->get(self::API_URL);
        if ($response->getStatusCode() !== 200) {
            throw new FetchLatestVersionException('Invalid response status');
        }

        $data = json_decode($response->getBody()->getContents(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FetchLatestVersionException('JSON decode error');
        }

        return $data['tag_name'] ?? self::UNKNOWN_VERSION;
    }
}
