<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Laravel\Prompts\SelectPrompt;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for generating Hyvä design tokens
 */
class TokensCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     * @param ThemePath $themePath
     * @param BuilderPool $builderPool
     * @param File $fileDriver
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly ThemePath $themePath,
        private readonly BuilderPool $builderPool,
        private readonly File $fileDriver,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'tokens'))
            ->setDescription('Generate Hyvä design tokens from design.tokens.json or hyva.config.json')
            ->addArgument(
                'themeCode',
                InputArgument::OPTIONAL,
                'Theme code to generate tokens for (format: Vendor/theme)'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $themeCode = $input->getArgument('themeCode');
        $isVerbose = $this->isVerbose($output);

        if (empty($themeCode)) {
            $themes = $this->themeList->getAllThemes();
            $options = array_map(fn($theme) => $theme->getCode(), $themes);

            $themeCodePrompt = new SelectPrompt(
                label: 'Select theme to generate tokens for',
                options: $options,
                scroll: 10,
                hint: 'Arrow keys to navigate, Enter to confirm',
            );

            try {
                $themeCode = $themeCodePrompt->prompt();
                \Laravel\Prompts\Prompt::terminal()->restoreTty();
            } catch (\Exception $e) {
                $this->io->error('Interactive mode failed: ' . $e->getMessage());
                return Cli::RETURN_FAILURE;
            }
        }

        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $this->io->error("Theme $themeCode is not installed.");
            return Cli::RETURN_FAILURE;
        }

        // Check if this is a Hyvä theme
        $builder = $this->builderPool->getBuilder($themePath);
        if ($builder === null || $builder->getName() !== 'HyvaThemes') {
            $this->io->error("Theme $themeCode is not a Hyvä theme. This command only works with Hyvä themes.");
            return Cli::RETURN_FAILURE;
        }

        $tailwindPath = rtrim($themePath, '/') . '/web/tailwind';
        if (!$this->fileDriver->isDirectory($tailwindPath)) {
            $this->io->error("Tailwind directory not found in: $tailwindPath");
            return Cli::RETURN_FAILURE;
        }

        // Check if node_modules exists
        if (!$this->fileDriver->isDirectory($tailwindPath . '/node_modules')) {
            $this->io->warning('Node modules not found. Please run: bin/magento mageforge:theme:build ' . $themeCode);
            return Cli::RETURN_FAILURE;
        }

        if ($isVerbose) {
            $this->io->section("Generating Hyvä design tokens for theme: $themeCode");
            $this->io->text("Working directory: $tailwindPath");
        }

        // Change to tailwind directory and run npx hyva-tokens
        $currentDir = getcwd();
        chdir($tailwindPath);

        try {
            if ($isVerbose) {
                $this->io->text('Running npx hyva-tokens...');
                passthru('npx hyva-tokens', $returnCode);
            } else {
                exec('npx hyva-tokens 2>&1', $output_lines, $returnCode);
                
                if ($returnCode === 0) {
                    $this->io->success('Hyvä design tokens generated successfully.');
                } else {
                    $this->io->error('Failed to generate Hyvä design tokens.');
                    if (!empty($output_lines)) {
                        $this->io->writeln($output_lines);
                    }
                }
            }

            chdir($currentDir);

            if ($returnCode !== 0) {
                return Cli::RETURN_FAILURE;
            }

            if ($isVerbose) {
                $this->io->success('Hyvä design tokens generated successfully.');
                $this->io->newLine();
                $this->io->text('The generated file can be found at:');
                $this->io->text($tailwindPath . '/generated/hyva-tokens.css');
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            chdir($currentDir);
            $this->io->error('Failed to generate Hyvä design tokens: ' . $e->getMessage());
            return Cli::RETURN_FAILURE;
        }
    }
}
