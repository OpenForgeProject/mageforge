/**
 * MageForge Toolbar – Audit Registry
 *
 * To add a new audit:
 * 1. Create a file in this directory exporting a default AuditDefinition object
 * 2. Import it here and add it to the `audits` array (with an optional `group` key)
 *
 * To add a new group:
 * 1. Add an entry to `auditGroups`
 * 2. Set `group: '<key>'` on the relevant audits below
 *
 * @typedef {object} AuditDefinition
 * @property {string}  key         - Unique identifier
 * @property {string}  icon        - Emoji or SVG string shown in menu
 * @property {string}  label       - Short display name
 * @property {string}  description - Tooltip / subtitle text
 * @property {string}  [group]     - Optional group key (must match an AuditGroup key)
 * @property {function(object, boolean): void} run - Audit logic; receives Alpine component as context and active state
 *
 * @typedef {object} AuditGroup
 * @property {string} key   - Unique group identifier
 * @property {string} label - Display name shown as group header
 */

import buttonsWithoutType from './buttons-without-type.js';
import duplicateIds from './duplicate-ids.js';
import emptyInteractive from './empty-interactive.js';
import imagesWithoutAlt from './images-without-alt.js';
import imagesWithoutDimensions from './images-without-dimensions.js';
import imagesWithoutLazyLoad from './images-without-lazy-load.js';
import inputsWithoutLabel from './inputs-without-label.js';
import lowContrastText from './low-contrast-text.js';
import multipleH1 from './multiple-h1.js';
import smallTouchTargets from './small-touch-targets.js';
import tabOrder from './tab-order.js';
import unsafeBlankTarget from './unsafe-blank-target.js';

/** @type {AuditGroup[]} */
export const auditGroups = [
    { key: 'wcag',         label: 'WCAG Checks' },
    { key: 'html-quality', label: 'HTML Quality' },
    { key: 'performance',  label: 'Performance' },
];

/** @type {AuditDefinition[]} */
export const audits = [
    { ...imagesWithoutAlt,        group: 'wcag' },
    { ...inputsWithoutLabel,      group: 'wcag' },
    { ...lowContrastText,         group: 'wcag' },
    { ...tabOrder,                group: 'wcag' },
    { ...multipleH1,              group: 'wcag' },
    { ...emptyInteractive,        group: 'wcag' },
    { ...buttonsWithoutType,      group: 'wcag' },
    { ...smallTouchTargets,       group: 'wcag' },
    { ...duplicateIds,            group: 'html-quality' },
    { ...unsafeBlankTarget,       group: 'html-quality' },
    { ...imagesWithoutDimensions, group: 'performance' },
    { ...imagesWithoutLazyLoad,   group: 'performance' },
];
