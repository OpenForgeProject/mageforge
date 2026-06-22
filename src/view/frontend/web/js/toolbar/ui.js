/**
 * MageForge Toolbar – DOM construction and menu controls
 *
 * Structure:
 *   createToolbar()           – Entry point; assembles and injects the toolbar DOM
 *   _buildMenu()              – Full menu popup container
 *     _buildMenuHeader()      – Sticky title bar (logo + name + close button)
 *     _buildTabLayout()       – Two-column tab container (nav | content)
 *       _buildTabNav()        – Left-side navigation buttons + action bar at bottom
 *       _buildNavTab()        – Single nav tab button
 *       _buildTabPanels()     – All content panels
 *         _buildPanel()       – Panel shell (role=tabpanel)
 *         _buildPanelHeader() – Panel title + compact score ring
 *         _buildScoreWidget() – Circular score ring (panel headers)
 *         _buildHomePanel()   – Overview panel with half-arc gauge
 *         _buildSettingsPanel() – Settings placeholder
 *     _buildMenuFooter()      – Credit line only (action bar is in nav)
 *   _buildBurgerButton()      – Persistent trigger button
 *
 *   switchTab()               – Activate a tab and show its panel
 *   createMenuItem()          – Single audit row
 *   setAuditFindings()        – Populate the clickable findings list under an item
 *   setAuditActive()          – Update an item's active / inactive visual state
 *   updateHealthScore()       – Animate all gauges and rings to a new score
 *   resetScore()              – Reset gauges + deactivate all audits
 *   toggleMenu() / openMenu() / closeMenu() / destroyToolbar()
 */

// ── Constants ──────────────────────────────────────────────────────────────

const LOGO_SVG_PATH =
  "M176 0L0 101.614V297L176 398.614L352 297V101.614L176 0ZM39 275.5V124L76.2391 101.614L101.5 162L126.5 73.4393L164.5 51.5V346.939L126.5 325V188L108.5 239H95L76.2391 188V297L39 275.5ZM187.5 346.939V51.5L313 124V170H275.5V146.368L225.5 117.5V188H280V226.5H225.5V325L187.5 346.939Z";

const ICON_HOME =
  '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>';

const GROUP_ICONS = {
  wcag: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
  "html-quality":
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
  performance:
    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>',
};

// ── Module-level helpers ───────────────────────────────────────────────────

function createLogoSvg(fill) {
  return `<svg width="24" height="27" viewBox="0 0 352 399" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="${LOGO_SVG_PATH}" fill="${fill}"></path></svg>`;
}

/**
 * Build a short, human-readable CSS selector for labelling a finding.
 *
 * @param {Element} el
 * @returns {string}
 */
function getReadableSelector(el) {
  if (el.id) return `#${el.id}`;
  const tag = el.tagName.toLowerCase();
  const classes = [...el.classList]
    .filter((c) => !c.startsWith("mageforge"))
    .slice(0, 2)
    .join(".");
  if (classes) return `${tag}.${classes}`;
  const ariaLabel = el.getAttribute("aria-label");
  if (ariaLabel) return `${tag}[aria-label]`;
  if (el.name) return `${tag}[name="${el.name}"]`;
  if (tag === "img" && el.src) {
    const base = el.src.split("/").pop().split("?")[0].slice(0, 24);
    return `img/${base}`;
  }
  return tag;
}

// ── Exported mixin ─────────────────────────────────────────────────────────

export const uiMethods = {
  // ────────────────────────────────────────────────────────────────────────
  // Entry point
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Build and inject the full toolbar into <body>.
   */
  createToolbar() {
    this.container = document.createElement("div");
    this.container.className = "mageforge-toolbar";

    if (this.$el?.hasAttribute("data-theme")) {
      this.container.setAttribute(
        "data-theme",
        this.$el.getAttribute("data-theme"),
      );
    }
    if (this.$el?.hasAttribute("data-position")) {
      this.container.setAttribute(
        "data-position",
        this.$el.getAttribute("data-position"),
      );
    }
    if (this.$el?.getAttribute("data-show-labels") === "0") {
      this.container.classList.add("mageforge-toolbar--no-labels");
    }

    this.menu = this._buildMenu();
    this.container.appendChild(this.menu);

    this.burgerButton = this._buildBurgerButton();
    this.container.appendChild(this.burgerButton);
    // Note: Inspector button is appended externally via _appendInspectorButton()

    this._outsideClickHandler = (e) => {
      if (this.menuOpen && !this.container.contains(e.target)) this.closeMenu();
    };
    document.addEventListener("click", this._outsideClickHandler);
    document.body.appendChild(this.container);
  },

  // ────────────────────────────────────────────────────────────────────────
  // Menu sections
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Assemble the full menu popup.
   *
   * @returns {HTMLDivElement}
   */
  _buildMenu() {
    const menu = document.createElement("div");
    menu.className = "mageforge-toolbar-menu";
    menu.appendChild(this._buildMenuHeader());
    menu.appendChild(this._buildTabLayout());
    menu.appendChild(this._buildMenuFooter());
    return menu;
  },

  /**
   * Sticky title bar: logo + name + close button.
   *
   * @returns {HTMLDivElement}
   */
  _buildMenuHeader() {
    const header = document.createElement("div");
    header.className = "mageforge-toolbar-menu-title";
    header.innerHTML = `
      <div class="mageforge-toolbar-menu-logo">
        <div>${createLogoSvg("#E5622A")}</div>
        <span class="mageforge-toolbar-menu-title-text">MageForge</span>
      </div>
      <button type="button" class="mageforge-toolbar-menu-close" title="Close & deactivate all">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"></path></svg>
      </button>
    `;
    header.querySelector(".mageforge-toolbar-menu-close").onclick = (e) => {
      e.stopPropagation();
      this.deactivateAllAudits();
      this.closeMenu();
    };
    return header;
  },

  /**
   * Two-column layout: tab nav (left) + content panels (right).
   *
   * @returns {HTMLDivElement}
   */
  _buildTabLayout() {
    const layout = document.createElement("div");
    layout.className = "mageforge-toolbar-tabs";
    layout.appendChild(this._buildTabNav());
    layout.appendChild(this._buildTabPanels());
    return layout;
  },

  // ────────────────────────────────────────────────────────────────────────
  // Tab navigation
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Left-side navigation: Home at top, audit groups, Settings pinned to bottom.
   *
   * @returns {HTMLElement}
   */
  _buildTabNav() {
    const nav = document.createElement("nav");
    nav.className = "mageforge-toolbar-tab-nav";
    nav.setAttribute("role", "tablist");
    nav.setAttribute("aria-label", "Audit categories");

    // Action bar (run/reset for the current tab).
    // column-reverse means the first DOM child appears at the visual bottom.
    this.footerActionBar = document.createElement("div");
    this.footerActionBar.className = "mageforge-nav-action-bar";
    nav.appendChild(this.footerActionBar);

    nav.appendChild(this._buildNavTab("home", ICON_HOME, "Dashboard", true));

    this.getAuditGroups().forEach((group) => {
      nav.appendChild(
        this._buildNavTab(group.key, GROUP_ICONS[group.key] ?? "", group.label),
      );
    });

    return nav;
  },

  /**
   * When audits from 2+ groups are active, relabel every group reset button
   * to "Reset All" and wire it to reset everything; otherwise restore the
   * per-group label and behaviour.
   */
  _updateResetAllButton() {
    const isMulti = this._isMultiGroupActive();
    const RESET_SVG =
      '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>';
    const label = isMulti ? "Reset All" : "Reset";
    this.getAuditGroups().forEach((group) => {
      const btn = this[`groupResetButton-${group.key}`];
      if (!btn) return;
      btn.innerHTML = `${RESET_SVG} ${label}`;
      const ariaLabel = isMulti
        ? "Reset all active audits"
        : `Reset ${group.label} audits`;
      btn.setAttribute("aria-label", ariaLabel);
      btn.title = ariaLabel;
      btn.classList.toggle("mageforge-group-reset-btn--all", isMulti);

      const groupHasActive =
        isMulti ||
        this.getAudits().some(
          (a) => a.group === group.key && this.activeAudits.has(a.key),
        );
      btn.classList.toggle("mageforge-group-reset-btn--disabled", !groupHasActive);
      btn.setAttribute("aria-disabled", String(!groupHasActive));
      btn.setAttribute("tabindex", groupHasActive ? "0" : "-1");
    });

    // Home reset button: disabled when nothing is active at all
    if (this.resetButton) {
      const hasAny = this.activeAudits.size > 0;
      this.resetButton.classList.toggle("mageforge-group-reset-btn--disabled", !hasAny);
      this.resetButton.setAttribute("aria-disabled", String(!hasAny));
      this.resetButton.setAttribute("tabindex", hasAny ? "0" : "-1");
    }
  },

  /** Returns true when audits from at least 2 different groups are active. */
  _isMultiGroupActive() {
    const activeGroups = new Set(
      this.getAudits()
        .filter((a) => a.group && this.activeAudits.has(a.key))
        .map((a) => a.group),
    );
    return activeGroups.size >= 2;
  },

  /**
   * Single tab button (icon stacked above abbreviated label).
   *
   * @param {string}  key
   * @param {string}  icon       – SVG string
   * @param {string}  label      – Display label (first word used in the nav)
   * @param {boolean} [isActive]
   * @returns {HTMLButtonElement}
   */
  _buildNavTab(key, icon, label, isActive = false) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.setAttribute("role", "tab");
    btn.setAttribute("aria-selected", String(isActive));
    btn.setAttribute("aria-controls", `mf-tab-panel-${key}`);
    btn.setAttribute("tabindex", isActive ? "0" : "-1");
    btn.className = "mageforge-toolbar-tab-btn";
    if (isActive) btn.classList.add("mageforge-tab-active");
    btn.dataset.tab = key;
    btn.innerHTML = `
      <span class="mageforge-tab-icon" aria-hidden="true">${icon}</span>
      <span class="mageforge-tab-label">${label.split(" ")[0]}</span>
      <span class="mageforge-tab-badges" data-tab-badges-for="${key}">
        <span class="mageforge-tab-badge mageforge-tab-badge--errors" data-type="errors"></span>
        <span class="mageforge-tab-badge mageforge-tab-badge--warnings" data-type="warnings"></span>
      </span>
    `;
    btn.onclick = (e) => {
      e.stopPropagation();
      this.switchTab(key);
    };
    btn.onkeydown = (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        e.stopPropagation();
        this.switchTab(key);
      }
    };
    return btn;
  },

  // ────────────────────────────────────────────────────────────────────────
  // Tab panels
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Build all content panels wrapped in the scrollable content column.
   *
   * @returns {HTMLDivElement}
   */
  _buildTabPanels() {
    const wrapper = document.createElement("div");
    wrapper.className = "mageforge-toolbar-tab-content";

    const showHealthScore =
      this.$el?.getAttribute("data-show-health-score") !== "0";

    const grouped = {};
    const ungrouped = [];
    this.getAudits().forEach((audit) => {
      audit.group
        ? (grouped[audit.group] = grouped[audit.group] || []).push(audit)
        : ungrouped.push(audit);
    });

    // Home panel – visible by default
    const homePanel = this._buildPanel("home");
    homePanel.classList.add("mageforge-tab-panel-active");
    homePanel.appendChild(this._buildHomePanel(showHealthScore));
    wrapper.appendChild(homePanel);

    // One panel per audit group
    this.getAuditGroups().forEach((group) => {
      const items = grouped[group.key];
      if (!items?.length) return;

      const panel = this._buildPanel(group.key);
      panel.setAttribute("hidden", "");
      panel.appendChild(this._buildPanelHeader(group.label, false, group.key));

      const body = document.createElement("div");
      body.className = "mageforge-tab-panel-body";

      const groupLabel =
        this.getAuditGroups().find((g) => g.key === group.key)?.label ??
        group.key;

      // Build run button – stored as ref, rendered in footer action bar
      const groupBtn = document.createElement("button");
      groupBtn.type = "button";
      groupBtn.className = "mageforge-group-run-btn";
      groupBtn.dataset.group = group.key;
      this[`runGroupButton-${group.key}`] = groupBtn;
      groupBtn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        Run Check
      `;
      groupBtn.onclick = (e) => {
        e.stopPropagation();
        this.runGroupAuditsForScore(group.key);
      };

      // Build reset button – stored as ref, rendered in footer action bar
      const groupResetBtn = document.createElement("div");
      groupResetBtn.setAttribute("role", "button");
      groupResetBtn.setAttribute("tabindex", "0");
      groupResetBtn.className = "mageforge-group-reset-btn";
      groupResetBtn.setAttribute("aria-label", `Reset ${groupLabel} audits`);
      groupResetBtn.title = `Reset ${groupLabel} audits`;
      groupResetBtn.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg> Reset';
      const handleGroupReset = () => {
        const btn = this[`groupResetButton-${group.key}`];
        if (btn?.classList.contains("mageforge-group-reset-btn--disabled")) return;
        if (this._isMultiGroupActive()) {
          this.resetScore();
        } else {
          this.resetGroupAudits(group.key);
        }
      };
      groupResetBtn.onclick = (e) => {
        e.stopPropagation();
        handleGroupReset();
      };
      groupResetBtn.onkeydown = (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          e.stopPropagation();
          handleGroupReset();
        }
        if (e.key === " ") e.preventDefault();
      };
      groupResetBtn.onkeyup = (e) => {
        if (e.key === " ") {
          e.stopPropagation();
          handleGroupReset();
        }
      };
      this[`groupResetButton-${group.key}`] = groupResetBtn;

      items.forEach((audit) => {
        body.appendChild(
          this.createMenuItem(
            audit.key,
            audit.icon,
            audit.label,
            audit.description,
            () => this.runAudit(audit.key),
            group.key,
          ),
        );
      });

      if (items.length < 6) {
        const featureBtn = document.createElement("a");
        featureBtn.href =
          "https://github.com/OpenForgeProject/mageforge/issues/new?labels=enhancement&template=feature_request.yml&title=%5BAudit+Request%5D+";
        featureBtn.target = "_blank";
        featureBtn.rel = "noopener noreferrer";
        featureBtn.className = "mageforge-feature-request-btn";
        featureBtn.innerHTML = `
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7-7 7 7"/></svg>
          Request an Audit
        `;
        body.appendChild(featureBtn);
      }

      panel.appendChild(body);
      wrapper.appendChild(panel);
    });

    // Ungrouped audits (no header)
    if (ungrouped.length > 0) {
      const panel = this._buildPanel("ungrouped");
      panel.setAttribute("hidden", "");
      ungrouped.forEach((audit) => {
        panel.appendChild(
          this.createMenuItem(
            audit.key,
            audit.icon,
            audit.label,
            audit.description,
            () => this.runAudit(audit.key),
          ),
        );
      });
      wrapper.appendChild(panel);
    }

    return wrapper;
  },

  /**
   * Create a bare panel shell.
   *
   * @param {string} key
   * @returns {HTMLDivElement}
   */
  _buildPanel(key) {
    const panel = document.createElement("div");
    panel.className = "mageforge-toolbar-tab-panel";
    panel.setAttribute("role", "tabpanel");
    panel.setAttribute("id", `mf-tab-panel-${key}`);
    panel.dataset.panel = key;
    return panel;
  },

  /**
   * Panel title bar: group name on the left, score ring on the right.
   *
   * @param {string}  title
   * @param {boolean} showScore
   * @param {string}  groupKey
   * @returns {HTMLDivElement}
   */
  _buildPanelHeader(title, showScore, groupKey) {
    const header = document.createElement("div");
    header.className = "mageforge-tab-panel-header";
    header.dataset.group = groupKey;

    const titleEl = document.createElement("h2");
    titleEl.className = "mageforge-tab-panel-title";
    titleEl.textContent = title;
    header.appendChild(titleEl);

    if (showScore) header.appendChild(this._buildScoreWidget());
    return header;
  },

  /**
   * Compact circular score ring shown in every audit panel header.
   *
   * @returns {HTMLDivElement}
   */
  _buildScoreWidget() {
    const CIRCUMFERENCE = 113.1; // 2pi x r=18
    const gradId = `mf-sg-${Math.random().toString(36).slice(2, 7)}`;
    const widget = document.createElement("div");
    widget.className = "mageforge-score-widget";
    widget.innerHTML = `
      <svg width="50" height="50" viewBox="0 0 44 44" aria-hidden="true">
        <defs>
          <linearGradient id="${gradId}" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%"   stop-color="#ef4444"></stop>
            <stop offset="50%"  stop-color="#edb04d"></stop>
            <stop offset="100%" stop-color="#10b981"></stop>
          </linearGradient>
        </defs>
        <circle cx="22" cy="22" r="18" fill="none" stroke="rgba(148,163,184,0.15)" stroke-width="4"></circle>
        <circle cx="22" cy="22" r="18" fill="none" stroke="url(#${gradId})" stroke-width="4"
                stroke-dasharray="0 ${CIRCUMFERENCE}" stroke-linecap="round"
                transform="rotate(-90 22 22)" class="mageforge-score-ring"></circle>
      </svg>
      <div class="mageforge-score-overlay">
        <div class="mageforge-score-value">
          <span class="mageforge-score-number">--</span><span class="mageforge-score-denom">/100</span>
        </div>
        <div class="mageforge-score-label">Health Score</div>
      </div>
    `;
    return widget;
  },

  /**
   * Home panel: half-arc gauge overview + Check Health Score button.
   *
   * @param {boolean} showHealthScore
   * @returns {HTMLDivElement}
   */
  _buildHomePanel(showHealthScore) {
    const panel = document.createElement("div");
    panel.className = "mageforge-home-panel";

    if (showHealthScore) {
      const ARC_LENGTH = 157.08;
      const gradId = `mf-gauge-${Math.random().toString(36).slice(2, 8)}`;
      panel.innerHTML = `
        <div class="mageforge-toolbar-health-wrapper">
          <svg viewBox="0 0 120 70" class="mageforge-toolbar-health-gauge" aria-hidden="true">
            <defs>
              <linearGradient id="${gradId}" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%"   stop-color="#ef4444"></stop>
                <stop offset="50%"  stop-color="#edb04d"></stop>
                <stop offset="100%" stop-color="#10b981"></stop>
              </linearGradient>
            </defs>
            <path d="M 10 65 A 50 50 0 0 1 110 65"
                  fill="none" stroke="rgba(148,163,184,0.15)" stroke-width="10" stroke-linecap="round"></path>
            <path d="M 10 65 A 50 50 0 0 1 110 65"
                  fill="none" stroke="url(#${gradId})" stroke-width="10" stroke-linecap="round"
                  stroke-dasharray="0 ${ARC_LENGTH}" class="mageforge-health-gauge-progress"></path>
            <line class="mageforge-health-gauge-needle"
                  x1="60" y1="65" x2="60" y2="20"
                  stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" opacity="0"></line>
            <circle cx="60" cy="65" r="4" fill="rgba(255,255,255,0.4)"></circle>
          </svg>
          <div class="mageforge-toolbar-health-score-text">
            <div class="mageforge-toolbar-health-score-value">
              <span class="mageforge-toolbar-health-score-number">--</span>
              <span class="mageforge-toolbar-health-score-max">/100</span>
            </div>
            <div class="mageforge-toolbar-health-score-label">Overall Health Score</div>
          </div>
        </div>
      `;

      // Check Health Score button + Reset side by side
      const btnRow = document.createElement("div");
      btnRow.className = "mageforge-home-btn-row";

      this.runAllButton = document.createElement("button");
      this.runAllButton.type = "button";
      this.runAllButton.className = "mageforge-group-run-btn";
      this.runAllButton.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        Perform Full Check
      `;
      this.runAllButton.onclick = (e) => {
        e.stopPropagation();
        this.runAllAuditsForScore();
      };
      btnRow.appendChild(this.runAllButton);

      // Reset button next to Perform Full Check
      this.resetButton = document.createElement("div");
      this.resetButton.setAttribute("role", "button");
      this.resetButton.setAttribute("tabindex", "0");
      this.resetButton.className = "mageforge-group-reset-btn";
      this.resetButton.title = "Reset score and deactivate all audits";
      this.resetButton.setAttribute(
        "aria-label",
        "Reset score and deactivate all audits",
      );
      this.resetButton.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg> Reset';
      this.resetButton.onclick = (e) => {
        e.stopPropagation();
        this.resetScore();
      };
      this.resetButton.onkeydown = (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          e.stopPropagation();
          this.resetScore();
        }
        if (e.key === " ") {
          e.preventDefault();
        }
      };
      this.resetButton.onkeyup = (e) => {
        if (e.key === " ") {
          e.stopPropagation();
          this.resetScore();
        }
      };
      btnRow.appendChild(this.resetButton);
      // btnRow held as ref; rendered in footer action bar via _updateFooterActions

      // Category score breakdown
      const categories = document.createElement("div");
      categories.className = "mageforge-dashboard-categories";
      this.getAuditGroups().forEach((group) => {
        const card = document.createElement("div");
        card.className = "mageforge-dashboard-category";
        card.style.setProperty(
          "--category-color",
          `var(--mageforge-group-color-${group.key})`,
        );
        card.innerHTML = `
          <span class="mageforge-dashboard-category-label">${group.label}</span>
          <span class="mageforge-dashboard-category-score" data-dashboard-group-score="${group.key}">--</span>
        `;
        categories.appendChild(card);
      });
      panel.appendChild(categories);

      // Issues list – populated by updateDashboardIssues()
      this.dashboardIssuesEl = document.createElement("div");
      this.dashboardIssuesEl.className = "mageforge-dashboard-issues";
      panel.appendChild(this.dashboardIssuesEl);

      panel.appendChild(
        Object.assign(document.createElement("p"), {
          className: "mageforge-home-hint",
          textContent: "Select a category on the left for detailed checks.",
        }),
      );
    } else {
      panel.innerHTML = `<p class="mageforge-home-hint">Select a category on the left to run audits.</p>`;
    }

    return panel;
  },

  // ────────────────────────────────────────────────────────────────────────
  // Footer
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Footer row: credit line only.
   *
   * @returns {HTMLDivElement}
   */
  _buildMenuFooter() {
    const footer = document.createElement("div");
    footer.className = "mageforge-toolbar-menu-footer";

    // ── Credit line ─────────────────────────────────────────────────────
    const credit = document.createElement("div");
    credit.className = "mageforge-toolbar-menu-credit";
    credit.innerHTML =
      'Built with <span class="mageforge-toolbar-menu-credit-heart">\u2764</span> by <a href="https://github.com/OpenForgeProject/mageforge" target="_blank" rel="noopener noreferrer" class="mageforge-toolbar-menu-credit-link">MageForge</a>';
    footer.appendChild(credit);

    // Populate nav action bar for the initially active tab (home).
    // footerActionBar was already created in _buildTabNav().
    this._updateFooterActions("home");
    this._updateResetAllButton();

    return footer;
  },

  /**
   * Populate the footer action bar with the run/reset buttons for the given tab.
   *
   * @param {string} key  – Tab key ("home" or a group key like "wcag")
   */
  _updateFooterActions(key) {
    if (!this.footerActionBar) return;
    this.footerActionBar.innerHTML = "";

    const row = document.createElement("div");
    row.className = "mageforge-footer-btn-row";

    if (key === "home") {
      if (!this.runAllButton) return;
      row.appendChild(this.runAllButton);
      row.appendChild(this.resetButton);
    } else {
      const runBtn = this[`runGroupButton-${key}`];
      const resetBtn = this[`groupResetButton-${key}`];
      if (!runBtn) return;
      row.appendChild(runBtn);
      if (resetBtn) row.appendChild(resetBtn);
    }

    this.footerActionBar.appendChild(row);
  },

  // ────────────────────────────────────────────────────────────────────────
  // Burger / trigger button
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Build the persistent trigger button (logo + label).
   *
   * @returns {HTMLDivElement}
   */
  _buildBurgerButton() {
    const btn = document.createElement("div");
    btn.className = "mageforge-toolbar-burger";
    btn.title = "Audit tools";
    btn.setAttribute("role", "button");
    btn.setAttribute("tabindex", "0");
    btn.setAttribute("aria-label", "Open audit tools menu");
    btn.setAttribute("aria-expanded", "false");
    btn.innerHTML = `
      <div class="mageforge-toolbar-burger-logo">${createLogoSvg("white")}</div>
      <span class="mageforge-toolbar-burger-label">MageForge</span>
    `;
    btn.onclick = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.toggleMenu();
    };
    btn.onkeydown = (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        e.stopPropagation();
        this.toggleMenu();
      }
      if (e.key === " ") {
        e.preventDefault();
      }
    };
    btn.onkeyup = (e) => {
      if (e.key === " ") {
        e.stopPropagation();
        this.toggleMenu();
      }
    };
    return btn;
  },

  // ────────────────────────────────────────────────────────────────────────
  // Tab switching
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Activate a tab and show its panel; hide all others.
   *
   * @param {string} key
   */
  switchTab(key) {
    this.activeTab = key;
    if (!this.menu) return;

    this.menu.querySelectorAll(".mageforge-toolbar-tab-btn").forEach((btn) => {
      const active = btn.dataset.tab === key;
      btn.classList.toggle("mageforge-tab-active", active);
      btn.setAttribute("aria-selected", String(active));
      btn.setAttribute("tabindex", active ? "0" : "-1");
    });

    this.menu
      .querySelectorAll(".mageforge-toolbar-tab-panel")
      .forEach((panel) => {
        const active = panel.dataset.panel === key;
        panel.classList.toggle("mageforge-tab-panel-active", active);
        active
          ? panel.removeAttribute("hidden")
          : panel.setAttribute("hidden", "");
      });

    this._updateFooterActions(key);
  },

  // ────────────────────────────────────────────────────────────────────────
  // Audit items
  // ────────────────────────────────────────────────────────────────────────

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
          <span class="mageforge-toolbar-menu-status"></span>
        </span>
        <span class="mageforge-toolbar-menu-desc">${description}</span>
      </span>
      <span class="mageforge-toolbar-menu-toggle"></span>
    `;

    // Findings list – populated by setAuditFindings(); clicks never bubble to the toggle
    const findings = document.createElement("div");
    findings.className = "mageforge-audit-findings";
    findings.addEventListener("click", (e) => e.stopPropagation());
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
    toggleBtn.innerHTML = `
      <svg class="mageforge-findings-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
      <span class="mageforge-findings-toggle-text">Show affected elements (${findings.length})</span>
    `;
    toggleBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      const isOpen = container.classList.toggle("mageforge-findings-open");
      const textEl = toggleBtn.querySelector(".mageforge-findings-toggle-text");
      if (textEl)
        textEl.textContent = isOpen
          ? `Hide affected elements (${findings.length})`
          : `Show affected elements (${findings.length})`;
    });
    container.appendChild(toggleBtn);

    const list = document.createElement("div");
    list.className = "mageforge-findings-list";

    findings.forEach(({ el, selector, severity = "error", action }, index) => {
      const selectorStr = selector ?? getReadableSelector(el);
      const isLast = index === findings.length - 1;

      const row = document.createElement("div");
      row.className = `mageforge-audit-finding mageforge-audit-finding--${severity}`;
      row.innerHTML = `
        <span class="mageforge-finding-tree" aria-hidden="true">${isLast ? "\u2514" : "\u251C"}\u2500</span>
        <span class="mageforge-finding-selector" title="${selectorStr}">${selectorStr}</span>
        <span class="mageforge-finding-action">Show Element</span>
      `;
      row.addEventListener("click", (e) => {
        e.stopPropagation();
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.classList.add("mageforge-finding-flash");
        setTimeout(() => el.classList.remove("mageforge-finding-flash"), 1200);
      });
      list.appendChild(row);
    });

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
    this._updateResetAllButton();
  },

  /** No-op – retained for compatibility. */
  updateToggleAllButton() {},

  /**
   * Rebuild the compact issues list on the Dashboard panel.
   */
  updateDashboardIssues() {
    if (!this.dashboardIssuesEl) return;

    /** @type {Array<{label: string, count: number, severity: string}>} */
    const rows = [];
    this.menu?.querySelectorAll("[data-audit-key]").forEach((item) => {
      const errors = parseInt(item.dataset.findingErrors || "0", 10);
      const warnings = parseInt(item.dataset.findingWarnings || "0", 10);
      if (!errors && !warnings) return;
      const label =
        item.querySelector(".mageforge-toolbar-menu-label")?.textContent ?? "";
      if (errors) rows.push({ label, count: errors, severity: "error" });
      if (warnings) rows.push({ label, count: warnings, severity: "warning" });
    });

    this.dashboardIssuesEl.innerHTML = "";

    if (!rows.length) return;

    // Errors first, then warnings, each group sorted by count desc
    rows.sort((a, b) => {
      if (a.severity !== b.severity)
        return a.severity === "error" ? -1 : 1;
      return b.count - a.count;
    });

    const heading = document.createElement("p");
    heading.className = "mageforge-dashboard-issues-heading";
    heading.textContent = "Issues found";
    this.dashboardIssuesEl.appendChild(heading);

    rows.forEach(({ label, count, severity }) => {
      const row = document.createElement("div");
      row.className = `mageforge-dashboard-issue mageforge-dashboard-issue--${severity}`;
      row.innerHTML = `<span class="mageforge-dashboard-issue-count">${count}</span><span class="mageforge-dashboard-issue-label">${label}</span>`;
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
      const errors = parseInt(item.dataset.findingErrors || "0", 10);
      const warnings = parseInt(item.dataset.findingWarnings || "0", 10);

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

  // ────────────────────────────────────────────────────────────────────────
  // Health score
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Animate all score gauges and rings to the given score (0-100).
   *
   * @param {number} score
   */
  updateHealthScore(score) {
    if (!this.menu) return;
    const ARC_LENGTH = 157.08;
    const CIRCUMFERENCE = 113.1;

    // Half-arc gauge in the Home panel
    const progress = this.menu.querySelector(
      ".mageforge-health-gauge-progress",
    );
    const needle = this.menu.querySelector(".mageforge-health-gauge-needle");
    if (progress)
      progress.setAttribute(
        "stroke-dasharray",
        `${((score / 100) * ARC_LENGTH).toFixed(2)} ${ARC_LENGTH}`,
      );
    if (needle) {
      const rad = (1 - score / 100) * Math.PI;
      needle.setAttribute("x2", (60 + 45 * Math.cos(rad)).toFixed(1));
      needle.setAttribute("y2", (65 - 45 * Math.sin(rad)).toFixed(1));
      needle.setAttribute("opacity", "1");
    }
    this.menu
      .querySelectorAll(".mageforge-toolbar-health-score-number")
      .forEach((el) => {
        el.textContent = score;
      });

    // Circular rings in audit panel headers
    this.menu.querySelectorAll(".mageforge-score-ring").forEach((ring) => {
      ring.setAttribute(
        "stroke-dasharray",
        `${((score / 100) * CIRCUMFERENCE).toFixed(2)} ${CIRCUMFERENCE}`,
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
    const CIRCUMFERENCE = 113.1;

    const panel = this.menu.querySelector(`[data-panel="${groupKey}"]`);
    if (!panel) return;

    const ring = panel.querySelector(".mageforge-score-ring");
    if (ring) {
      ring.setAttribute(
        "stroke-dasharray",
        `${((score / 100) * CIRCUMFERENCE).toFixed(2)} ${CIRCUMFERENCE}`,
      );
    }
    const number = panel.querySelector(".mageforge-score-number");
    if (number) {
      number.textContent = score;
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
    }
  },

  /**
   * Reset all score displays and deactivate all audits.
   */
  resetScore() {
    this.deactivateAllAudits();
    if (!this.menu) return;
    const ARC_LENGTH = 157.08;
    const CIRCUMFERENCE = 113.1;

    const progress = this.menu.querySelector(
      ".mageforge-health-gauge-progress",
    );
    const needle = this.menu.querySelector(".mageforge-health-gauge-needle");
    if (progress) progress.setAttribute("stroke-dasharray", `0 ${ARC_LENGTH}`);
    if (needle) needle.setAttribute("opacity", "0");
    this.menu
      .querySelectorAll(".mageforge-toolbar-health-score-number")
      .forEach((el) => {
        el.textContent = "--";
      });
    this.menu.querySelectorAll(".mageforge-score-ring").forEach((ring) => {
      ring.setAttribute("stroke-dasharray", `0 ${CIRCUMFERENCE}`);
    });
    this.menu.querySelectorAll(".mageforge-score-number").forEach((el) => {
      el.textContent = "--";
    });

    // Reset dashboard category badges
    this.menu
      .querySelectorAll("[data-dashboard-group-score]")
      .forEach((el) => {
        el.textContent = "--";
        el.classList.remove("mageforge-dashboard-category-score--active");
      });

    // Clear dashboard issues list
    if (this.dashboardIssuesEl) this.dashboardIssuesEl.innerHTML = "";

    // Reset all navigation badges
    this.updateHomeSummary();
  },

  // ────────────────────────────────────────────────────────────────────────
  // Menu open / close
  // ────────────────────────────────────────────────────────────────────────

  toggleMenu() {
    this.menuOpen ? this.closeMenu() : this.openMenu();
  },

  openMenu() {
    this.menuOpen = true;
    this.menu.classList.add("mageforge-menu-open");
    this.burgerButton.classList.add("mageforge-active");
    this.burgerButton.setAttribute("aria-expanded", "true");
  },

  closeMenu() {
    this.menuOpen = false;
    this.menu.classList.remove("mageforge-menu-open");
    this.burgerButton.classList.remove("mageforge-active");
    this.burgerButton.setAttribute("aria-expanded", "false");
  },

  destroyToolbar() {
    if (this._outsideClickHandler) {
      document.removeEventListener("click", this._outsideClickHandler);
      this._outsideClickHandler = null;
    }
    if (this.container?.parentNode)
      this.container.parentNode.removeChild(this.container);
    this.container = null;
    this.menu = null;
    this.burgerButton = null;
    this.runAllButton = null;
    this.resetButton = null;
    this.menuOpen = false;
  },
};
