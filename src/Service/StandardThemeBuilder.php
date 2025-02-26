<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

class StandardThemeBuilder
{
    public function __construct(
        private readonly DependencyChecker $dependencyChecker,
        private readonly GruntTaskRunner $gruntTaskRunner,
        private readonly StaticContentDeployer $staticContentDeployer
    ) {
    }

    public function build(
        string $themeCode,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose,
        array &$successList
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
            $successList[] = "Global: Grunt tasks executed";
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
