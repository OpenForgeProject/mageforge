<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use OpenForgeProject\MageForge\Model\ThemeList;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;

class ListThemesCommand extends Command
{
<<<<<<< HEAD
    /**
     * Constructor
     *
     * @param ThemeList $themeList
     */
=======
>>>>>>> 46cb511 (add ListThemesCommand)
    public function __construct(
        private readonly ThemeList $themeList,
    ) {
        parent::__construct();
    }

<<<<<<< HEAD
    /**
     * Configure the command
     */
    protected function configure(): void {
=======
    protected function configure(
    ): void {
>>>>>>> 46cb511 (add ListThemesCommand)
        $this->setName('mageforge:themes:list');
        $this->setDescription('Lists all available themes');
    }

<<<<<<< HEAD
    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
=======
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
>>>>>>> 46cb511 (add ListThemesCommand)
    ): int {
        $themes = $this->themeList->getAllThemes();

        if (empty($themes)) {
            $output->writeln('<info>No themes found.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln('<info>Available Themes:</info>');
        foreach ($themes as $path => $theme) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment> - %s',
                    $path,
                    $theme->getThemeTitle(),
                ),
            );
        }

        return Cli::RETURN_SUCCESS;
    }
}
