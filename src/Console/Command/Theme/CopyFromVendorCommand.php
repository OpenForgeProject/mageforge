<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use InvalidArgumentException;
use Laravel\Prompts\SearchPrompt;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\VendorFileMapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\search;

class CopyFromVendorCommand extends AbstractCommand
{
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly VendorFileMapper $vendorFileMapper,
        private readonly Filesystem $filesystem,
        private readonly DirectoryList $directoryList,
        private readonly ComponentRegistrarInterface $componentRegistrar
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mageforge:theme:copy-from-vendor')
             ->setDescription('Copy a file from vendor/ to a specific theme with correct path resolution')
             ->setAliases(['m:t:cfv'])
             ->addArgument('file', InputArgument::REQUIRED, 'Path to the source file (vendor/...)')
             ->addArgument('theme', InputArgument::OPTIONAL, 'Target theme code (e.g. Magento/luma)');
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $sourceFile = $input->getArgument('file');
        $themeCode = $input->getArgument('theme');

        // 1. Verify Source File
        $rootPath = $this->directoryList->getRoot();
        // If absolute path provided
        if (str_starts_with($sourceFile, '/')) {
            $absoluteSourcePath = $sourceFile;
            // Make relative for display/proecessing
            if (str_starts_with($sourceFile, $rootPath . '/')) {
                $sourceFile = substr($sourceFile, strlen($rootPath) + 1);
            }
        } else {
             $absoluteSourcePath = $rootPath . '/' . $sourceFile;
        }

        if (!file_exists($absoluteSourcePath)) {
            $this->io->error("Source file not found: $absoluteSourcePath");
            return Cli::RETURN_FAILURE;
        }

        // 2. Select Theme if missing
        if (!$themeCode) {
            $themes = $this->themeList->getAllThemes();
            $options = [];
            foreach ($themes as $theme) {
                $options[$theme->getCode()] = $theme->getCode();
            }

            if (empty($options)) {
                $this->io->error('No themes found to copy to.');
                return Cli::RETURN_FAILURE;
            }

            // Fix Environment for DDEV (Required for Laravel Prompts)
            $this->fixPromptEnvironment();

            $themeCode = search(
                label: 'Select target theme',
                options: fn (string $value) => array_filter(
                    $options,
                    fn ($option) => str_contains(strtolower($option), strtolower($value))
                ),
                placeholder: 'Search for a theme...'
            );
        }

        // 3. Resolve Theme Path
        $theme = $this->themeList->getThemeByCode($themeCode);
        if (!$theme) {
             $this->io->error("Theme not found: $themeCode");
             return Cli::RETURN_FAILURE;
        }

        // Use ComponentRegistrar to get absolute path
        $regName = $theme->getArea() . '/' . $theme->getCode();
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $regName);

        if (!$themePath) {
             // Fallback to model path if registrar fails
             $this->io->warning("Theme path not found via ComponentRegistrar for $regName, falling back to getFullPath()");
             $themePath = $theme->getFullPath();
        }

        // 4. Calculate Destination
        try {
            $destinationPath = $this->vendorFileMapper->mapToThemePath($sourceFile, $themePath);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        if (str_starts_with($destinationPath, '/')) {
            $absoluteDestPath = $destinationPath;
        } else {
             $absoluteDestPath = $rootPath . '/' . $destinationPath;
        }

        // Make destination relative for display if it's inside root
        $destinationDisplay = $absoluteDestPath;
        if (str_starts_with($absoluteDestPath, $rootPath . '/')) {
            $destinationDisplay = substr($absoluteDestPath, strlen($rootPath) + 1);
        }

        // 5. Preview & Confirm
        $this->io->section('Copy Preview');
        $this->io->text([
            "Source: <info>$sourceFile</info>",
            "Target: <info>$destinationDisplay</info>",
            "Absolute Target: <comment>$absoluteDestPath</comment>"
        ]);
        $this->io->newLine();

        if (file_exists($absoluteDestPath)) {
            $this->io->warning("File already exists at destination!");
            if (!$this->io->confirm('Overwrite existing file?', false)) {
                return Cli::RETURN_SUCCESS;
            }
        } else {
            if (!$this->io->confirm('Proceed with copy?', true)) {
                return Cli::RETURN_SUCCESS;
            }
        }

        // 6. Perform Copy
        try {
             $directory = dirname($absoluteDestPath);
             if (!is_dir($directory)) {
                 if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                     throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
                 }
             }
             copy($absoluteSourcePath, $absoluteDestPath);
             $this->io->success("File copied successfully.");
        } catch (\Exception $e) {
            $this->io->error("Failed to copy file: " . $e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }

    private function fixPromptEnvironment(): void
    {
        if (getenv('DDEV_PROJECT')) {
             putenv('COLUMNS=100');
             putenv('LINES=40');
             putenv('TERM=xterm-256color');
        }
    }
}
