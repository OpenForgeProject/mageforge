<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Magento\Framework\Console\Cli;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for listing available Magento themes
 */
class ListCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemeList $themeList,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'list'))
            ->setDescription('Lists all available Magento themes')
            ->setAliases(['frontend:list']);
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themes = $this->themeList->getAllThemes();

        if (empty($themes)) {
            $this->io->info('No themes found.');
            return Cli::RETURN_SUCCESS;
        }

        $this->io->section('Available Themes:');
        $table = new Table($output);
        $table->setHeaders(['Code', 'Title', 'Path']);

        foreach ($themes as $path => $theme) {
            $table->addRow([
                sprintf('<fg=yellow>%s</>', $theme->getCode()),
                $theme->getThemeTitle(),
                $path
            ]);
        }

        $table->render();

        return Cli::RETURN_SUCCESS;
    }
}
