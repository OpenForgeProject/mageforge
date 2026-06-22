/**
 * MageForge Toolbar - Audit dispatcher
 */

import { audits, auditGroups } from "./audits/index.js";

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
      audit.run(this, false);
      this.setAuditCounterBadge(auditKey, "", "success");
    } else {
      this.activeAudits.add(auditKey);
      audit.run(this, true);
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
