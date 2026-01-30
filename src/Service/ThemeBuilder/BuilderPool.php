<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder;

class BuilderPool
{
    /**
     * @param BuilderInterface[] $builders
     */
    public function __construct(
        private readonly array $builders = []
    ) {
    }

    public function getBuilder(string $themePath): ?BuilderInterface
    {
        foreach ($this->builders as $builder) {
            if ($builder->detect($themePath)) {
                return $builder;
            }
        }

        return null;
    }

    /**
     * @return BuilderInterface[]
     */
    public function getBuilders(): array
    {
        return $this->builders;
    }
}
