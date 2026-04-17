<?php

declare(strict_types=1);

namespace Magento\Framework\App;

/**
 * Stub class for Magento\Framework\App\State
 */
class State
{
    public const MODE_DEFAULT = 'default';
    public const MODE_DEVELOPER = 'developer';
    public const MODE_PRODUCTION = 'production';

    public function getMode(): string
    {
        return self::MODE_DEFAULT;
    }
}
