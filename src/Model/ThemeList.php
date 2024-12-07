<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

use Magento\Framework\View\Design\Theme\ThemeList as MagentoThemeList;

class ThemeList
{
<<<<<<< HEAD
    /**
     * Constructor
     *
     * @param MagentoThemeList $magentoThemeList
     */
    public function __construct(
        private readonly MagentoThemeList $magentoThemeList
    ) {
    }

    /**
     * Get all themes
     *
     * @return array
     */
=======
    public function __construct(
        private readonly MagentoThemeList $magentoThemeList,
    ) {}

>>>>>>> 46cb511 (add ListThemesCommand)
    public function getAllThemes(): array
    {
        return $this->magentoThemeList->getItems();
    }
}
