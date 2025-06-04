<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker;

class CheckerPool
{
    /** @var CheckerInterface[] */
    private array $checkers;

    /**
     * @param CheckerInterface[] $checkers
     */
    public function __construct(
        array $checkers = []
    ) {
        $this->checkers = $checkers;
    }

    /**
     * Get appropriate checker for a theme
     *
     * @param string $themePath
     * @return CheckerInterface|null
     */
    public function getChecker(string $themePath): ?CheckerInterface
    {
        foreach ($this->checkers as $checker) {
            if ($checker->detect($themePath)) {
                return $checker;
            }
        }

        return null;
    }

    /**
     * Get all registered checkers
     *
     * @return CheckerInterface[]
     */
    public function getCheckers(): array
    {
        return $this->checkers;
    }
}
