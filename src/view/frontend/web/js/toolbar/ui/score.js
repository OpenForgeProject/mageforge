/**
 * MageForge Toolbar – Score animations
 *
 * updateHealthScore / updateGroupScore / resetScore
 */

import { GAUGE_ARC_LENGTH, SCORE_RING_CIRCUMFERENCE } from "./constants.js";

export const scoreMethods = {
  /**
   * Apply a colour class to a score element based on its value.
   * < 50 = low (red), 50–89 = mid (amber), >= 90 = high (green).
   *
   * @param {HTMLElement|null} el
   * @param {number} score
   */
  _applyScoreColorClass(el, score) {
    if (!el) return;
    el.classList.remove(
      "mageforge-score--low",
      "mageforge-score--mid",
      "mageforge-score--high",
    );
    if (score < 0) return;
    if (score < 50) {
      el.classList.add("mageforge-score--low");
    } else if (score < 90) {
      el.classList.add("mageforge-score--mid");
    } else {
      el.classList.add("mageforge-score--high");
    }
  },

  /**
   * Animate all score gauges and rings to the given score (0-100).
   *
   * @param {number} score
   */
  updateHealthScore(score) {
    if (!this.menu) return;

    // Half-arc gauge in the Home panel
    const progress = this.menu.querySelector(
      ".mageforge-health-gauge-progress",
    );
    const needle = this.menu.querySelector(".mageforge-health-gauge-needle");
    if (progress)
      progress.setAttribute(
        "stroke-dasharray",
        `${((score / 100) * GAUGE_ARC_LENGTH).toFixed(2)} ${GAUGE_ARC_LENGTH}`,
      );
    if (needle) {
      const rad = (1 - score / 100) * Math.PI;
      needle.setAttribute("x2", (60 + 45 * Math.cos(rad)).toFixed(1));
      needle.setAttribute("y2", (65 - 45 * Math.sin(rad)).toFixed(1));
      needle.setAttribute("opacity", "1");
    }
    this.menu
      .querySelectorAll(".mageforge-toolbar-health-score-value")
      .forEach((el) => {
        const number = el.querySelector(".mageforge-toolbar-health-score-number");
        if (number) number.textContent = score;
        this._applyScoreColorClass(el, score);
      });

    // Circular rings in audit panel headers
    this.menu.querySelectorAll(".mageforge-score-ring").forEach((ring) => {
      ring.setAttribute(
        "stroke-dasharray",
        `${((score / 100) * SCORE_RING_CIRCUMFERENCE).toFixed(2)} ${SCORE_RING_CIRCUMFERENCE}`,
      );
    });
    this.menu.querySelectorAll(".mageforge-score-number").forEach((el) => {
      el.textContent = score;
    });
  },

  /**
   * Update the score ring in a specific group panel header.
   *
   * @param {string} groupKey
   * @param {number} score
   */
  updateGroupScore(groupKey, score) {
    if (!this.menu) return;

    const panel = this.menu.querySelector(`[data-panel="${groupKey}"]`);
    if (!panel) return;

    const ring = panel.querySelector(".mageforge-score-ring");
    if (ring) {
      ring.setAttribute(
        "stroke-dasharray",
        `${((score / 100) * SCORE_RING_CIRCUMFERENCE).toFixed(2)} ${SCORE_RING_CIRCUMFERENCE}`,
      );
    }
    const number = panel.querySelector(".mageforge-score-number");
    const valueEl = panel.querySelector(".mageforge-score-value");
    if (number) {
      number.textContent = score;
    }
    if (valueEl) {
      this._applyScoreColorClass(valueEl, score);
    }

    // Also update the dashboard category badge
    const dashboardScore = this.menu.querySelector(
      `[data-dashboard-group-score="${groupKey}"]`,
    );
    if (dashboardScore) {
      dashboardScore.textContent = score;
      dashboardScore.classList.toggle(
        "mageforge-dashboard-category-score--active",
        score > 0,
      );
      this._applyScoreColorClass(dashboardScore, score);
    }
  },

  /**
   * Reset all score displays and deactivate all audits.
   */
  resetScore() {
    this.deactivateAllAudits();
    if (!this.menu) return;

    const progress = this.menu.querySelector(
      ".mageforge-health-gauge-progress",
    );
    const needle = this.menu.querySelector(".mageforge-health-gauge-needle");
    if (progress)
      progress.setAttribute("stroke-dasharray", `0 ${GAUGE_ARC_LENGTH}`);
    if (needle) needle.setAttribute("opacity", "0");
    this.menu
      .querySelectorAll(".mageforge-toolbar-health-score-number")
      .forEach((el) => {
        el.textContent = "--";
      });
    this.menu.querySelectorAll(".mageforge-score-ring").forEach((ring) => {
      ring.setAttribute("stroke-dasharray", `0 ${SCORE_RING_CIRCUMFERENCE}`);
    });
    this.menu.querySelectorAll(".mageforge-score-number").forEach((el) => {
      el.textContent = "--";
    });
    this.menu
      .querySelectorAll(
        ".mageforge-toolbar-health-score-value, .mageforge-score-value, .mageforge-dashboard-category-score",
      )
      .forEach((el) => {
        this._applyScoreColorClass(el, -1);
      });

    // Reset dashboard category badges
    this.menu.querySelectorAll("[data-dashboard-group-score]").forEach((el) => {
      el.textContent = "--";
      el.classList.remove("mageforge-dashboard-category-score--active");
    });

    // Clear dashboard issues list
    if (this.dashboardIssuesEl) this.dashboardIssuesEl.innerHTML = "";

    // Reset all navigation badges
    this.updateHomeSummary();
  },
};
