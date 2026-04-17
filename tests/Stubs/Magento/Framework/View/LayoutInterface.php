<?php

declare(strict_types=1);

namespace Magento\Framework\View;

/**
 * Stub interface for Magento\Framework\View\LayoutInterface
 */
interface LayoutInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getAllBlocks(): array;
}
