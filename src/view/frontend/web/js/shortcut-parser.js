/**
 * MageForge Shortcut Parser
 *
 * Parses human-readable shortcut strings (e.g. "Ctrl+Shift+A", "Shift+F8",
 * "F12", "none") and matches them against a native KeyboardEvent.
 *
 * Supported modifiers: Ctrl, Cmd, Meta, Shift, Alt, Option.
 * "Cmd" and "Meta" are treated as equivalent to "Ctrl" because browsers map
 * Cmd on macOS to metaKey and Ctrl on Windows/Linux to ctrlKey.
 */

/**
 * @typedef {object} ParsedShortcut
 * @property {string} key
 * @property {boolean} ctrlOrMeta
 * @property {boolean} shift
 * @property {boolean} alt
 */

/**
 * Memoised parse results keyed by the normalised shortcut string, so global
 * keydown handlers do not re-split and re-lowercase the same shortcut on
 * every keypress.
 *
 * @type {Map<string, ParsedShortcut|null>}
 */
const parsedShortcutCache = new Map();

/**
 * Parse a shortcut string into its components.
 *
 * Results are memoised by normalised shortcut string.
 *
 * @param {string} shortcut
 * @returns {ParsedShortcut|null} Null when shortcut is "none" or empty.
 */
export function parseShortcut(shortcut) {
  const normalised = (shortcut || "").trim().toLowerCase();

  if (parsedShortcutCache.has(normalised)) {
    return parsedShortcutCache.get(normalised);
  }

  let parsed = null;
  if (normalised !== "" && normalised !== "none") {
    const parts = normalised.split("+").map((part) => part.trim());
    const key = parts.pop() || "";

    parsed = {
      key,
      ctrlOrMeta:
        parts.includes("ctrl") ||
        parts.includes("cmd") ||
        parts.includes("meta"),
      shift: parts.includes("shift"),
      alt: parts.includes("alt") || parts.includes("option"),
    };
  }

  parsedShortcutCache.set(normalised, parsed);

  return parsed;
}

/**
 * Check whether a keyboard event matches the configured shortcut.
 *
 * @param {KeyboardEvent} event
 * @param {string} shortcut
 * @returns {boolean}
 */
export function matchesShortcut(event, shortcut) {
  const parsed = parseShortcut(shortcut);
  if (!parsed) return false;

  return (
    event.key.toLowerCase() === parsed.key &&
    (event.ctrlKey || event.metaKey) === parsed.ctrlOrMeta &&
    event.shiftKey === parsed.shift &&
    event.altKey === parsed.alt
  );
}
