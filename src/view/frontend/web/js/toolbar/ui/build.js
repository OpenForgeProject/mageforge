/**
 * MageForge Toolbar – DOM construction
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
 */

import {
  createLogoSvg,
  generateId,
  ICON_HOME,
  GROUP_ICONS,
  GAUGE_ARC_LENGTH,
  SCORE_RING_CIRCUMFERENCE,
} from "./constants.js";

export const buildMethods = {
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
    menu.setAttribute("role", "dialog");
    menu.setAttribute("aria-modal", "true");
    menu.setAttribute("aria-label", "MageForge Toolbar");
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
      <button type="button" class="mageforge-toolbar-menu-close" title="Close & deactivate all" aria-label="Close & deactivate all">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"></path></svg>
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

    this.getAuditGroups().forEach((group) => {
      nav.appendChild(
        this._buildNavTab(group.key, GROUP_ICONS[group.key] ?? "", group.label),
      );
    });

    nav.appendChild(this._buildNavTab("home", ICON_HOME, "Dashboard", true));

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
      btn.classList.toggle(
        "mageforge-group-reset-btn--disabled",
        !groupHasActive,
      );
      btn.setAttribute("aria-disabled", String(!groupHasActive));
      btn.setAttribute("tabindex", groupHasActive ? "0" : "-1");
    });

    // Home reset button: disabled when nothing is active at all
    if (this.resetButton) {
      const hasAny = this.activeAudits.size > 0;
      this.resetButton.classList.toggle(
        "mageforge-group-reset-btn--disabled",
        !hasAny,
      );
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
      <span class="mageforge-tab-label">${label}</span>
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
    homePanel.appendChild(this._buildHomePanel());
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

      const groupLabel = group.label;

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
      const groupResetBtn = document.createElement("button");
      groupResetBtn.type = "button";
      groupResetBtn.className = "mageforge-group-reset-btn";
      groupResetBtn.setAttribute("aria-label", `Reset ${groupLabel} audits`);
      groupResetBtn.title = `Reset ${groupLabel} audits`;
      groupResetBtn.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg> Reset';
      const handleGroupReset = () => {
        const btn = this[`groupResetButton-${group.key}`];
        if (btn?.classList.contains("mageforge-group-reset-btn--disabled"))
          return;
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
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12l7-7 7 7"></path></svg>
          Suggest a Audit
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

    if (showScore) {
      header.appendChild(this._buildScoreWidget());
    }

    return header;
  },

  /**
   * Compact circular score ring shown in every audit panel header.
   *
   * @returns {HTMLDivElement}
   */
  _buildScoreWidget() {
    const gradId = generateId("sg");
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
        <circle cx="22" cy="22" r="18" fill="none" stroke="var(--mageforge-border-color, rgba(148,163,184,0.15))" stroke-width="4"></circle>
        <circle cx="22" cy="22" r="18" fill="none" stroke="url(#${gradId})" stroke-width="4"
                stroke-dasharray="0 ${SCORE_RING_CIRCUMFERENCE}" stroke-linecap="round"
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
   * @returns {HTMLDivElement}
   */
  _buildHomePanel() {
    const panel = document.createElement("div");
    panel.className = "mageforge-home-panel";

    const gradId = generateId("gauge");
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
                fill="none" stroke="var(--mageforge-border-color)" stroke-width="10" stroke-linecap="round"></path>
          <path d="M 10 65 A 50 50 0 0 1 110 65"
                fill="none" stroke="url(#${gradId})" stroke-width="10" stroke-linecap="round"
                stroke-dasharray="0 ${GAUGE_ARC_LENGTH}" class="mageforge-health-gauge-progress"></path>
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
    this.resetButton = document.createElement("button");
    this.resetButton.type = "button";
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
    btnRow.appendChild(this.resetButton);
    // btnRow held as ref; rendered in footer action bar via _updateFooterActions

    panel.appendChild(this._buildPageContext());
    panel.appendChild(this._buildQuickStats());

    // Category score breakdown
    const categories = document.createElement("div");
    categories.className = "mageforge-dashboard-categories";
    [...this.getAuditGroups()]
      .sort((a, b) => a.label.localeCompare(b.label))
      .forEach((group) => {
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

    return panel;
  },

  _buildPageContext() {
    const classes = document.body.className;
    let pageType = "Page";
    if (classes.includes("catalog-product-view")) pageType = "Product";
    else if (classes.includes("catalog-category-view")) pageType = "Category";
    else if (classes.includes("checkout-index-index")) pageType = "Checkout";
    else if (classes.includes("checkout-cart-index")) pageType = "Cart";
    else if (classes.includes("catalogsearch-result-index"))
      pageType = "Search";
    else if (classes.includes("cms-index-index")) pageType = "Homepage";
    else if (classes.includes("cms-page-view")) pageType = "CMS Page";
    else if (classes.includes("customer-account")) pageType = "Account";

    const rawTitle = document.title.split(" - ")[0].trim();
    const title =
      rawTitle.length > 36 ? rawTitle.slice(0, 34) + "\u2026" : rawTitle;
    const path = location.pathname.replace(/\/+$/, "") || "/";
    const displayPath = path.length > 34 ? "\u2026" + path.slice(-32) : path;

    const el = document.createElement("div");
    el.className = "mageforge-page-context";

    const typeBadge = document.createElement("span");
    typeBadge.className = "mageforge-page-context-type";
    typeBadge.textContent = pageType;

    const titleEl = document.createElement("span");
    titleEl.className = "mageforge-page-context-title";
    titleEl.textContent = title || "(no title)";
    titleEl.title = document.title;

    const urlEl = document.createElement("span");
    urlEl.className = "mageforge-page-context-url";
    urlEl.textContent = displayPath;
    urlEl.title = path;

    el.appendChild(typeBadge);
    el.appendChild(titleEl);
    if (pageType !== "Homepage" && path !== "/") el.appendChild(urlEl);
    return el;
  },

  _buildQuickStats() {
    const allImgs = [...document.querySelectorAll("img")].filter(
      (img) => !this.container?.contains(img),
    );
    const imgNoAlt = allImgs.filter(
      (img) => img.getAttribute("alt") === null,
    ).length;
    const imgNoLazy = allImgs.filter(
      (img) => !img.getAttribute("loading"),
    ).length;
    const extScripts = document.querySelectorAll("script[src]").length;
    const inlineScripts = document.querySelectorAll("script:not([src])").length;
    const stylesheets = document.querySelectorAll(
      'link[rel="stylesheet"]',
    ).length;

    const el = document.createElement("div");
    el.className = "mageforge-quick-stats";

    const heading = document.createElement("p");
    heading.className = "mageforge-section-heading";
    heading.textContent = "Page Overview";
    el.appendChild(heading);

    const grid = document.createElement("div");
    grid.className = "mageforge-quick-stats-grid";

    const items = [
      {
        value: allImgs.length,
        label: "Images",
        sub:
          imgNoAlt > 0
            ? `${imgNoAlt} no alt`
            : imgNoLazy > 0
              ? `${imgNoLazy} no lazy`
              : "all good",
        warn: imgNoAlt > 0,
      },
      {
        value: extScripts,
        label: "JS Files",
        sub: `${inlineScripts} inline`,
        warn: false,
      },
      {
        value: stylesheets,
        label: "CSS Files",
        sub: null,
        warn: false,
      },
    ];

    items.forEach(({ value, label, sub, warn }) => {
      const item = document.createElement("div");
      item.className = "mageforge-quick-stat";

      const valueEl = document.createElement("span");
      valueEl.className = `mageforge-quick-stat-value${
        warn ? " mageforge-quick-stat-value--warn" : ""
      }`;
      valueEl.textContent = String(value);

      const labelEl = document.createElement("span");
      labelEl.className = "mageforge-quick-stat-label";
      labelEl.textContent = label;

      item.appendChild(valueEl);
      item.appendChild(labelEl);

      if (sub !== null) {
        const subEl = document.createElement("span");
        subEl.className = `mageforge-quick-stat-sub${
          warn ? " mageforge-quick-stat-sub--warn" : ""
        }`;
        subEl.textContent = sub;
        item.appendChild(subEl);
      }

      grid.appendChild(item);
    });

    el.appendChild(grid);
    return el;
  },

  // ────────────────────────────────────────────────────────────────────────
  // Footer
  // ────────────────────────────────────────────────────────────────────────

  /**
   * Footer row: export controls + credit line.
   *
   * @returns {HTMLDivElement}
   */
  _buildMenuFooter() {
    const footer = document.createElement("div");
    footer.className = "mageforge-toolbar-menu-footer";

    // Export format row ──────────────────────────────────────────────────
    const exportRow = document.createElement("div");
    exportRow.className = "mageforge-footer-theme-row";
    this._exportBtnRow = exportRow;
    const exportGroup = document.createElement("div");
    exportGroup.className = "mageforge-theme-toggle";
    const exportLabel = document.createElement("span");
    exportLabel.className = "mageforge-footer-theme-label";
    exportLabel.textContent = "Export";
    exportGroup.appendChild(exportLabel);
    [
      ["json", "JSON"],
      ["md", "MD"],
      ["txt", "TXT"],
    ].forEach(([fmt, label]) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "mageforge-theme-btn mageforge-export-btn--disabled";
      btn.dataset.exportFormat = fmt;
      btn.textContent = label;
      btn.disabled = true;
      btn.setAttribute("aria-label", `Export findings as ${label}`);
      btn.title = `Export findings as ${label}`;
      btn.onclick = (e) => {
        e.stopPropagation();
        this.exportFindings(fmt);
      };
      exportGroup.appendChild(btn);
    });
    exportRow.appendChild(exportGroup);
    footer.appendChild(exportRow);

    // Credit line (left side of export row) ─────────────────────────────
    const credit = document.createElement("div");
    credit.className = "mageforge-toolbar-menu-credit";
    credit.innerHTML =
      'Built with <span class="mageforge-toolbar-menu-credit-heart">\u2764</span> by <a href="https://github.com/OpenForgeProject/mageforge" target="_blank" rel="noopener noreferrer" class="mageforge-toolbar-menu-credit-link">MageForge</a>';
    exportRow.insertBefore(credit, exportRow.firstChild);

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
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mageforge-toolbar-burger";
    btn.title = "Audit tools";
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
};
