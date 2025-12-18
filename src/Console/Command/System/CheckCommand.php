<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\System;

use Composer\Semver\Comparator;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Escaper;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Service\DatabaseInfoService;
use OpenForgeProject\MageForge\Service\SearchEngineInfoService;
use OpenForgeProject\MageForge\Service\SystemInfoService;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for checking system information
 */
class CheckCommand extends AbstractCommand
{
    /**
     * @param ProductMetadataInterface $productMetadata
     * @param Escaper $escaper
     * @param SystemInfoService $systemInfoService
     * @param DatabaseInfoService $databaseInfoService
     * @param SearchEngineInfoService $searchEngineInfoService
     */
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly Escaper $escaper,
        private readonly SystemInfoService $systemInfoService,
        private readonly DatabaseInfoService $databaseInfoService,
        private readonly SearchEngineInfoService $searchEngineInfoService,
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
        $nodeVersion = $this->systemInfoService->getNodeVersion();
        $mysqlVersion = $this->databaseInfoService->getMysqlVersion();
        $dbType = $this->databaseInfoService->getDatabaseType();
        $osInfo = $this->systemInfoService->getOsInfo();
        $magentoVersion = $this->productMetadata->getVersion();
        $latestLtsNodeVersion = $this->escaper->escapeHtml($this->systemInfoService->getLatestLtsNodeVersion());
        $composerVersion = $this->systemInfoService->getComposerVersion();
        $npmVersion = $this->systemInfoService->getNpmVersion();
        $gitVersion = $this->systemInfoService->getGitVersion();
        $xdebugStatus = $this->systemInfoService->getXdebugStatus();
        $redisStatus = $this->systemInfoService->getRedisStatus();
        $searchEngineStatus = $this->searchEngineInfoService->getSearchEngineStatus();
        $phpExtensions = $this->systemInfoService->getImportantPhpExtensions();
        $diskSpace = $this->systemInfoService->getDiskSpace();

        $nodeVersionDisplay = Comparator::lessThan($nodeVersion, $latestLtsNodeVersion)
            ? "<fg=yellow>$nodeVersion</> (Latest LTS: <fg=green>$latestLtsNodeVersion</>)"
            : "$nodeVersion (Latest LTS: <fg=green>$latestLtsNodeVersion</>)";

        $dbDisplay = $dbType . ' ' . $mysqlVersion;

        $this->io->section('System Components');
        $this->io->table(
            ['Component', 'Version/Status'],
            [
                ['PHP', $phpVersion . ' (Memory limit: ' . $this->systemInfoService->getPhpMemoryLimit() . ')'],
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
}
