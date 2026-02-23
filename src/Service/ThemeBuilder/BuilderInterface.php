<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeBuilder;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

interface BuilderInterface
{
    /**
     * Detect whether the builder can handle the theme at the given path.
     *
     * @param string $themePath
     * @return bool
     */
    public function detect(string $themePath): bool;

    /**
     * Build the theme assets.
     *
     * @param string $themeCode
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function build(
        string $themeCode,
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool;

    /**
     * Get the builder name used for registration.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Repair missing dependencies or setup prior to build.
     *
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function autoRepair(
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool;

    /**
     * Run the theme watch process.
     *
     * @param string $themeCode
     * @param string $themePath
     * @param SymfonyStyle $io
     * @param OutputInterface $output
     * @param bool $isVerbose
     * @return bool
     */
    public function watch(
        string $themeCode,
        string $themePath,
        SymfonyStyle $io,
        OutputInterface $output,
        bool $isVerbose
    ): bool;
}
