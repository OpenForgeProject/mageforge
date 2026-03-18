<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\View\Design\Theme\ThemeList as MagentoThemeList;
use Magento\Theme\Model\Theme;

class ThemeList
{
    /**
     * Constructor
     *
     * @param MagentoThemeList $magentoThemeList
     */
    public function __construct(
        private readonly MagentoThemeList $magentoThemeList,
    ) {
    }

    /**
     * Get all themes
     *
     * @return array<int, Theme>
     */
    public function getAllThemes(): array
    {
        /** @var array<int, Theme> $items */
        $items = $this->magentoThemeList->getItems();
        return $items;
    }
}
