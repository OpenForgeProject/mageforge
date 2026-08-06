<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model\Config;

class TemplateOverride
{
    public const XML_PATH_ADD_HEADER = 'mageforge/template_override/add_header';

    /**
     * Store scope type.
     *
     * Equivalent to \Magento\Store\Model\ScopeInterface::SCOPE_STORE, duplicated here so
     * MageForge does not need a hard dependency on the Magento_Store module just for this
     * string constant.
     */
    public const SCOPE_STORE = 'store';
}
