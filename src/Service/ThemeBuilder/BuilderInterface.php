<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

interface BuilderInterface
{
    public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool;
    public function detect(string $themePath): bool;
    public function getName(): string;
}
