<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Laravel\Prompts\SelectPrompt;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use OpenForgeProject\MageForge\Model\ThemePath;

class ThemeWatchCommand extends Command
{
    public function __construct(
        private readonly BuilderPool $builderPool,
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('mageforge:theme:watch')
            ->setDescription('Watches theme files for changes and rebuilds them automatically')
            ->addOption(
                'theme',
                't',
                InputOption::VALUE_OPTIONAL,
                'Theme to watch (format: Vendor/theme)'
            )
            ->setAliases(['frontend:watch']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeCode = $input->getOption('theme');

        if (empty($themeCode)) {
            $themes = $this->themeList->getAllThemes();
            $options = array_map(fn($theme) => $theme->getCode(), $themes);


            $themeCodePrompt = new SelectPrompt(
                label: 'Select theme to watch',
                options: $options,
                scroll: 10,
                hint: 'Arrow keys to navigate, Enter to confirm',
            );

            $themeCode = $themeCodePrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        }

        try {
            $themePath = $this->themePath->getPath($themeCode);
            if ($themePath === null) {
                $io->error("Theme $themeCode is not installed.");
                return Command::FAILURE;
            }

            $builder = $this->builderPool->getBuilder($themePath);
            return $builder->watch($themePath, $io, $output, true) ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
