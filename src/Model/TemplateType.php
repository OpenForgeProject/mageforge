<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Model;

/**
 * Discriminator for the kind of view file a template reference describes
 */
enum TemplateType: string
{
    case TEMPLATE = 'template';
    case EMAIL = 'email';
    case STATIC = 'static';
}
