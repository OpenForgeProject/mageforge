/**
 * MageForge Toolbar – Audit factory
 *
 * Reduces boilerplate in audit files. Each audit provides:
 *   - key, icon, label, description (metadata)
 *   - detect(context) → Element[] | { errors: Element[], warnings: Element[] }
 *
 * The factory handles the common activate/deactivate cycle:
 *   clearHighlight → detect → applyHighlight
 *
 * Optional onComplete callback for post-processing (e.g. dynamic descriptions):
 *   onComplete(context, elements)
 */

import { applyHighlight, clearHighlight } from "./highlight.js";

/**
 * @param {{ key: string, icon: string, label: string, description: string }} meta
 * @param {(context: object) => Element[] | { errors: Element[], warnings: Element[] }} detect - Returns elements to highlight
 * @param {(context: object, elements: Element[] | { errors: Element[], warnings: Element[] }) => void} [onComplete] - Optional post-processing callback
 * @returns {{ key: string, icon: string, label: string, description: string, run: (context: object, active: boolean) => void }}
 */
export function createAudit(meta, detect, onComplete) {
  const { key, icon, label, description } = meta;

  /** @type {(context: object, active: boolean) => void} */
  const run = (context, active) => {
    if (!active) {
      clearHighlight(key);
      return;
    }

    const result = detect(context);

    // Support error/warning split: { errors: Element[], warnings: Element[] }
    if (
      result &&
      typeof result === "object" &&
      "errors" in result &&
      "warnings" in result
    ) {
      const { errors, warnings } = result;

      const hasErrors = errors.length > 0;
      const hasWarnings = warnings.length > 0;
      const total = errors.length + warnings.length;

      if (total === 0) {
        context.setAuditCounterBadge(key, "0", "success");
        return;
      }

      if (hasErrors) {
        applyHighlight(errors, key, context, {
          severity: "error",
          autoFindings: true,
          formatFinding: () => ({ action: "Show affected element" }),
        });
      }
      if (hasWarnings) {
        applyHighlight(warnings, key, context, {
          severity: "warning",
          autoFindings: true,
          formatFinding: () => ({ action: "Show affected element" }),
        });
      }

      // Scroll to first issue
      const first = errors[0] ?? warnings[0];
      if (first && !context._batchRunning) {
        first.scrollIntoView({ behavior: "smooth", block: "center" });
      }

      context.setAuditCounterBadge(
        key,
        `${total}`,
        hasErrors ? "error" : "warning",
      );
    } else {
      /** @type {Element[]} */
      const elements = result;

      if (elements.length === 0) {
        context.setAuditCounterBadge(key, "0", "success");
        return;
      }

      applyHighlight(elements, key, context, {
        autoFindings: true,
        formatFinding: () => ({ action: "Show affected element" }),
      });
    }

    onComplete?.(context, result);
  };

  return { key, icon, label, description, run };
}
