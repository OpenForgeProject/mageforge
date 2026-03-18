<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\View\Design\Theme\ThemeList as MagentoThemeList;
use Magento\Framework\View\Design\ThemeInterface;

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
     * @return array<ThemeInterface>
     */
    public function getAllThemes(): array
    {
        return $this->magentoThemeList->getItems();
    }
}
