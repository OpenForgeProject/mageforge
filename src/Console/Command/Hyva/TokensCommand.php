<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Hyva;

use Laravel\Prompts\SelectPrompt;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Model\ThemePath;
use OpenForgeProject\MageForge\Service\HyvaTokens\TokenProcessor;
use OpenForgeProject\MageForge\Service\ThemeBuilder\HyvaThemes\Builder as HyvaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for generating Hyva design tokens CSS
 */
class TokensCommand extends AbstractCommand
{
    /**
     * @param ThemePath $themePath
     * @param ThemeList $themeList
     * @param TokenProcessor $tokenProcessor
     * @param HyvaBuilder $hyvaBuilder
     */
    public function __construct(
        private readonly ThemePath $themePath,
        private readonly ThemeList $themeList,
        private readonly TokenProcessor $tokenProcessor,
        private readonly HyvaBuilder $hyvaBuilder
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('hyva', 'tokens'))
            ->setDescription('Generate Hyva design tokens CSS from token definitions')
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

        // If no theme code provided, select interactively
        if (empty($themeCode)) {
            $themeCode = $this->selectThemeInteractively($output);
            if ($themeCode === null) {
                return Command::FAILURE;
            }
        }

        // Validate theme
        $themePath = $this->validateTheme($themeCode);
        if ($themePath === null) {
            return Command::FAILURE;
        }

        // Process tokens and return result
        return $this->processTokens($themeCode, $themePath);
    }

    /**
     * Select theme interactively
     *
     * @param OutputInterface $output
     * @return string|null
     */
    private function selectThemeInteractively(OutputInterface $output): ?string
    {
        $hyvaThemes = $this->getHyvaThemes();

        if (empty($hyvaThemes)) {
            $this->io->error('No Hyvä themes found in this installation.');
            return null;
        }

        // Check if we're in an interactive terminal environment
        if (!$this->isInteractiveTerminal($output)) {
            $this->displayAvailableThemes($hyvaThemes);
            return null;
        }

        return $this->promptForTheme($hyvaThemes);
    }

    /**
     * Display available themes for non-interactive environments
     *
     * @param array $hyvaThemes
     * @return void
     */
    private function displayAvailableThemes(array $hyvaThemes): void
    {
        $this->io->info('Available Hyvä themes:');
        foreach ($hyvaThemes as $theme) {
            $this->io->writeln(' - ' . $theme->getCode());
        }
        $this->io->newLine();
        $this->io->info('Usage: bin/magento mageforge:hyva:tokens <theme-code>');
    }

    /**
     * Prompt user to select a theme
     *
     * @param array $hyvaThemes
     * @return string|null
     */
    private function promptForTheme(array $hyvaThemes): ?string
    {
        $options = [];
        foreach ($hyvaThemes as $theme) {
            $options[] = $theme->getCode();
        }

        $themeCodePrompt = new SelectPrompt(
            label: 'Select Hyvä theme to generate tokens for',
            options: $options,
            hint: 'Arrow keys to navigate, Enter to confirm'
        );

        try {
            return $themeCodePrompt->prompt();
        } catch (\Exception $e) {
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            return null;
        } finally {
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
        }
    }

    /**
     * Validate theme exists and is a Hyva theme
     *
     * @param string $themeCode
     * @return string|null
     */
    private function validateTheme(string $themeCode): ?string
    {
        // Get theme path
        $themePath = $this->themePath->getPath($themeCode);
        if ($themePath === null) {
            $this->io->error("Theme $themeCode is not installed.");
            return null;
        }

        // Verify this is a Hyva theme
        if (!$this->hyvaBuilder->detect($themePath)) {
            $this->io->error("Theme $themeCode is not a Hyvä theme. This command only works with Hyvä themes.");
            return null;
        }

        return $themePath;
    }

    /**
     * Process tokens and display results
     *
     * @param string $themeCode
     * @param string $themePath
     * @return int
     */
    private function processTokens(string $themeCode, string $themePath): int
    {
        $this->io->text("Processing design tokens for theme: <fg=cyan>$themeCode</>");
        $result = $this->tokenProcessor->process($themePath);

        if ($result['success']) {
            return $this->handleSuccess($result);
        }

        return $this->handleFailure($result);
    }

    /**
     * Handle successful token processing
     *
     * @param array $result
     * @return int
     */
    private function handleSuccess(array $result): int
    {
        $this->io->newLine();
        $this->io->success($result['message']);
        $this->io->writeln("Output file: <fg=green>{$result['outputPath']}</>");
        $this->io->newLine();
        $this->io->text('ℹ️  Make sure to import this file in your Tailwind CSS configuration.');
        return Command::SUCCESS;
    }

    /**
     * Handle token processing failure
     *
     * @param array $result
     * @return int
     */
    private function handleFailure(array $result): int
    {
        $this->io->error($result['message']);
        $this->io->newLine();
        $this->io->text('ℹ️  To use this command, you need one of the following:');
        $this->io->listing([
            'A design.tokens.json file in the theme\'s web/tailwind directory',
            'A custom token file specified in hyva.config.json',
            'Inline token values in hyva.config.json',
        ]);
        $this->io->newLine();
        $this->io->text('Example hyva.config.json with inline tokens:');
        $this->io->text(<<<JSON
{
    "tokens": {
        "values": {
            "colors": {
                "primary": {
                    "lighter": "oklch(62.3% 0.214 259.815)",
                    "DEFAULT": "oklch(54.6% 0.245 262.881)",
                    "darker": "oklch(37.9% 0.146 265.522)"
                }
            }
        }
    }
}
JSON);
        return Command::FAILURE;
    }

    /**
     * Get list of Hyva themes
     *
     * @return array
     */
    private function getHyvaThemes(): array
    {
        $allThemes = $this->themeList->getAllThemes();
        $hyvaThemes = [];

        foreach ($allThemes as $theme) {
            $themePath = $this->themePath->getPath($theme->getCode());
            if ($themePath && $this->hyvaBuilder->detect($themePath)) {
                $hyvaThemes[] = $theme;
            }
        }

        return $hyvaThemes;
    }

    /**
     * Check if the current environment supports interactive terminal input
     *
     * @param OutputInterface $output
     * @return bool
     */
    private function isInteractiveTerminal(OutputInterface $output): bool
    {
        // Check if output is decorated (supports ANSI codes)
        if (!$output->isDecorated()) {
            return false;
        }

        // Check if STDIN is available and readable
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return false;
        }

        // Additional check: try to detect if running in a proper TTY
        $sttyOutput = shell_exec('stty -g 2>/dev/null');
        return !empty($sttyOutput);
    }
}
