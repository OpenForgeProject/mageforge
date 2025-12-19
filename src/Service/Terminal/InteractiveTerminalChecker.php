<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Terminal;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Service for checking if the terminal supports interactive input
 */
class InteractiveTerminalChecker
{
    public function __construct(
        private readonly TerminalEnvironmentService $environmentService
    ) {
    }

    /**
     * Check if the current environment supports interactive terminal input
     */
    public function isInteractiveTerminal(OutputInterface $output): bool
    {
        if (!$output->isDecorated()) {
            return false;
        }

        if (!defined('STDIN') || !is_resource(STDIN)) {
            return false;
        }

        $nonInteractiveEnvs = [
            'CI',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'TEAMCITY_VERSION',
        ];

        foreach ($nonInteractiveEnvs as $env) {
            if ($this->environmentService->getEnvVar($env) || $this->environmentService->getServerVar($env)) {
                return false;
            }
        }

        $sttyOutput = shell_exec('stty -g 2>/dev/null');
        return !empty($sttyOutput);
    }
}
