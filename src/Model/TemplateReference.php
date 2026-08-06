<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

/**
 * Value object describing a module template, e.g. "Magento_Catalog::product/view/details.phtml"
 */
class TemplateReference
{
    /**
     * @param string $moduleName Module name in Vendor_Module notation
     * @param string $templatePath Path relative to the module's templates directory
     * @param TemplateType $type Whether this is a block template or an email template
     */
    public function __construct(
        private readonly string $moduleName,
        private readonly string $templatePath,
        private readonly TemplateType $type = TemplateType::TEMPLATE,
    ) {
    }

    /**
     * Get the module name in Vendor_Module notation
     *
     * @return string
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Get the template path relative to the templates directory
     *
     * @return string
     */
    public function getTemplatePath(): string
    {
        return $this->templatePath;
    }

    /**
     * Get the template type (block template or email template)
     *
     * @return TemplateType
     */
    public function getType(): TemplateType
    {
        return $this->type;
    }

    /**
     * Get the full template identifier in Module_Name::path/to/template.phtml notation
     *
     * @return string
     */
    public function getTemplateId(): string
    {
        return $this->moduleName . '::' . $this->templatePath;
    }
}
