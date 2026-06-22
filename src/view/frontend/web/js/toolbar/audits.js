/**
 * MageForge Toolbar - Audit dispatcher
 */

import { audits, auditGroups } from "./audits/index.js";

// Allow async DOM mutations to settle; 200ms gives async audits enough
// time to finish after synchronous mutations (typically 1–2 frames = 16ms).
const AUDIT_SETTLE_DELAY_MS = 200;

export const auditMethods = {
  /**
   * Toggles an audit on/off and updates the menu item state.
   *
   * @param {string} auditKey
   */
  runAudit(auditKey) {
    const audit = audits.find((a) => a.key === auditKey);
    if (!audit) return;

    const isActive = this.activeAudits.has(auditKey);
    if (isActive) {
      this.activeAudits.delete(auditKey);
      try {
        audit.run(this, false);
      } catch (err) {
        console.warn(
          `[MageForge] Audit "${auditKey}" failed on deactivate:`,
          err,
        );
      }
      this.setAuditCounterBadge(auditKey, "", "success");
    } else {
      this.activeAudits.add(auditKey);
      try {
        audit.run(this, true);
      } catch (err) {
        console.warn(
          `[MageForge] Audit "${auditKey}" failed on activate:`,
          err,
        );
        this.activeAudits.delete(auditKey);
      }
    }
    this.setAuditActive(auditKey, !isActive);
  },

  /**
   * Activates all inactive audits or deactivates all if all are already active.
   */
  toggleAllAudits() {
    const allActive = this.activeAudits.size === audits.length;
    if (allActive) {
      this.deactivateAllAudits();
    } else {
      audits.forEach((audit) => {
        if (!this.activeAudits.has(audit.key)) {
          this.runAudit(audit.key);
        }
      });
    }
  },

  /**
   * Calculate a 0–100 score for the given audit list based on current DOM state.
   *
   * @param {import('./audits/index.js').AuditDefinition[]} auditList
   * @returns {number}
   */
  _calcScore(auditList) {
    let total = 0;
    let max = 0;
    auditList.forEach((audit) => {
      const item = this.menu?.querySelector(`[data-audit-key="${audit.key}"]`);
      if (!item) return;
      max += 100;
      const status = item.querySelector(".mageforge-toolbar-menu-status");
      if (!status || !status.textContent.trim()) {
        total += 100;
      } else if (
        status.classList.contains("mageforge-toolbar-menu-status--success")
      ) {
        total += 100;
      } else if (
        status.classList.contains("mageforge-toolbar-menu-status--warning")
      ) {
        total += 50;
      }
    });
    return max > 0 ? Math.round((total / max) * 100) : 100;
  },

  /**
   * Run every audit, wait for the DOM to settle, then compute and display
   * an overall health score (0–100) in the footer gauge.
   */
  async runAllAuditsForScore() {
    if (this._batchRunning) return;
    const btn = this.runAllButton;
    if (!btn) return;
    btn.disabled = true;
    btn.classList.add("mageforge-running");

    try {
      this._batchRunning = true;
      this.deactivateAllAudits();

      audits.forEach((audit) => {
        if (!this.activeAudits.has(audit.key)) {
          this.runAudit(audit.key);
        }
      });

      // Allow async DOM mutations to settle
      await new Promise((resolve) =>
        setTimeout(resolve, AUDIT_SETTLE_DELAY_MS),
      );

      this.updateHealthScore(this._calcScore(audits));

      // Update per-group scores on the dashboard
      const grouped = {};
      audits.forEach((a) => {
        if (a.group) (grouped[a.group] = grouped[a.group] ?? []).push(a);
      });
      Object.entries(grouped).forEach(([groupKey, groupAudits]) => {
        this.updateGroupScore(groupKey, this._calcScore(groupAudits));
      });

      this.updateHomeSummary();
    } finally {
      this._batchRunning = false;
      btn.disabled = false;
      btn.classList.remove("mageforge-running");
    }
  },

  /**
   * Run all audits for a specific group, wait for the DOM to settle, then
   * compute and display a score (0–100) in that panel's ring.
   */
  async runGroupAuditsForScore(groupKey) {
    if (this._batchRunning) return;
    const btn = this[`runGroupButton-${groupKey}`];
    if (!btn) return;
    btn.disabled = true;
    btn.classList.add("mageforge-running");

    try {
      this._batchRunning = true;
      const groupAudits = audits.filter((a) => a.group === groupKey);

      // Deactivate existing audits in this group
      groupAudits.forEach((audit) => {
        if (this.activeAudits.has(audit.key)) {
          this.activeAudits.delete(audit.key);
          audit.run(this, false);
        }
      });

      // Run all audits in the group
      groupAudits.forEach((audit) => {
        if (!this.activeAudits.has(audit.key)) {
          this.runAudit(audit.key);
        }
      });

      // Allow async DOM mutations to settle
      await new Promise((resolve) =>
        setTimeout(resolve, AUDIT_SETTLE_DELAY_MS),
      );

      this.updateGroupScore(groupKey, this._calcScore(groupAudits));
      this.updateHomeSummary();
    } finally {
      this._batchRunning = false;
      btn.disabled = false;
      btn.classList.remove("mageforge-running");
    }
  },

  /**
   * Reset all audits for a specific group (deactivate + hide score).
   */
  resetGroupAudits(groupKey) {
    const groupAudits = audits.filter((a) => a.group === groupKey);
    groupAudits.forEach((audit) => {
      if (this.activeAudits.has(audit.key)) {
        this.activeAudits.delete(audit.key);
        audit.run(this, false);
        this.setAuditCounterBadge(audit.key, "", "success");
        this.setAuditActive(audit.key, false);
      }
    });

    // Reset score ring
    this.updateGroupScore(groupKey, 0);
  },

  /**
   * Deactivates all currently active audits (called when closing the toolbar).
   */
  deactivateAllAudits() {
    const keys = [...this.activeAudits];
    keys.forEach((key) => {
      this.activeAudits.delete(key);
      const audit = audits.find((a) => a.key === key);
      if (audit) audit.run(this, false);
      this.setAuditCounterBadge(key, "", "success");
      this.setAuditActive(key, false);
    });

    this.updateToggleAllButton();
  },

  /**
   * Returns all registered audits (used by UI to build menu items)
   *
   * @returns {import('./audits/index.js').AuditDefinition[]}
   */
  getAudits() {
    return audits;
  },

  /**
   * Returns all registered audit groups.
   *
   * @returns {import('./audits/index.js').AuditGroup[]}
   */
  getAuditGroups() {
    return auditGroups;
  },

  /**
   * Collect the current audit state into a plain data structure shared by
   * all export formatters.
   *
   * @returns {{ timestamp: string, url: string, audits: Array<object> }}
   */
  _collectExportData() {
    const data = {
      timestamp: new Date().toISOString(),
      url: location.href,
      audits: [],
    };
    audits.forEach((audit) => {
      if (!this.activeAudits.has(audit.key)) return;
      const item = this.menu?.querySelector(`[data-audit-key="${audit.key}"]`);
      if (!item) return;

      // Extract per-element selectors stored in the findings list DOM
      const findings = Array.from(
        item.querySelectorAll(".mageforge-audit-finding"),
      ).map((row) => ({
        selector:
          row
            .querySelector(".mageforge-finding-selector")
            ?.textContent?.trim() ?? "",
        severity: row.classList.contains("mageforge-audit-finding--warning")
          ? "warning"
          : "error",
      }));

      data.audits.push({
        key: audit.key,
        label: audit.label,
        group: audit.group ?? null,
        errors: parseInt(item.dataset.findingErrors || "0", 10),
        warnings: parseInt(item.dataset.findingWarnings || "0", 10),
        badge:
          item
            .querySelector(".mageforge-toolbar-menu-status")
            ?.textContent?.trim() ?? "",
        findings,
      });
    });
    return data;
  },

  /**
   * Export all active audit findings in the given format.
   *
   * @param {'json'|'md'|'txt'} [format='json']
   */
  exportFindings(format = "json") {
    const data = this._collectExportData();
    const ts = Date.now();
    let content, mimeType, ext;

    if (format === "md") {
      content = this._exportAsMd(data);
      mimeType = "text/markdown";
      ext = "md";
    } else if (format === "txt") {
      content = this._exportAsTxt(data);
      mimeType = "text/plain";
      ext = "txt";
    } else {
      content = JSON.stringify(data, null, 2);
      mimeType = "application/json";
      ext = "json";
    }

    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `mageforge-audit-${ts}.${ext}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  },

  /**
   * Format audit data as Markdown.
   *
   * @param {object} data
   * @returns {string}
   */
  _exportAsMd(data) {
    const lines = [
      "# MageForge Audit Report",
      "",
      `**Date:** ${data.timestamp}`,
      `**URL:** ${data.url}`,
      "",
      "## Summary",
      "",
    ];

    if (!data.audits.length) {
      lines.push("_No active audits._");
    } else {
      lines.push("| Audit | Group | Errors | Warnings |");
      lines.push("|-------|-------|-------:|---------:|");
      data.audits.forEach(({ label, group, errors, warnings }) => {
        lines.push(`| ${label} | ${group ?? "—"} | ${errors} | ${warnings} |`);
      });

      const totalErrors = data.audits.reduce((s, a) => s + a.errors, 0);
      const totalWarnings = data.audits.reduce((s, a) => s + a.warnings, 0);
      lines.push(
        "",
        `**Total errors:** ${totalErrors} · **Total warnings:** ${totalWarnings}`,
        "",
        "## Details",
      );

      data.audits.forEach(
        ({ label, group, errors, warnings, badge, findings }) => {
          const icon = errors > 0 ? "❌" : warnings > 0 ? "⚠️" : "✅";
          const groupNote = group ? ` \`${group}\`` : "";
          lines.push("", `### ${icon} ${label}${groupNote}`, "");

          if (findings.length > 0) {
            lines.push("| Selector | Severity |");
            lines.push("|----------|----------|");
            findings.forEach(({ selector, severity }) => {
              lines.push(`| \`${selector}\` | ${severity} |`);
            });
          } else {
            // Page-level audit: badge only, no element selectors
            const detail = badge ? `badge: ${badge}` : "passed";
            lines.push(`_Page-level check — ${detail}_`);
          }
        },
      );
    }

    lines.push(
      "",
      "---",
      "",
      "_Generated by [MageForge](https://github.com/OpenForgeProject/mageforge)_",
    );
    return lines.join("\n");
  },

  /**
   * Format audit data as plain text.
   *
   * @param {object} data
   * @returns {string}
   */
  _exportAsTxt(data) {
    const sep = "=".repeat(50);
    const lines = [
      "MageForge Audit Report",
      sep,
      `Date : ${data.timestamp}`,
      `URL  : ${data.url}`,
      sep,
      "",
    ];

    if (!data.audits.length) {
      lines.push("No active audits.");
    } else {
      // Group audits by their group key
      const groups = {};
      data.audits.forEach((audit) => {
        const g = audit.group ?? "other";
        (groups[g] = groups[g] ?? []).push(audit);
      });

      Object.entries(groups).forEach(([groupKey, groupAudits]) => {
        lines.push(groupKey.toUpperCase(), "-".repeat(30));
        groupAudits.forEach(({ label, errors, warnings, badge, findings }) => {
          const status =
            errors > 0 ? "[ERROR]" : warnings > 0 ? "[WARN] " : "[OK]   ";
          const detail =
            errors > 0
              ? `${errors} error(s)`
              : warnings > 0
                ? `${warnings} warning(s)`
                : badge || "passed";
          lines.push(`  ${status} ${label}: ${detail}`);

          // Render element selectors as indented tree
          findings.forEach(({ selector, severity }, i) => {
            const branch = i === findings.length - 1 ? "└─" : "├─";
            const tag = severity === "warning" ? "[warn]" : "[err] ";
            lines.push(`           ${branch} ${tag} ${selector}`);
          });
        });
        lines.push("");
      });

      const totalErrors = data.audits.reduce((s, a) => s + a.errors, 0);
      const totalWarnings = data.audits.reduce((s, a) => s + a.warnings, 0);
      lines.push(
        sep,
        `Total: ${totalErrors} error(s), ${totalWarnings} warning(s)`,
      );
    }

    lines.push(
      "",
      "Generated by MageForge (https://github.com/OpenForgeProject/mageforge)",
    );
    return lines.join("\n");
  },

  /**
   * Update the description text of an audit menu item.
   * Useful for audits that want to surface detail (e.g. which IDs are duplicated).
   *
   * @param {string} key
   * @param {string} text
   */
  setAuditDescription(key, text) {
    if (!this.menu) return;
    const item = this.menu.querySelector(`[data-audit-key="${key}"]`);
    if (!item) return;
    const desc = item.querySelector(".mageforge-toolbar-menu-desc");
    if (!desc) return;
    const originalText = desc.dataset.originalText ?? desc.textContent;
    if (!desc.dataset.originalText) desc.dataset.originalText = originalText;
    const isChanged = text !== originalText;
    desc.textContent = text;
    desc.classList.toggle("mageforge-active", isChanged);
  },

  /**
   * Set the inline counter badge of an audit menu item.
   *
   * @param {string} key
   * @param {string} message
   * @param {'success'|'error'} type
   */
  setAuditCounterBadge(key, message, type = "success") {
    if (!this.menu) return;
    const item = this.menu.querySelector(`[data-audit-key="${key}"]`);
    if (!item) return;
    const status = item.querySelector(".mageforge-toolbar-menu-status");
    if (!status) return;
    status.textContent = message;
    status.className = `mageforge-toolbar-menu-status mageforge-toolbar-menu-status--${type}`;
    // Reflect error/warning/success on the active item background
    item.classList.toggle("mageforge-active--error", type === "error");
    item.classList.toggle("mageforge-active--warning", type === "warning");
  },
};
