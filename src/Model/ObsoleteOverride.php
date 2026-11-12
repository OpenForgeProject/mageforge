<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

/**
 * Value object describing a theme override file that is likely obsolete
 */
class ObsoleteOverride
{
    /**
     * @param string $themeCode Theme the override belongs to
     * @param string $file Absolute path of the override file
     * @param string $templateId Identifier in Module_Name::path/to/file notation
     * @param ObsoleteReason $reason Why the override is considered obsolete
     * @param string $details Short explanation shown to the user
     * @param bool $safeToDelete Whether the file can be removed without changing the rendered result
     */
    public function __construct(
        private readonly string $themeCode,
        private readonly string $file,
        private readonly string $templateId,
        private readonly ObsoleteReason $reason,
        private readonly string $details,
        private readonly bool $safeToDelete,
    ) {
    }

    /**
     * Get the theme code
     *
     * @return string
     */
    public function getThemeCode(): string
    {
        return $this->themeCode;
    }

    /**
     * Get the absolute override file path
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the template identifier
     *
     * @return string
     */
    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    /**
     * Get the reason
     *
     * @return ObsoleteReason
     */
    public function getReason(): ObsoleteReason
    {
        return $this->reason;
    }

    /**
     * Get the explanation
     *
     * @return string
     */
    public function getDetails(): string
    {
        return $this->details;
    }

    /**
     * Check whether deleting the file leaves the rendered result unchanged
     *
     * @return bool
     */
    public function isSafeToDelete(): bool
    {
        return $this->safeToDelete;
    }
}
