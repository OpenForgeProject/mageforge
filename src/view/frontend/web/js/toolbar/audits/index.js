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
 * @property {function(object): void} run - Audit logic; receives Alpine component as context
 */

import imagesWithoutAlt from './images-without-alt.js';
import tabOrder from './tab-order.js';

/** @type {AuditDefinition[]} */
export const audits = [
    imagesWithoutAlt,
    tabOrder,
];
