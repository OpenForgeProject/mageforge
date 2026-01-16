<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\SelectPrompt;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use OpenForgeProject\MageForge\Service\ThemeSuggestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for watching theme changes
 */
class WatchCommand extends AbstractCommand
{
    /**
     * @param BuilderPool $builderPool
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param ThemeSuggestion $themeSuggestion
     */
    public function __construct(
        private readonly BuilderPool $builderPool,
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly ThemeSuggestion $themeSuggestion,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName($this->getCommandName('theme', 'watch'))
            ->setDescription('Watches theme files for changes and rebuilds them automatically')
            ->addArgument(
                'themeCode',
                InputArgument::OPTIONAL,
                'Theme to watch (format: Vendor/theme)'
            )
            ->addOption(
                'theme',
                't',
                InputOption::VALUE_OPTIONAL,
                'Theme to watch (format: Vendor/theme)'
            )
            ->setAliases(['frontend:watch']);
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCode = $input->getArgument('themeCode');
        $isVerbose = $this->isVerbose($output);

        if (empty($themeCode)) {
            $themeCode = $input->getOption('theme');
        }

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
            
            // Suggest similar theme names
            $suggestions = $this->themeSuggestion->getSuggestions($themeCode);
            if (!empty($suggestions)) {
                $this->io->writeln('<comment>Did you mean:</comment>');
                foreach ($suggestions as $suggestion) {
                    $this->io->writeln(sprintf('  - <fg=cyan>%s</>', $suggestion));
                }
            }
            
            return self::FAILURE;
        }

        $builder = $this->builderPool->getBuilder($themePath);
        return $builder->watch($themePath, $this->io, $output, $isVerbose) ? self::SUCCESS : self::FAILURE;
    }
}
