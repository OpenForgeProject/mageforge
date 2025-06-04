<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\ThemeChecker\Custom;

use OpenForgeProject\MageForge\Service\ThemeChecker\MagentoStandard\Checker as StandardChecker;

class Checker extends StandardChecker
{
    /**
     * {@inheritdoc}
     */
    public function detect(string $themePath): bool
    {
        // This is a fallback checker, it should have the lowest priority
        // and only be used if no other checker matches
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Custom Theme';
    }
}
