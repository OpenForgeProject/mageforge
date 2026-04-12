/**
 * MageForge Toolbar – Audit Registry
 *
 * To add a new audit:
 * 1. Create a file in this directory exporting a default AuditDefinition object
 * 2. Import it here and add it to the `audits` array
 *
 * @typedef {object} AuditDefinition
 * @property {string} key         - Unique identifier
 * @property {string} icon        - Emoji or SVG string shown in menu
 * @property {string} label       - Short display name
 * @property {string} description - Tooltip / subtitle text
 * @property {function(object, boolean): void} run - Audit logic; receives Alpine component as context and active state
 */

import imagesWithoutAlt from './images-without-alt.js';
import imagesWithoutDimensions from './images-without-dimensions.js';
import imagesWithoutLazyLoad from './images-without-lazy-load.js';
import inputsWithoutLabel from './inputs-without-label.js';
import lowContrastText from './low-contrast-text.js';
import tabOrder from './tab-order.js';

/** @type {AuditDefinition[]} */
export const audits = [
    imagesWithoutAlt,
    imagesWithoutDimensions,
    imagesWithoutLazyLoad,
    inputsWithoutLabel,
    lowContrastText,
    tabOrder,
];
