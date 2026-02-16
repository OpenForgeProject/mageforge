<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use InvalidArgumentException;
use Laravel\Prompts\SearchPrompt;
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

use function Laravel\Prompts\search;

class CopyFromVendorCommand extends AbstractCommand
{
    public function __construct(
        private readonly ThemeList $themeList,
        private readonly VendorFileMapper $vendorFileMapper,
        private readonly DirectoryList $directoryList,
        private readonly ComponentRegistrarInterface $componentRegistrar
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mageforge:theme:copy-from-vendor')
             ->setDescription('Copy a file from vendor/ to a specific theme with correct path resolution')
             ->setAliases(['theme:copy'])
             ->addArgument('file', InputArgument::REQUIRED, 'Path to the source file (vendor/...)')
             ->addArgument('theme', InputArgument::OPTIONAL, 'Target theme code (e.g. Magento/luma)')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the copy operation without performing it');
    }

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
        $themePath = $this->getThemePath($themeCode);

        $destinationPath = $this->vendorFileMapper->mapToThemePath($sourceFile, $themePath);
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

    private function getAbsoluteSourcePath(string $sourceFile): string
    {
        $rootPath = $this->directoryList->getRoot();
        if (str_starts_with($sourceFile, '/')) {
            $absoluteSourcePath = $sourceFile;
        } else {
            $absoluteSourcePath = $rootPath . '/' . $sourceFile;
        }

        if (!file_exists($absoluteSourcePath)) {
            throw new \RuntimeException("Source file not found: $absoluteSourcePath");
        }

        return $absoluteSourcePath;
    }

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

        $this->fixPromptEnvironment();

        return (string) search(
            label: 'Select target theme',
            options: fn (string $value) => array_filter(
                $options,
                fn ($option) => str_contains(strtolower($option), strtolower($value))
            ),
            placeholder: 'Search for a theme...'
        );
    }

    private function getThemePath(string $themeCode): string
    {
        $theme = $this->themeList->getThemeByCode($themeCode);
        if (!$theme) {
            throw new \RuntimeException("Theme not found: $themeCode");
        }

        $regName = $theme->getArea() . '/' . $theme->getCode();
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $regName);

        if (!$themePath) {
            $this->io->warning("Theme path not found via ComponentRegistrar for $regName, falling back to getFullPath()");
            $themePath = $theme->getFullPath();
        }

        return $themePath;
    }

    private function getAbsoluteDestPath(string $destinationPath, string $rootPath): string
    {
        if (str_starts_with($destinationPath, '/')) {
            return $destinationPath;
        }
        return $rootPath . '/' . $destinationPath;
    }

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

        if (file_exists($absoluteDestPath)) {
            $this->io->warning("File already exists at destination!");
            return $this->io->confirm('Overwrite existing file?', false);
        }

        return $this->io->confirm('Proceed with copy?', true);
    }

    private function performCopy(string $absoluteSourcePath, string $absoluteDestPath): void
    {
        $directory = dirname($absoluteDestPath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
        copy($absoluteSourcePath, $absoluteDestPath);
    }

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

        if (file_exists($absoluteDestPath)) {
            $this->io->warning("File already exists at destination and would be overwritten!");
        } else {
            $this->io->info("File would be created at destination.");
        }

        $this->io->note("No files were modified (dry-run mode).");
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
