/**
 * MageForge Toolbar – Audit items, findings, and dashboard summaries
 *
 * createMenuItem / setAuditFindings / setAuditActive
 * updateToggleAllButton / updateHomeSummary / updateDashboardIssues / updateExportButton
 */

import { getReadableSelector } from "../audits/highlight.js";
import { GROUP_ICONS } from "./constants.js";

export const itemMethods = {
  /**
   * Create a single audit row: icon | label + status badge | toggle | findings list.
   *
   * @param {string}   key
   * @param {string}   icon
   * @param {string}   label
   * @param {string}   description
   * @param {Function} callback
   * @param {?string}  groupKey
   * @returns {HTMLDivElement}
   */
  createMenuItem(key, icon, label, description, callback, groupKey = null) {
    const item = document.createElement("div");
    item.setAttribute("role", "button");
    item.setAttribute("tabindex", "0");
    item.className = "mageforge-toolbar-menu-item";
    item.dataset.auditKey = key;
    if (groupKey) item.dataset.groupKey = groupKey;
    item.setAttribute("aria-pressed", "false");

    item.innerHTML = `
      <span class="mageforge-toolbar-menu-icon">${icon}</span>
      <span class="mageforge-toolbar-menu-text">
        <span class="mageforge-toolbar-menu-label-row">
          <span class="mageforge-toolbar-menu-label">${label}</span>
          <span class="mageforge-toolbar-menu-status" aria-live="polite" aria-atomic="true"></span>
        </span>
        <span class="mageforge-toolbar-menu-desc"></span>
      </span>
      <span class="mageforge-toolbar-menu-toggle"></span>
    `;
    item.querySelector(".mageforge-toolbar-menu-desc").textContent =
      description;

    // Findings list – populated by setAuditFindings(); events never bubble to the toggle
    const findings = document.createElement("div");
    findings.className = "mageforge-audit-findings";
    findings.addEventListener("click", (e) => e.stopPropagation());
    findings.addEventListener("keydown", (e) => e.stopPropagation());
    findings.addEventListener("keyup", (e) => e.stopPropagation());
    item.appendChild(findings);

    item.onclick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      callback();
    };
    item.onkeydown = (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        e.stopPropagation();
        callback();
      }
      if (e.key === " ") {
        e.preventDefault();
      }
    };
    item.onkeyup = (e) => {
      if (e.key === " ") {
        e.stopPropagation();
        callback();
      }
    };

    return item;
  },

  /**
   * Populate (or clear) the findings list beneath an audit item.
   * Each row scrolls to and briefly highlights the element on click.
   *
   * @param {string} key
   * @param {Array<{el: Element, selector?: string, severity?: 'error'|'warning', action?: string}>} findings
   */
  setAuditFindings(key, findings) {
    if (!this.menu) return;
    const item = this.menu.querySelector(`[data-audit-key="${key}"]`);
    if (!item) return;
    const container = item.querySelector(".mageforge-audit-findings");
    if (!container) return;

    // Store finding counts on the item for badge aggregation
    let errorCount = 0;
    let warningCount = 0;
    findings?.forEach((f) => {
      if (f.severity === "warning") warningCount++;
      else errorCount++;
    });
    item.dataset.findingErrors = String(errorCount);
    item.dataset.findingWarnings = String(warningCount);

    container.innerHTML = "";
    container.classList.remove("mageforge-findings-open");

    if (!findings?.length) {
      container.classList.remove("mageforge-has-findings");
      return;
    }

    container.classList.add("mageforge-has-findings");

    const toggleBtn = document.createElement("button");
    toggleBtn.type = "button";
    toggleBtn.className = "mageforge-findings-toggle";
    toggleBtn.setAttribute("aria-expanded", "false");
    toggleBtn.innerHTML = `
      <svg class="mageforge-findings-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
      <span class="mageforge-findings-toggle-text">Show affected elements (${findings.length})</span>
    `;
    toggleBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      const isOpen = container.classList.toggle("mageforge-findings-open");
      toggleBtn.setAttribute("aria-expanded", String(isOpen));
      const textEl = toggleBtn.querySelector(".mageforge-findings-toggle-text");
      if (textEl)
        textEl.textContent = isOpen
          ? `Hide affected elements (${findings.length})`
          : `Show affected elements (${findings.length})`;
    });
    container.appendChild(toggleBtn);

    const list = document.createElement("div");
    list.className = "mageforge-findings-list";

    findings.forEach(
      (
        { el, selector, severity = "error", action = "Show Element" },
        index,
      ) => {
        const selectorStr = selector ?? getReadableSelector(el);
        const isLast = index === findings.length - 1;

        const row = document.createElement("div");
        row.className = `mageforge-audit-finding mageforge-audit-finding--${severity}`;

        const treeEl = document.createElement("span");
        treeEl.className = "mageforge-finding-tree";
        treeEl.setAttribute("aria-hidden", "true");
        treeEl.textContent = `${isLast ? "\u2514" : "\u251C"}\u2500`;

        const selectorEl = document.createElement("span");
        selectorEl.className = "mageforge-finding-selector";
        selectorEl.setAttribute("title", selectorStr);
        selectorEl.textContent = selectorStr;

        const actionEl = document.createElement("span");
        actionEl.className = "mageforge-finding-action";
        actionEl.textContent = action;

        row.appendChild(treeEl);
        row.appendChild(selectorEl);
        row.appendChild(actionEl);

        row.addEventListener("click", (e) => {
          e.stopPropagation();
          el.scrollIntoView({ behavior: "smooth", block: "center" });
          el.classList.add("mageforge-finding-flash");
          setTimeout(
            () => el.classList.remove("mageforge-finding-flash"),
            1200,
          );
        });
        list.appendChild(row);
      },
    );

    container.appendChild(list);
  },

  /**
   * Toggle the active visual state of an audit item.
   * Clears findings and status badge on deactivation.
   *
   * @param {string}  key
   * @param {boolean} active
   */
  setAuditActive(key, active) {
    if (!this.menu) return;
    const item = this.menu.querySelector(`[data-audit-key="${key}"]`);
    if (!item) return;

    item.classList.toggle("mageforge-active", active);
    item.setAttribute("aria-pressed", String(active));

    if (!active) {
      item.classList.remove(
        "mageforge-active--error",
        "mageforge-active--warning",
      );
      const status = item.querySelector(".mageforge-toolbar-menu-status");
      if (status) {
        status.textContent = "";
        status.className = "mageforge-toolbar-menu-status";
      }
      this.setAuditFindings(key, []);
    }

    this.updateToggleAllButton();
    this.updateHomeSummary();
    this.updateExportButton();
    this._updateResetAllButton();
  },

  /** No-op – retained for compatibility. */
  updateToggleAllButton() {},

  /**
   * Rebuild the compact issues list on the Dashboard panel.
   */
  updateDashboardIssues() {
    if (!this.dashboardIssuesEl) return;

    /** @type {Array<{label: string, count: number, severity: string, groupKey: string|null}>} */
    const rows = [];
    this.menu?.querySelectorAll("[data-audit-key]").forEach((item) => {
      let errors = parseInt(item.dataset.findingErrors || "0", 10);
      let warnings = parseInt(item.dataset.findingWarnings || "0", 10);

      // Badge-only audits: fall back to the status badge (same logic as updateHomeSummary)
      if (!errors && !warnings) {
        const status = item.querySelector(".mageforge-toolbar-menu-status");
        const count = parseInt(status?.textContent || "0", 10) || 0;
        if (count > 0) {
          if (
            status.classList.contains("mageforge-toolbar-menu-status--error")
          ) {
            errors = count;
          } else if (
            status.classList.contains("mageforge-toolbar-menu-status--warning")
          ) {
            warnings = count;
          }
        }
      }

      if (!errors && !warnings) return;
      const label =
        item.querySelector(".mageforge-toolbar-menu-label")?.textContent ?? "";
      const groupKey = item.dataset.groupKey ?? null;
      if (errors)
        rows.push({ label, count: errors, severity: "error", groupKey });
      if (warnings)
        rows.push({ label, count: warnings, severity: "warning", groupKey });
    });

    this.dashboardIssuesEl.innerHTML = "";

    if (!rows.length) return;

    // Errors first, then warnings, each group sorted by count desc
    rows.sort((a, b) => {
      if (a.severity !== b.severity) return a.severity === "error" ? -1 : 1;
      return b.count - a.count;
    });

    const heading = document.createElement("p");
    heading.className = "mageforge-dashboard-issues-heading";
    heading.textContent = "Issues found";
    this.dashboardIssuesEl.appendChild(heading);

    rows.forEach(({ label, count, severity, groupKey }) => {
      const row = document.createElement("div");
      row.className = `mageforge-dashboard-issue mageforge-dashboard-issue--${severity}`;

      const countEl = document.createElement("span");
      countEl.className = "mageforge-dashboard-issue-count";
      countEl.textContent = String(count);

      const labelEl = document.createElement("span");
      labelEl.className = "mageforge-dashboard-issue-label";
      labelEl.textContent = label;

      row.appendChild(countEl);
      row.appendChild(labelEl);

      if (groupKey) {
        const groupLabel =
          this.getAuditGroups().find((g) => g.key === groupKey)?.label ??
          groupKey;
        const badge = document.createElement("button");
        badge.type = "button";
        badge.className = "mageforge-dashboard-issue-group";
        badge.style.setProperty(
          "--issue-group-color",
          `var(--mageforge-group-color-${groupKey})`,
        );
        badge.innerHTML = GROUP_ICONS[groupKey] ?? "";
        badge.title = `Jump to ${groupLabel}`;
        badge.setAttribute("aria-label", `Jump to ${groupLabel}`);
        badge.onclick = (e) => {
          e.stopPropagation();
          this.switchTab(groupKey);
        };
        row.appendChild(badge);
      }

      this.dashboardIssuesEl.appendChild(row);
    });
  },

  /**
   * Update error/warning badges on the left navigation tabs.
   * Counts actual findings (affected elements), not just audits.
   */
  updateHomeSummary() {
    if (!this.menu) return;

    // Count actual findings (elements) per group
    const groupCounts = {};
    this.menu.querySelectorAll("[data-audit-key]").forEach((item) => {
      let errors = parseInt(item.dataset.findingErrors || "0", 10);
      let warnings = parseInt(item.dataset.findingWarnings || "0", 10);

      // Badge-only audits (page-level checks) never call setAuditFindings, so
      // findingErrors/findingWarnings stay at 0. Fall back to the status badge.
      if (!errors && !warnings) {
        const status = item.querySelector(".mageforge-toolbar-menu-status");
        const count = parseInt(status?.textContent || "0", 10) || 0;
        if (count > 0) {
          if (
            status.classList.contains("mageforge-toolbar-menu-status--error")
          ) {
            errors = count;
          } else if (
            status.classList.contains("mageforge-toolbar-menu-status--warning")
          ) {
            warnings = count;
          }
        }
      }

      if (!errors && !warnings) return;

      const groupKey = item.dataset.groupKey;
      if (!groupKey) return;

      if (!groupCounts[groupKey]) {
        groupCounts[groupKey] = { errors: 0, warnings: 0 };
      }

      groupCounts[groupKey].errors += errors;
      groupCounts[groupKey].warnings += warnings;
    });

    // Reset ALL badges first, then populate only those with findings
    this.menu.querySelectorAll("[data-tab-badges-for]").forEach((container) => {
      const errorBadge = container.querySelector('[data-type="errors"]');
      const warningBadge = container.querySelector('[data-type="warnings"]');
      if (errorBadge) {
        errorBadge.textContent = "";
        errorBadge.style.display = "none";
      }
      if (warningBadge) {
        warningBadge.textContent = "";
        warningBadge.style.display = "none";
      }
    });

    this.updateDashboardIssues();

    // Update badges for each group tab with findings
    Object.entries(groupCounts).forEach(([groupKey, counts]) => {
      const container = this.menu.querySelector(
        `[data-tab-badges-for="${groupKey}"]`,
      );
      if (!container) return;

      const errorBadge = container.querySelector('[data-type="errors"]');
      const warningBadge = container.querySelector('[data-type="warnings"]');

      if (counts.errors > 0 && errorBadge) {
        errorBadge.textContent = counts.errors;
        errorBadge.style.display = "inline-flex";
      }

      if (counts.warnings > 0 && warningBadge) {
        warningBadge.textContent = counts.warnings;
        warningBadge.style.display = "inline-flex";
      }
    });
  },

  /**
   * Enable or disable the Export JSON button based on whether any audits
   * are currently active.
   */
  updateExportButton() {
    if (!this._exportBtnRow) return;
    const hasActive = this.activeAudits.size > 0;
    this._exportBtnRow
      .querySelectorAll("[data-export-format]")
      .forEach((btn) => {
        btn.disabled = !hasActive;
        btn.classList.toggle("mageforge-export-btn--disabled", !hasActive);
      });
  },
};
