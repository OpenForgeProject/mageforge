<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

/**
 * Reason why a theme override file is considered obsolete
 */
enum ObsoleteReason: string
{
    case IDENTICAL = 'identical';
    case ORPHANED = 'orphaned';
    case UNUSED = 'unused';
    case OUTDATED = 'outdated';

    /**
     * Get a human readable label
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::IDENTICAL => 'Identical',
            self::ORPHANED => 'Orphaned',
            self::UNUSED => 'Unused',
            self::OUTDATED => 'Outdated',
        };
    }
}
