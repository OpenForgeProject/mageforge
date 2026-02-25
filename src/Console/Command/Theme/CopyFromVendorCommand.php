<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use OpenForgeProject\MageForge\Console\Command\AbstractCommand;
use OpenForgeProject\MageForge\Model\ThemeList;
use OpenForgeProject\MageForge\Service\VendorFileMapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Filesystem\Driver\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;

class CopyFromVendorCommand extends AbstractCommand
{
    /**
     * @param ThemeList $themeList
     * @param VendorFileMapper $vendorFileMapper
     * @param DirectoryList $directoryList
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param File $fileDriver
     */
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly VendorFileMapper $vendorFileMapper,
        private readonly DirectoryList $directoryList,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly File $fileDriver
    ) {
        parent::__construct();
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'copy-from-vendor'))
             ->setDescription('Copy a file from vendor/ to a specific theme with correct path resolution')
             ->setAliases(['theme:copy'])
             ->addArgument('file', InputArgument::REQUIRED, 'Path to the source file (vendor/...)')
             ->addArgument('theme', InputArgument::OPTIONAL, 'Target theme code (e.g. Magento/luma)')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the copy operation without performing it');
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $sourceFileArg = $input->getArgument('file');
        $isDryRun = $input->getOption('dry-run');
        $absoluteSourcePath = $this->getAbsoluteSourcePath($sourceFileArg);

        // Update sourceFileArg if it was normalized to relative path
        $rootPath = $this->directoryList->getRoot();
        $sourceFile = str_starts_with($absoluteSourcePath, $rootPath . '/')
            ? substr($absoluteSourcePath, strlen($rootPath) + 1)
            : $sourceFileArg;

        $themeCode = $this->getThemeCode($input);
        [$themePath, $themeArea] = $this->getThemePathAndArea($themeCode);

        $destinationPath = $this->vendorFileMapper->mapToThemePath($sourceFile, $themePath, $themeArea);
        $absoluteDestPath = $this->getAbsoluteDestPath($destinationPath, $rootPath);

        if ($isDryRun) {
            $this->showDryRunPreview($sourceFile, $absoluteDestPath, $rootPath);
            return Cli::RETURN_SUCCESS;
        }

        if (!$this->confirmCopy($sourceFile, $absoluteDestPath, $rootPath)) {
            return Cli::RETURN_SUCCESS;
        }

        $this->performCopy($absoluteSourcePath, $absoluteDestPath);
        $this->io->success("File copied successfully.");

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Get absolute source path
     *
     * @param string $sourceFile
     * @return string
     * @throws \RuntimeException
     */
    private function getAbsoluteSourcePath(string $sourceFile): string
    {
        $rootPath = $this->directoryList->getRoot();
        if (str_starts_with($sourceFile, '/')) {
            $absoluteSourcePath = $sourceFile;
        } else {
            $absoluteSourcePath = $rootPath . '/' . $sourceFile;
        }

        if (!$this->fileDriver->isExists($absoluteSourcePath)) {
            throw new \RuntimeException("Source file not found: $absoluteSourcePath");
        }

        return $absoluteSourcePath;
    }

    /**
     * Get theme code
     *
     * @param InputInterface $input
     * @return string
     * @throws \RuntimeException
     */
    private function getThemeCode(InputInterface $input): string
    {
        $themeCode = $input->getArgument('theme');
        if ($themeCode) {
            return $themeCode;
        }

        $themes = $this->themeList->getAllThemes();
        $options = [];
        foreach ($themes as $theme) {
            $options[$theme->getCode()] = $theme->getCode();
        }

        if (empty($options)) {
            throw new \RuntimeException('No themes found to copy to.');
        }

        if (!$input->isInteractive()) {
            $themeList = implode(', ', array_keys($options));
            throw new \RuntimeException(
                "Theme argument is missing. Available themes: $themeList"
            );
        }

        $this->fixPromptEnvironment();

        try {
            $result = search(
                label: 'Select target theme',
                options: fn (string $value) => array_filter(
                    $options,
                    fn ($option) => str_contains(strtolower($option), strtolower($value))
                ),
                placeholder: 'Search for a theme...'
            );
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            return (string) $result;
        } finally {
            $this->resetPromptEnvironment();
        }
    }

    /**
     * Get theme path and area from theme code
     *
     * @param string $themeCode
     * @return array{0: string, 1: string} [themePath, themeArea]
     */
    private function getThemePathAndArea(string $themeCode): array
    {
        $theme = $this->themeList->getThemeByCode($themeCode);
        if (!$theme) {
            throw new \RuntimeException("Theme not found: $themeCode");
        }

        $themeArea = $theme->getArea();
        $regName = $themeArea . '/' . $theme->getCode();
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $regName);

        if (!$themePath) {
            $this->io->warning(
                "Theme path not found via ComponentRegistrar for $regName, falling back to getFullPath()"
            );
            $themePath = $theme->getFullPath();
        }

        return [$themePath, $themeArea];
    }

    /**
     * Get absolute destination path
     *
     * @param string $destinationPath
     * @param string $rootPath
     * @return string
     */
    private function getAbsoluteDestPath(string $destinationPath, string $rootPath): string
    {
        if (str_starts_with($destinationPath, '/')) {
            return $destinationPath;
        }
        return $rootPath . '/' . $destinationPath;
    }

    /**
     * Confirm copy operation
     *
     * @param string $sourceFile
     * @param string $absoluteDestPath
     * @param string $rootPath
     * @return bool
     */
    private function confirmCopy(string $sourceFile, string $absoluteDestPath, string $rootPath): bool
    {
        $destinationDisplay = str_starts_with($absoluteDestPath, $rootPath . '/')
            ? substr($absoluteDestPath, strlen($rootPath) + 1)
            : $absoluteDestPath;

        $this->io->section('Copy Preview');
        $this->io->text([
            "Source: <info>$sourceFile</info>",
            "Target: <info>$destinationDisplay</info>",
            "Absolute Target: <comment>$absoluteDestPath</comment>"
        ]);
        $this->io->newLine();

        $this->setPromptEnvironment();

        try {
            if ($this->fileDriver->isExists($absoluteDestPath)) {
                $this->io->warning("File already exists at destination!");
                $result = confirm(
                    label: 'Overwrite existing file?',
                    default: false
                );
                \Laravel\Prompts\Prompt::terminal()->restoreTty();
                $this->resetPromptEnvironment();
                return $result;
            }

            $result = confirm(
                label: 'Proceed with copy?',
                default: true
            );
            \Laravel\Prompts\Prompt::terminal()->restoreTty();
            $this->resetPromptEnvironment();
            return $result;
        } catch (\Exception $e) {
            $this->resetPromptEnvironment();
            $this->io->error('Interactive mode failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform copy operation
     *
     * @param string $absoluteSourcePath
     * @param string $absoluteDestPath
     * @return void
     * @throws \RuntimeException
     */
    private function performCopy(string $absoluteSourcePath, string $absoluteDestPath): void
    {
        $directory = $this->fileDriver->getParentDirectory($absoluteDestPath);
        if (!$this->fileDriver->isDirectory($directory)) {
            $this->fileDriver->createDirectory($directory);
        }
        $this->fileDriver->copy($absoluteSourcePath, $absoluteDestPath);
    }

    /**
     * Show dry run preview
     *
     * @param string $sourceFile
     * @param string $absoluteDestPath
     * @param string $rootPath
     * @return void
     */
    private function showDryRunPreview(string $sourceFile, string $absoluteDestPath, string $rootPath): void
    {
        $destinationDisplay = str_starts_with($absoluteDestPath, $rootPath . '/')
            ? substr($absoluteDestPath, strlen($rootPath) + 1)
            : $absoluteDestPath;

        $this->io->section('Dry Run - Copy Preview');
        $this->io->text([
            "Source: <info>$sourceFile</info>",
            "Target: <info>$destinationDisplay</info>",
            "Absolute Target: <comment>$absoluteDestPath</comment>"
        ]);
        $this->io->newLine();

        if ($this->fileDriver->isExists($absoluteDestPath)) {
            $this->io->warning("File already exists at destination and would be overwritten!");
        } else {
            $this->io->info("File would be created at destination.");
        }

        $this->io->note("No files were modified (dry-run mode).");
    }

    /**
     * Fix prompt environment
     *
     * @return void
     */
    private function fixPromptEnvironment(): void
    {
        $this->setPromptEnvironment();
    }
}
