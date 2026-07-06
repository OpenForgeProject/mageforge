<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service;

/**
 * Outcome of a single theme's dependency update attempt
 */
enum DependencyUpdateResult
{
    /**
     * Dependencies were processed successfully
     */
    case Updated;

    /**
     * The theme was intentionally skipped (e.g. vendor-managed or no own package.json)
     */
    case Skipped;

    /**
     * Dependencies could not be processed
     */
    case Failed;
}
