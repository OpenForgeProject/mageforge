<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\System;

use GuzzleHttp\Client;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for displaying version information
 */
class VersionCommand extends AbstractCommand
{
    private const API_URL = 'https://api.github.com/repos/openforgeproject/mageforge/releases/latest';
    private const PACKAGE_NAME = 'openforgeproject/mageforge';
    private const UNKNOWN_VERSION = 'Unknown';

    /**
     * @param File $fileDriver
     */
    public function __construct(
        private readonly File $fileDriver
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('system', 'version'))
            ->setDescription('Displays the module version and the latest version');
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $moduleVersion = $this->getModuleVersion();
        $latestVersion = $this->getLatestVersion();

        $this->io->title('MageForge Version Information');
        $this->io->section('Versions');
        $this->io->listing([
            "Module Version: $moduleVersion",
            "Latest Version: $latestVersion"
        ]);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Get the current module version
     *
     * @return string
     */
    private function getModuleVersion(): string
    {
        try {
            $composerJson = $this->fileDriver->fileGetContents(
                __DIR__ . '/../../../../composer.json'
            );
            $composerData = json_decode($composerJson, true);
            return $composerData['version'] ?? self::UNKNOWN_VERSION;
        } catch (\Exception $e) {
            return self::UNKNOWN_VERSION;
        }
    }

    /**
     * Get the latest version from GitHub
     *
     * @return string
     */
    private function getLatestVersion(): string
    {
        try {
            $client = new Client();
            $response = $client->get(self::API_URL, [
                'headers' => [
                    'User-Agent' => 'MageForge-Version-Check'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                return $data['tag_name'] ?? self::UNKNOWN_VERSION;
            }
        } catch (\Exception $e) {
            // Fall through to return unknown
        }

        return self::UNKNOWN_VERSION;
    }
}
