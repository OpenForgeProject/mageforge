<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder;

abstract class AbstractBuilder implements BuilderInterface
{
    public function __construct(
        protected readonly DetectorInterface $detector,
        protected readonly CompilerInterface $compiler
    ) {
    }

    abstract public function getName(): string;
}
