/**
 * MageForge Toolbar Audit – Duplicate IDs
 *
 * IDs must be unique per document. Duplicate IDs break label associations,
 * aria-labelledby / aria-describedby references, fragment links, and cause
 * unpredictable behaviour with JavaScript querySelector.
 *
 * Icon source: Tabler Icons (MIT)
 */

import { createAudit } from "./createAudit.js";

export default createAudit(
  {
    key: "duplicate-ids",
    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 8a4 4 0 0 1 4 4a4 4 0 0 1 4 -4"></path><path d="M7 16a4 4 0 0 0 4 -4a4 4 0 0 0 4 4"></path><path d="M9 12h6"></path><path d="M3 12h2"></path><path d="M19 12h2"></path></svg>',
    label: "Duplicate IDs",
    description:
      "Highlight elements sharing an ID with at least one other element",
  },
  () => {
    /** @type {Map<string, Element[]>} */
    const idMap = new Map();

    document.querySelectorAll("[id]").forEach((el) => {
      const id = el.id;
      if (!id) return;
      if (el.closest(".mageforge-toolbar")) return;
      if (!idMap.has(id)) {
        idMap.set(id, []);
      }
      idMap.get(id).push(el);
    });

    /** @type {Element[]} */
    const duplicates = [];
    idMap.forEach((els) => {
      if (els.length > 1) {
        els.forEach((el) => duplicates.push(el));
      }
    });

    return duplicates;
  },
  (context, findings) => {
    if (findings.length > 0) {
      const idMap = new Map();
      document.querySelectorAll("[id]").forEach((el) => {
        if (el.closest(".mageforge-toolbar")) return;
        if (!idMap.has(el.id)) idMap.set(el.id, []);
        idMap.get(el.id).push(el);
      });
      const duplicateIdNames = [];
      idMap.forEach((els, id) => {
        if (els.length > 1) {
          duplicateIdNames.push(`#${id} (×${els.length})`);
        }
      });
      context.setAuditDescription(
        "duplicate-ids",
        `Duplicate: ${duplicateIdNames.join(", ")}`,
      );
    } else {
      context.setAuditDescription(
        "duplicate-ids",
        "Highlight elements sharing an ID with at least one other element",
      );
    }
  },
);
