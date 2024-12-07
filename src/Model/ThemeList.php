<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\View\Design\Theme\ThemeList as MagentoThemeList;

class ThemeList
{
    public function __construct(
        private readonly MagentoThemeList $magentoThemeList,
    ) {}

    public function getAllThemes(): array
    {
        return $this->magentoThemeList->getItems();
    }
}
