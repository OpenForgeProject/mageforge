<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\Config;

class TemplateOverride
{
    public const XML_PATH_ADD_HEADER = 'mageforge/template_override/add_header';

    // @mago-format-ignore-start
    // Kept multi-line so PHPCS' 120-character limit is respected; Mago would re-fold them.
    public const XML_PATH_SOURCE_HEADER_INCLUDE_DATE
        = 'mageforge/template_override/source_header_include_date';
    public const XML_PATH_SOURCE_HEADER_INCLUDE_MODULE_VERSION
        = 'mageforge/template_override/source_header_include_module_version';
    public const XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_PATH
        = 'mageforge/template_override/source_header_include_source_path';
    public const XML_PATH_SOURCE_HEADER_INCLUDE_SOURCE_MODULE
        = 'mageforge/template_override/source_header_include_source_module';
    public const XML_PATH_SOURCE_HEADER_INCLUDE_OVERRIDE_FOR
        = 'mageforge/template_override/source_header_include_override_for';
    public const XML_PATH_SOURCE_HEADER_ENABLE_PHTML
        = 'mageforge/template_override/source_header_enable_phtml';
    public const XML_PATH_SOURCE_HEADER_ENABLE_HTML
        = 'mageforge/template_override/source_header_enable_html';
    public const XML_PATH_SOURCE_HEADER_ENABLE_XML
        = 'mageforge/template_override/source_header_enable_xml';
    public const XML_PATH_SOURCE_HEADER_ENABLE_WEB_ASSETS
        = 'mageforge/template_override/source_header_enable_web_assets';
    public const XML_PATH_SOURCE_HEADER_ENABLE_SHELL
        = 'mageforge/template_override/source_header_enable_shell';
    // @mago-format-ignore-end

    /**
     * Store scope type.
     *
     * Equivalent to \Magento\Store\Model\ScopeInterface::SCOPE_STORE, duplicated here so
     * MageForge does not need a hard dependency on the Magento_Store module just for this
     * string constant.
     */
    public const SCOPE_STORE = 'store';
}
