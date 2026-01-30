<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Dev;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to enable, disable or check status of MageForge Inspector
 */
class InspectorCommand extends AbstractCommand
{
    private const XML_PATH_INSPECTOR_ENABLED = 'dev/mageforge_inspector/enabled';
    private const ARGUMENT_ACTION = 'action';

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly State $state,
        private readonly CacheManager $cacheManager,
        private readonly ScopeConfigInterface $scopeConfig,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'inspector'))
            ->setDescription('Manage MageForge Frontend Inspector (Actions: enable|disable|status)')
            ->addArgument(
                self::ARGUMENT_ACTION,
                InputArgument::REQUIRED,
                'Action to perform: enable, disable, or status'
            )
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command manages the MageForge Frontend Inspector:

  <info>php %command.full_name%</info> <comment>enable</comment>
  Enable the inspector (requires developer mode)

  <info>php %command.full_name%</info> <comment>disable</comment>
  Disable the inspector

  <info>php %command.full_name%</info> <comment>status</comment>
  Show current inspector status

The inspector allows you to hover over frontend elements to see template paths,
block classes, modules, and other metadata. Activate with Ctrl+Shift+I.
HELP
            );

        parent::configure();
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower((string)$input->getArgument(self::ARGUMENT_ACTION));

        // Validate action
        if (!in_array($action, ['enable', 'disable', 'status'], true)) {
            $this->io->error(sprintf(
                'Invalid action "%s". Use: enable, disable, or status',
                $action
            ));
            return Cli::RETURN_FAILURE;
        }

        // Check developer mode for enable/disable actions
        if (in_array($action, ['enable', 'disable'], true) && !$this->isDeveloperMode()) {
            $this->io->error([
                'Inspector can only be enabled/disabled in developer mode.',
                'Current mode: ' . $this->state->getMode(),
                '',
                'To switch to developer mode, run:',
                '  bin/magento deploy:mode:set developer',
            ]);
            return Cli::RETURN_FAILURE;
        }

        return match ($action) {
            'enable' => $this->enableInspector(),
            'disable' => $this->disableInspector(),
            'status' => $this->showStatus(),
        };
    }

    /**
     * Enable inspector
     *
     * @return int
     */
    private function enableInspector(): int
    {
        $this->configWriter->save(self::XML_PATH_INSPECTOR_ENABLED, '1');
        $this->cleanCache();

        $this->io->success('MageForge Inspector has been enabled!');
        $this->io->writeln([
            'The inspector will now be active on the frontend for allowed IPs.',
            '',
            'Usage:',
            '  • Press Ctrl+Shift+I (or Cmd+Option+I on macOS) to toggle the inspector',
            '  • Hover over elements to see their template information',
            '  • Click to pin the inspector panel',
            '',
            '<comment>Note: Inspector only works in developer mode and for allowed IPs</comment>',
        ]);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Disable inspector
     *
     * @return int
     */
    private function disableInspector(): int
    {
        $this->configWriter->save(self::XML_PATH_INSPECTOR_ENABLED, '0');
        $this->cleanCache();

        $this->io->success('MageForge Inspector has been disabled.');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Show inspector status
     *
     * @return int
     */
    private function showStatus(): int
    {
        $isDeveloperMode = $this->isDeveloperMode();
        $isEnabled = $this->isInspectorEnabled();

        $this->io->section('MageForge Inspector Status');

        $this->io->writeln([
            sprintf('Mode: <comment>%s</comment>', $this->state->getMode()),
            sprintf('Inspector: %s', $isEnabled ? '<info>Enabled</info>' : '<comment>Disabled</comment>'),
        ]);

        if (!$isDeveloperMode) {
            $this->io->newLine();
            $this->io->warning([
                'Inspector requires developer mode to function.',
                'Switch to developer mode with: bin/magento deploy:mode:set developer',
            ]);
        } elseif (!$isEnabled) {
            $this->io->newLine();
            $this->io->note('Run "bin/magento mageforge:theme:inspector enable" to activate the inspector.');
        } else {
            $this->io->newLine();
            $this->io->writeln('<info>✓</info> Inspector is active and ready to use!');
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Check if Magento is in developer mode
     *
     * @return bool
     */
    private function isDeveloperMode(): bool
    {
        try {
            return $this->state->getMode() === State::MODE_DEVELOPER;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if inspector is enabled in configuration
     *
     * @return bool
     */
    private function isInspectorEnabled(): bool
    {
        try {
            return $this->scopeConfig->isSetFlag(self::XML_PATH_INSPECTOR_ENABLED);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean config cache
     *
     * @return void
     */
    private function cleanCache(): void
    {
        $this->cacheManager->clean(['config']);
        $this->io->writeln('<comment>Config cache cleaned</comment>');
    }
}
