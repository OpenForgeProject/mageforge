<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Model\ThemeList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\Console\Cli;

class ListThemesCommand extends Command
{
    /**
     * Constructor
     *
     * @param ThemeList $themeList
     */
    public function __construct(
        private readonly ThemeList $themeList,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this->setName('mageforge:themes:list');
        $this->setDescription('Lists all available themes');
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $themes = $this->themeList->getAllThemes();

        if (empty($themes)) {
            $output->writeln('<info>No themes found.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln('<info>Available Themes:</info>');
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
