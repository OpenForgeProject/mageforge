<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command;

use Laravel\Prompts\SelectPrompt;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ThemeWatchCommand extends AbstractCommand
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

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCode = $input->getOption('theme');

        if (empty($themeCode)) {
            $themes = $this->themeList->getAllThemes();
            $options = [];
            foreach ($themes as $theme) {
                $options[] = $theme->getCode();
            }

            $themeCodePrompt = new SelectPrompt(
                label: 'Select theme to watch',
                options: $options,
                scroll: 10,
                hint: 'Arrow keys to navigate, Enter to confirm',
            );

            $themeCode = $themeCodePrompt->prompt();
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        }

        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $this->io->error("Theme $themeCode is not installed.");
            return self::FAILURE;
        }

        $builder = $this->builderPool->getBuilder($themePath);
        return $builder->watch($themePath, $this->io, $output, true) ? self::SUCCESS : self::FAILURE;
    }
}
