<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

/**
 * An ancestor in a theme's inheritance chain and why it is not built (if applicable)
 */
class ThemeAncestor
{
    /**
     * @param string $code
     * @param string|null $skipReason
     */
    public function __construct(
        public readonly string $code,
        public readonly ?string $skipReason = null,
    ) {
    }
}
