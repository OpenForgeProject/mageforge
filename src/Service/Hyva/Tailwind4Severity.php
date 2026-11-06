<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

/**
 * Severity of a Tailwind CSS v4 readiness finding
 */
enum Tailwind4Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
