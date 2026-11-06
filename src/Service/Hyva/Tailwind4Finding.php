<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

/**
 * Single finding of the Tailwind CSS v4 readiness check
 */
class Tailwind4Finding
{
    /**
     * @param Tailwind4Severity $severity
     * @param string $message
     * @param string $hint How to resolve the finding
     * @param string|null $file Path relative to the theme's web/tailwind or the module's tailwind directory
     * @param int|null $line
     */
    public function __construct(
        public readonly Tailwind4Severity $severity,
        public readonly string $message,
        public readonly string $hint = '',
        public readonly ?string $file = null,
        public readonly ?int $line = null,
    ) {
    }

    /**
     * Get the finding location as "file:line"
     *
     * @return string
     */
    public function getLocation(): string
    {
        if ($this->file === null) {
            return '';
        }

        return $this->line === null ? $this->file : $this->file . ':' . $this->line;
    }
}
