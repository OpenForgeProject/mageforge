<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Magento\Framework\Console\Cli;
use GuzzleHttp\Client;
use Composer\Semver\Comparator;

class SystemCheckCommand extends Command
{
    private const NODE_LTS_URL = 'https://nodejs.org/dist/index.json';

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('mageforge:system-check');
        $this->setDescription('Displays system information like PHP version and Node.js version');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $phpVersion = phpversion();
        $nodeVersion = trim(shell_exec('node -v'));
        $mysqlVersion = $this->getShortMysqlVersion();
        $osInfo = $this->getShortOsInfo();
        $latestLtsNodeVersion = $this->getLatestLtsNodeVersion();

        $nodeVersionDisplay = Comparator::lessThan($nodeVersion, $latestLtsNodeVersion)
            ? "<fg=yellow>$nodeVersion</> (Latest LTS: <fg=green>$latestLtsNodeVersion</>)"
            : "$nodeVersion (Latest LTS: <fg=green>$latestLtsNodeVersion</>)";

        $io->title('System Information');
        $io->section('System Components');
        $io->table(
            ['Component', 'Version'],
            [
                ['PHP', $phpVersion],
                new TableSeparator(),
                ['Node.js', $nodeVersionDisplay],
                new TableSeparator(),
                ['MySQL', $mysqlVersion],
                new TableSeparator(),
                ['OS', $osInfo]
            ]
        );

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Get the latest LTS Node.js version from the Node.js API
     */
    private function getLatestLtsNodeVersion(): string
    {
        try {
            $client = new Client();
            $response = $client->get(self::NODE_LTS_URL);
            if ($response->getStatusCode() !== 200) {
                return 'Unknown';
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return 'Unknown';
            }

            foreach ($data as $release) {
                if (!empty($release['lts'])) {
                    return $release['version'];
                }
            }

            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get a shortened MySQL version string
     */
    private function getShortMysqlVersion(): string
    {
        $mysqlVersion = trim(shell_exec('mysql -V'));
        if (preg_match('/Distrib ([\d.]+)/', $mysqlVersion, $matches)) {
            return $matches[1];
        }
        return 'Unknown';
    }

    /**
     * Get a shortened OS information string
     */
    private function getShortOsInfo(): string
    {
        $osInfo = php_uname();
        $osInfoParts = explode(' ', $osInfo);
        return $osInfoParts[0] . ' ' . $osInfoParts[2];
    }
}
