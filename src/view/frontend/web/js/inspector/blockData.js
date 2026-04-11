/**
 * MageForge Inspector - Block Data Storage
 *
 * Shared WeakMap for associating block metadata with DOM elements.
 * Using WeakMap avoids polluting the HTMLElement namespace and allows
 * garbage collection when elements are removed from the DOM.
 */

export const blockDataMap = new WeakMap();
