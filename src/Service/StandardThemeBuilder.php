<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StandardThemeBuilder
{
    /**
     * Create a standard theme builder.
     *
     * @param DependencyChecker $dependencyChecker
     * @param GruntTaskRunner $gruntTaskRunner
     * @param StaticContentDeployer $staticContentDeployer
     */
    public function __construct(
        private readonly DependencyChecker $dependencyChecker,
        private readonly GruntTaskRunner $gruntTaskRunner,
        private readonly StaticContentDeployer $staticContentDeployer,
    ) {
    }

    /**
     * Build assets for a standard Magento theme.
     *
     * @param string $themeCode
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @param array<string> $successList
     * @return bool
     */
    public function build(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        array &$successList,
    ): bool {
        // Check dependencies
        if (!$this->dependencyChecker->checkDependencies($io, $isVerbose)) {
            return false;
        }
        $successList[] = "$themeCode: Dependencies checked";

        // Run Grunt tasks (only once per build process)
        static $gruntTasksRun = false;
        if (!$gruntTasksRun) {
            if (!$this->gruntTaskRunner->runTasks($io, $output, $isVerbose)) {
                return false;
            }
            $successList[] = 'Global: Grunt tasks executed';
            $gruntTasksRun = true;
        }

        // Deploy static content
        if (!$this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose)) {
            return false;
        }
        $successList[] = "$themeCode: Static content deployed";

        if ($isVerbose) {
            $io->success("Theme $themeCode has been successfully built.");
        }

        return true;
    }
}
