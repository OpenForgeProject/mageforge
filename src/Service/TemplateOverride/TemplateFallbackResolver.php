<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Design\Fallback\RulePool;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use OpenForgeProject\MageForge\Model\TemplateReference;
use OpenForgeProject\MageForge\Model\TemplateType;

/**
 * Determines template fallback directories exactly as Magento does at runtime
 *
 * Uses Magento's own template fallback rule (RulePool), so any installed plugins - such as
 * Hyvä's compat module fallback - are taken into account. The target area's DI configuration
 * must be loaded first (see AreaEmulator) for such plugins to be active.
 */
class TemplateFallbackResolver
{
    /**
     * @param ObjectManagerInterface $objectManager
     * @param DesignInterface $design
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param File $fileDriver
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly DesignInterface $design,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly File $fileDriver,
    ) {
    }

    /**
     * Get the ordered list of directories Magento searches for the given template
     *
     * @param TemplateReference $reference
     * @param ThemeInterface $theme
     * @return string[]
     */
    public function getFallbackDirs(TemplateReference $reference, ThemeInterface $theme): array
    {
        $area = $theme->getArea();

        if ($reference->getType() === TemplateType::LAYOUT) {
            return $this->getLayoutFallbackDirs($reference, $theme, $area);
        }

        // Fallback plugins like Hyvä's compat module fallback check the "current" design theme
        $this->design->setDesignTheme($theme, $area);

        // A fresh RulePool instance is created so that fallback rule plugins registered in the
        // area's DI configuration (loaded after the CLI booted) are applied.
        $rulePool = $this->objectManager->create(RulePool::class);
        if (!$rulePool instanceof RulePool) {
            throw new \RuntimeException('Could not create the view fallback rule pool.');
        }

        $ruleType = match ($reference->getType()) {
            TemplateType::EMAIL => RulePool::TYPE_EMAIL_TEMPLATE,
            TemplateType::STATIC => RulePool::TYPE_STATIC_FILE,
            default => RulePool::TYPE_TEMPLATE_FILE,
        };

        $dirs = $rulePool
            ->getRule($ruleType)
            ->getPatternDirs([
                'area' => $area,
                'theme' => $theme,
                'module_name' => $reference->getModuleName(),
                'file' => $reference->getTemplatePath(),
            ]);

        $result = [];
        foreach ($dirs as $dir) {
            if (is_string($dir) && !in_array($dir, $result, true)) {
                $result[] = $dir;
            }
        }

        return $result;
    }

    /**
     * Build fallback directories for layout XML files
     *
     * Magento does not expose a dedicated layout fallback rule in RulePool. Layout files are
     * resolved via the View File Locator with a fixed directory pattern:
     * <theme_dir>/<module_name>/layout plus <module_dir>/view/<area>/layout and base.
     *
     * @param TemplateReference $reference
     * @param ThemeInterface $theme
     * @param string $area
     * @return string[]
     */
    private function getLayoutFallbackDirs(TemplateReference $reference, ThemeInterface $theme, string $area): array
    {
        $moduleName = $reference->getModuleName();
        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $theme->getFullPath());
        $parentTheme = $theme->getParentTheme();

        $dirs = [];

        if ($themePath !== null) {
            $dirs[] = $themePath . '/' . $moduleName . '/layout';
        }

        if ($parentTheme instanceof ThemeInterface) {
            $parentThemePath = $this->componentRegistrar->getPath(
                ComponentRegistrar::THEME,
                $parentTheme->getFullPath(),
            );
            if ($parentThemePath !== null) {
                $dirs[] = $parentThemePath . '/' . $moduleName . '/layout';
            }
        }

        if ($modulePath !== null) {
            $dirs[] = $modulePath . '/view/' . $area . '/layout';
            $dirs[] = $modulePath . '/view/base/layout';
        }

        return $dirs;
    }

    /**
     * Find the first existing file for the template within the fallback directories
     *
     * This mirrors how Magento resolves a template: the first directory in the fallback order
     * containing the file wins. An optional directory can be excluded from the search to find
     * the file that would be used if the excluded location did not exist.
     *
     * @param string[] $fallbackDirs
     * @param string $templatePath
     * @param string|null $excludedDir
     * @return string|null
     */
    public function findFirstExistingFile(
        array $fallbackDirs,
        string $templatePath,
        ?string $excludedDir = null,
    ): ?string {
        foreach ($fallbackDirs as $dir) {
            if ($dir === $excludedDir) {
                continue;
            }
            $file = $dir . '/' . $templatePath;
            if ($this->fileDriver->isFile($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Get the fallback directory located within the given theme itself (the override target)
     *
     * @param string[] $fallbackDirs
     * @param ThemeInterface $theme
     * @return string|null
     */
    public function getThemeTargetDir(array $fallbackDirs, ThemeInterface $theme): ?string
    {
        $themePath = $this->componentRegistrar->getPath(ComponentRegistrar::THEME, $theme->getFullPath());
        if ($themePath === null) {
            return null;
        }

        $themePath = rtrim(str_replace('\\', '/', $themePath), '/');
        foreach ($fallbackDirs as $dir) {
            $normalizedDir = str_replace('\\', '/', $dir);
            if ($normalizedDir === $themePath || str_starts_with($normalizedDir, $themePath . '/')) {
                return $dir;
            }
        }

        return null;
    }
}
