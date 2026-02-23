<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder;

class BuilderFactory
{
    /** @var array<string, BuilderInterface> */
    private array $builders = [];

    /**
     * Register a builder by name.
     *
     * @param BuilderInterface $builder
     * @return void
     */
    public function addBuilder(BuilderInterface $builder): void
    {
        $this->builders[$builder->getName()] = $builder;
    }

    /**
     * Create a builder by type name.
     *
     * @param string $type
     * @return BuilderInterface
     */
    public function create(string $type): BuilderInterface
    {
        if (!isset($this->builders[$type])) {
            throw new \InvalidArgumentException("Builder $type not found");
        }

        return $this->builders[$type];
    }

    /**
     * Get a list of available builder names.
     *
     * @return array<string>
     */
    public function getAvailableBuilders(): array
    {
        return array_keys($this->builders);
    }
}
