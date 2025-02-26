<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder\MagentoStandard;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\DependencyChecker;
use OpenForgeProject\MageForge\Service\GruntTaskRunner;
use OpenForgeProject\MageForge\Service\StaticContentDeployer;
use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'MagentoStandard';

    public function __construct(
        private readonly DependencyChecker $dependencyChecker,
        private readonly GruntTaskRunner $gruntTaskRunner,
        private readonly StaticContentDeployer $staticContentDeployer,
        private readonly File $fileDriver
    ) {
    }

    public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        if (!$this->detect($themePath)) {
            return false;
        }

        // Check dependencies
        if (!$this->dependencyChecker->checkDependencies($io, $isVerbose)) {
            return false;
        }

        // Run Grunt tasks
        if (!$this->gruntTaskRunner->runTasks($io, $output, $isVerbose)) {
            return false;
        }

        // Deploy static content for the theme
        $themeCode = basename($themePath);
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }

        if ($isVerbose) {
            $io->success("Standard Magento theme has been successfully built.");
        }

        return true;
    }

    public function detect(string $themePath): bool
    {
        // Check if this is a standard Magento theme by looking for theme.xml
        // and ensuring it's not a Hyva theme
        $themeXmlPath = $themePath . '/theme.xml';
        return $this->fileDriver->isExists($themeXmlPath)
            && !$this->fileDriver->isExists($themePath . '/web/tailwind');
    }

    public function getName(): string
    {
        return self::THEME_NAME;
    }
}
