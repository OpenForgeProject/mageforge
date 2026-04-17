<?php

declare(strict_types=1);

namespace Magento\Framework;

/**
 * Stub class for Magento\Framework\Shell
 * Used to allow unit testing without a full Magento installation.
 */
class Shell
{
    /**
     * @param array<mixed> $args
     */
    public function execute(string $command, array $args = []): string
    {
        return '';
    }
}
