<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder;

class BuilderFactory
{
    private array $builders = [];

    public function addBuilder(BuilderInterface $builder): void
    {
        $this->builders[$builder->getName()] = $builder;
    }

    public function create(string $type): BuilderInterface
    {
        if (!isset($this->builders[$type])) {
            throw new \InvalidArgumentException("Builder $type not found");
        }

        return $this->builders[$type];
    }

    public function getAvailableBuilders(): array
    {
        return array_keys($this->builders);
    }
}
