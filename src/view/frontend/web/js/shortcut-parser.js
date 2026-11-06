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
 * @property {string} code Physical key code (e.g. "KeyI") for single letters and digits, otherwise empty
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
 * Map a single letter or digit to its physical KeyboardEvent.code.
 *
 * @param {string} key Lower-cased key from the shortcut string.
 * @returns {string} Empty string for all other keys.
 */
function toKeyCode(key) {
  if (/^[a-z]$/.test(key)) return `Key${key.toUpperCase()}`;
  if (/^[0-9]$/.test(key)) return `Digit${key}`;

  return "";
}

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
      code: toKeyCode(key),
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

  // Some events (autofill, synthetic input) carry no key at all
  const key = (event.key || "").toLowerCase();

  // With Alt/Option macOS produces special characters (Option+I => "ˆ"), so
  // fall back to the physical key for letters and digits.
  const keyMatches =
    key === parsed.key ||
    (parsed.alt && parsed.code !== "" && event.code === parsed.code);

  return (
    keyMatches &&
    (event.ctrlKey || event.metaKey) === parsed.ctrlOrMeta &&
    event.shiftKey === parsed.shift &&
    event.altKey === parsed.alt
  );
}
