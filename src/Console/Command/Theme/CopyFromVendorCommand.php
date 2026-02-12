<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Console\Command\Theme;

use InvalidArgumentException;
use Laravel\Prompts\SearchPrompt;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
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
        private readonly DirectoryList $directoryList
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
            return self::RETURN_FAILURE;
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
                return self::RETURN_FAILURE;
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
             return self::RETURN_FAILURE;
        }

        // Use View\Design\ThemeInterface::getFullPath() if available,
        // fallback to calculating path assuming app/design/frontend structure if needed,
        // but Theme model normally has getFullPath().
        // Let's verify what interface we have. We likely have Magento\Theme\Model\Theme which has getFullPath().
        if (!method_exists($theme, 'getFullPath')) {
             // Fallback logic
             $themePath = 'app/design/frontend/' . $theme->getThemePath();
        } else {
             $themeAbsolutePath = $theme->getFullPath(); // This is absolute path
             // Make relative
             if (str_starts_with($themeAbsolutePath, $rootPath . '/')) {
                 $themePath = substr($themeAbsolutePath, strlen($rootPath) + 1);
             } else {
                 $themePath = $themeAbsolutePath;
             }
        }

        // 4. Calculate Destination
        try {
            $destinationRelative = $this->vendorFileMapper->mapToThemePath($sourceFile, $themePath);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return self::RETURN_FAILURE;
        }

        $absoluteDestPath = $rootPath . '/' . $destinationRelative;

        // 5. Preview & Confirm
        $this->io->section('Copy Preview');
        $this->io->text([
            "Source: <info>$sourceFile</info>",
            "Target: <info>$destinationRelative</info>",
        ]);
        $this->io->newLine();

        if (file_exists($absoluteDestPath)) {
            $this->io->warning("File already exists at destination!");
            if (!$this->io->confirm('Overwrite existing file?', false)) {
                return self::RETURN_SUCCESS;
            }
        } else {
            if (!$this->io->confirm('Proceed with copy?', true)) {
                return self::RETURN_SUCCESS;
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
            return self::RETURN_FAILURE;
        }

        return self::RETURN_SUCCESS;
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
