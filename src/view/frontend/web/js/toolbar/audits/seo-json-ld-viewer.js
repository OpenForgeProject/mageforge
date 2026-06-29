/**
 * MageForge Toolbar Audit – JSON-LD Viewer
 *
 * Reads all <script type="application/ld+json"> blocks from the current page,
 * parses them, renders a formatted viewer and validates required fields based
 * on Google Rich Results requirements.
 *
 * Page-level audit: no DOM element highlighting, findings panel shows the data.
 */

const KEY = "seo-json-ld-viewer";

/**
 * Required/recommended field rules per @type.
 * Based on Google Rich Results requirements:
 * https://developers.google.com/search/docs/appearance/structured-data
 *
 * Each entry has:
 *   fields: Array<{ path, severity, message }> — dot-notation field checks
 *   extra?: (parsed) => Array<{ severity, message }> — custom logic for nested/conditional checks
 */
const VALIDATION_RULES = {
  Product: {
    fields: [
      {
        path: "name",
        severity: "error",
        message: 'Missing required field "name"',
      },
      {
        path: "image",
        severity: "error",
        message: 'Missing required field "image"',
      },
      {
        path: "offers",
        severity: "error",
        message: 'Missing required field "offers"',
      },
      {
        path: "offers.price",
        severity: "error",
        message: 'Offer missing "price" (required for rich results)',
      },
      {
        path: "offers.priceCurrency",
        severity: "error",
        message: 'Offer missing "priceCurrency"',
      },
      {
        path: "offers.availability",
        severity: "error",
        message: 'Offer missing "availability" (required by Google)',
      },
      {
        path: "brand",
        severity: "warning",
        message: 'Missing "brand" (required for Google Merchant Center)',
      },
      {
        path: "sku",
        severity: "warning",
        message: 'Missing "sku" or "gtin" (recommended for Google Shopping)',
      },
      {
        path: "description",
        severity: "warning",
        message: 'Missing recommended field "description"',
      },
    ],
    extra(parsed) {
      const issues = [];
      if (parsed.aggregateRating) {
        if (!hasField(parsed, "aggregateRating.ratingValue")) {
          issues.push({
            severity: "error",
            message: '"aggregateRating" missing required "ratingValue"',
          });
        }
        const hasCount =
          hasField(parsed, "aggregateRating.reviewCount") ||
          hasField(parsed, "aggregateRating.ratingCount");
        if (!hasCount) {
          issues.push({
            severity: "error",
            message: '"aggregateRating" missing "reviewCount" or "ratingCount"',
          });
        }
      }
      if (parsed.brand && !hasField(parsed, "brand.name")) {
        issues.push({
          severity: "error",
          message: '"brand" object missing "name"',
        });
      }
      return issues;
    },
  },
  BreadcrumbList: {
    fields: [
      {
        path: "itemListElement",
        severity: "error",
        message: 'Missing required field "itemListElement"',
      },
    ],
    extra(parsed) {
      const issues = [];
      const items = parsed.itemListElement;
      if (!Array.isArray(items)) return issues;
      items.forEach((item, i) => {
        const n = i + 1;
        if (item.position == null) {
          issues.push({
            severity: "error",
            message: `ListItem[${n}] missing "position"`,
          });
        }
        if (!item.name) {
          issues.push({
            severity: "error",
            message: `ListItem[${n}] missing "name"`,
          });
        }
        if (!item.item) {
          issues.push({
            severity: "warning",
            message: `ListItem[${n}] missing "item" (URL)`,
          });
        }
      });
      return issues;
    },
  },
  Organization: {
    fields: [
      {
        path: "name",
        severity: "error",
        message: 'Missing required field "name"',
      },
      {
        path: "url",
        severity: "warning",
        message: 'Missing recommended field "url"',
      },
      {
        path: "logo",
        severity: "warning",
        message: 'Missing recommended field "logo"',
      },
    ],
  },
  WebSite: {
    fields: [
      {
        path: "name",
        severity: "error",
        message: 'Missing required field "name"',
      },
      {
        path: "url",
        severity: "error",
        message: 'Missing required field "url"',
      },
    ],
  },
  Article: {
    fields: [
      {
        path: "headline",
        severity: "error",
        message: 'Missing required field "headline"',
      },
      {
        path: "author",
        severity: "error",
        message: 'Missing required field "author"',
      },
      {
        path: "datePublished",
        severity: "error",
        message: 'Missing required field "datePublished"',
      },
      {
        path: "image",
        severity: "error",
        message: 'Missing required field "image"',
      },
    ],
  },
  NewsArticle: {
    fields: [
      {
        path: "headline",
        severity: "error",
        message: 'Missing required field "headline"',
      },
      {
        path: "author",
        severity: "error",
        message: 'Missing required field "author"',
      },
      {
        path: "datePublished",
        severity: "error",
        message: 'Missing required field "datePublished"',
      },
      {
        path: "image",
        severity: "error",
        message: 'Missing required field "image"',
      },
    ],
  },
  FAQPage: {
    fields: [
      {
        path: "mainEntity",
        severity: "error",
        message: 'Missing required field "mainEntity"',
      },
    ],
    extra(parsed) {
      const issues = [];
      const items = parsed.mainEntity;
      if (!Array.isArray(items)) return issues;
      items.forEach((item, i) => {
        const n = i + 1;
        if (!item.name) {
          issues.push({
            severity: "error",
            message: `Question[${n}] missing "name" (the question text)`,
          });
        }
        if (!hasField(item, "acceptedAnswer.text")) {
          issues.push({
            severity: "error",
            message: `Question[${n}] missing "acceptedAnswer.text"`,
          });
        }
      });
      return issues;
    },
  },
  LocalBusiness: {
    fields: [
      {
        path: "name",
        severity: "error",
        message: 'Missing required field "name"',
      },
      {
        path: "address",
        severity: "error",
        message: 'Missing required field "address"',
      },
      {
        path: "telephone",
        severity: "warning",
        message: 'Missing recommended field "telephone"',
      },
    ],
  },
  Event: {
    fields: [
      {
        path: "name",
        severity: "error",
        message: 'Missing required field "name"',
      },
      {
        path: "startDate",
        severity: "error",
        message: 'Missing required field "startDate"',
      },
      {
        path: "location",
        severity: "error",
        message: 'Missing required field "location"',
      },
    ],
  },
  Recipe: {
    fields: [
      {
        path: "name",
        severity: "error",
        message: 'Missing required field "name"',
      },
      {
        path: "image",
        severity: "error",
        message: 'Missing required field "image"',
      },
      {
        path: "author",
        severity: "error",
        message: 'Missing required field "author"',
      },
    ],
  },
  Order: {
    fields: [
      {
        path: "orderNumber",
        severity: "error",
        message: 'Missing required field "orderNumber"',
      },
      {
        path: "orderStatus",
        severity: "error",
        message: 'Missing required field "orderStatus"',
      },
      {
        path: "merchant",
        severity: "error",
        message: 'Missing required field "merchant" (seller/Organization)',
      },
      {
        path: "orderedItem",
        severity: "error",
        message: 'Missing required field "orderedItem"',
      },
      {
        path: "priceCurrency",
        severity: "warning",
        message: 'Missing recommended field "priceCurrency"',
      },
      {
        path: "price",
        severity: "warning",
        message: 'Missing recommended field "price" (order total)',
      },
      {
        path: "customer",
        severity: "warning",
        message: 'Missing recommended field "customer"',
      },
    ],
  },
};

/**
 * Resolve a dot-notation path on an object, traversing arrays if needed.
 *
 * @param {object} obj
 * @param {string} path
 * @returns {boolean} true if value exists and is non-empty
 */
function hasField(obj, path) {
  const parts = path.split(".");
  let current = obj;
  for (const part of parts) {
    if (current == null) return false;
    // If it's an array, check the first element
    if (Array.isArray(current)) current = current[0];
    if (current == null) return false;
    current = current[part];
  }
  if (current == null) return false;
  if (typeof current === "string") return current.trim().length > 0;
  return true;
}

/**
 * Validate a parsed JSON-LD object against known rules.
 *
 * @param {object} parsed
 * @returns {Array<{severity: string, message: string}>}
 */
/**
 * Validate a single JSON-LD node object against known rules.
 *
 * @param {object} node
 * @returns {Array<{severity: string, message: string}>}
 */
function validateNode(node) {
  if (!node || typeof node !== "object" || Array.isArray(node)) return [];

  const issues = [];

  if (!node["@context"]) {
    issues.push({
      severity: "error",
      message: 'Missing "@context" (should be "https://schema.org")',
    });
  }

  const type = node["@type"];
  if (!type) {
    issues.push({ severity: "warning", message: 'Missing "@type"' });
    return issues;
  }

  const config = VALIDATION_RULES[type];
  if (!config) return issues;

  (config.fields ?? []).forEach(({ path, severity, message }) => {
    if (!hasField(node, path)) {
      issues.push({ severity, message });
    }
  });

  if (typeof config.extra === "function") {
    issues.push(...config.extra(node));
  }

  return issues;
}

/**
 * Validate a parsed JSON-LD value, handling top-level arrays and @graph wrappers.
 *
 * @param {unknown} parsed
 * @returns {Array<{severity: string, message: string}>}
 */
function validate(parsed) {
  if (!parsed || typeof parsed !== "object") return [];

  // Top-level array: validate each node individually
  if (Array.isArray(parsed)) {
    return parsed.flatMap((node) => validateNode(node));
  }

  // @graph wrapper: the outer object carries @context; propagate it to each node
  if (Array.isArray(parsed["@graph"])) {
    const outerContext = parsed["@context"];
    return parsed["@graph"].flatMap((node) => {
      // Inherit outer @context when the node omits it
      const effective =
        outerContext && !node["@context"]
          ? { "@context": outerContext, ...node }
          : node;
      return validateNode(effective);
    });
  }

  return validateNode(parsed);
}

const ICON_ERROR =
  '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
const ICON_WARN =
  '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';

/** Extract a display label from a parsed JSON-LD object. */
function buildTypeLabel(parsed) {
  const type =
    parsed?.["@type"] ??
    (Array.isArray(parsed)
      ? parsed
          .map((b) => b?.["@type"])
          .filter(Boolean)
          .join(", ") || "Array"
      : "Unknown");
  const name = typeof parsed?.name === "string" ? parsed.name.trim() : null;
  const nameLabel = name
    ? " \u2014 " + (name.length > 75 ? name.slice(0, 74) + "\u2026" : name)
    : "";
  return type + nameLabel;
}

/** Build the collapsible block header button (XSS-safe). */
function buildBlockHeader(titleText, issues, parseError) {
  const hasErrors = issues.some((i) => i.severity === "error");
  const hasWarnings = issues.some((i) => i.severity === "warning");

  const header = document.createElement("button");
  header.type = "button";
  header.className = "mageforge-jsonld-block-header";
  header.setAttribute("aria-expanded", "false");
  header.innerHTML = `
    <span class="mageforge-jsonld-block-type">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
      <span class="mageforge-jsonld-block-title"></span>
      <span class="mageforge-jsonld-badge-slot"></span>
    </span>
    <svg class="mageforge-jsonld-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
  `;
  header.querySelector(".mageforge-jsonld-block-title").textContent = titleText;

  if (parseError || hasErrors || hasWarnings) {
    const errCount = issues.filter((i) => i.severity === "error").length;
    const warnCount = issues.filter((i) => i.severity === "warning").length;
    const isError = parseError || hasErrors;
    const badgeEl = document.createElement("span");
    badgeEl.className =
      "mageforge-jsonld-val-badge " +
      (isError
        ? "mageforge-jsonld-val-badge--error"
        : "mageforge-jsonld-val-badge--warning");
    badgeEl.innerHTML = isError ? ICON_ERROR : ICON_WARN;
    badgeEl.appendChild(
      document.createTextNode(
        isError
          ? ` ${parseError ? "Invalid JSON" : `${errCount} error${errCount !== 1 ? "s" : ""}`}`
          : ` ${warnCount} warning${warnCount !== 1 ? "s" : ""}`,
      ),
    );
    header.querySelector(".mageforge-jsonld-badge-slot").appendChild(badgeEl);
  }

  return header;
}

/** Build the issues list shown above the JSON code. */
function buildIssueList(issues) {
  const list = document.createElement("ul");
  list.className = "mageforge-jsonld-issues";
  issues.forEach(({ severity, message }) => {
    const li = document.createElement("li");
    li.className = `mageforge-jsonld-issue mageforge-jsonld-issue--${severity}`;
    li.innerHTML = severity === "error" ? ICON_ERROR : ICON_WARN;
    li.appendChild(document.createTextNode(` ${message}`));
    list.appendChild(li);
  });
  return list;
}

/** Build the copy button for a parsed JSON-LD block. */
function buildCopyButton(parsed) {
  const copyBtn = document.createElement("button");
  copyBtn.type = "button";
  copyBtn.className = "mageforge-jsonld-copy-btn";
  copyBtn.textContent = "Copy";
  copyBtn.onclick = (e) => {
    e.stopPropagation();
    if (!navigator.clipboard?.writeText) {
      copyBtn.textContent = "Not available";
      setTimeout(() => {
        copyBtn.textContent = "Copy";
      }, 1500);
      return;
    }
    navigator.clipboard
      .writeText(JSON.stringify(parsed, null, 2))
      .then(() => {
        copyBtn.textContent = "Copied!";
        setTimeout(() => {
          copyBtn.textContent = "Copy";
        }, 1500);
      })
      .catch(() => {
        copyBtn.textContent = "Failed";
        setTimeout(() => {
          copyBtn.textContent = "Copy";
        }, 1500);
      });
  };
  return copyBtn;
}

/** Build the expandable content area for one JSON-LD block. */
function buildBlockContent(parsed, parseError, issues) {
  const content = document.createElement("div");
  content.className = "mageforge-jsonld-block-content";
  content.hidden = true;

  if (parseError) {
    const errorMsg = document.createElement("p");
    errorMsg.className = "mageforge-jsonld-parse-error";
    errorMsg.textContent = `Parse error: ${parseError}`;
    content.appendChild(errorMsg);
    return content;
  }

  if (issues.length > 0) content.appendChild(buildIssueList(issues));

  const pre = document.createElement("pre");
  pre.className = "mageforge-jsonld-pre";
  const code = document.createElement("code");
  code.textContent = JSON.stringify(parsed, null, 2);
  pre.appendChild(code);
  content.appendChild(pre);
  content.appendChild(buildCopyButton(parsed));

  return content;
}

/** Build a complete collapsible block for one JSON-LD script. */
function buildBlock({ index, parsed, parseError, issues }) {
  const hasErrors = issues.some((i) => i.severity === "error");
  const hasWarnings = issues.some((i) => i.severity === "warning");

  const titleText = parseError
    ? `Block ${index + 1} \u2014 Invalid JSON`
    : buildTypeLabel(parsed);

  const block = document.createElement("div");
  block.className =
    "mageforge-jsonld-block" +
    (parseError || hasErrors ? " mageforge-jsonld-block--error" : "") +
    (!parseError && !hasErrors && hasWarnings
      ? " mageforge-jsonld-block--warning"
      : "");

  const header = buildBlockHeader(titleText, issues, parseError);
  const content = buildBlockContent(parsed, parseError, issues);

  header.onclick = (e) => {
    e.stopPropagation();
    const isOpen = !content.hidden;
    content.hidden = isOpen;
    header.setAttribute("aria-expanded", String(!isOpen));
    header
      .querySelector(".mageforge-jsonld-chevron")
      .classList.toggle("mageforge-jsonld-chevron--open", !isOpen);
  };

  block.appendChild(header);
  block.appendChild(content);
  return block;
}

export default {
  key: KEY,
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 4a2 2 0 0 0 -2 2v3a2 3 0 0 1 -2 3a2 3 0 0 1 2 3v3a2 2 0 0 0 2 2"></path><path d="M17 4a2 2 0 0 1 2 2v3a2 3 0 0 1 2 3a2 3 0 0 1 -2 3v3a2 2 0 0 1 -2 2"></path><path d="M11 12h1l1 3"></path><circle cx="11" cy="9" r=".5" fill="currentColor"></circle></svg>',
  label: "JSON-LD Viewer",
  description: "Shows all structured data (JSON-LD) blocks found on this page",

  run(context, active) {
    const auditItem = context.menu?.querySelector(`[data-audit-key="${KEY}"]`);
    const findingsContainer = auditItem?.querySelector(
      ".mageforge-audit-findings",
    );

    if (!active) {
      if (findingsContainer) {
        findingsContainer.innerHTML = "";
        findingsContainer.classList.remove(
          "mageforge-has-findings",
          "mageforge-findings-open",
          "mageforge-jsonld-viewer-open",
        );
      }
      return;
    }

    const scripts = Array.from(
      document.querySelectorAll('script[type="application/ld+json"]'),
    );

    if (scripts.length === 0) {
      context.setAuditCounterBadge(KEY, "0", "warning");
      if (findingsContainer) {
        findingsContainer.innerHTML = "";
        const empty = document.createElement("p");
        empty.className = "mageforge-jsonld-empty";
        empty.textContent = "No JSON-LD blocks found on this page.";
        findingsContainer.appendChild(empty);
        findingsContainer.classList.add(
          "mageforge-has-findings",
          "mageforge-findings-open",
          "mageforge-jsonld-viewer-open",
        );
      }
      return;
    }

    let parseErrorCount = 0;
    let validationErrorCount = 0;
    let validationWarningCount = 0;

    const blocks = scripts.map((script, index) => {
      let parsed = null;
      let parseError = null;
      let issues = [];
      try {
        parsed = JSON.parse(script.textContent.trim());
        issues = validate(parsed);
        validationErrorCount += issues.filter(
          (i) => i.severity === "error",
        ).length;
        validationWarningCount += issues.filter(
          (i) => i.severity === "warning",
        ).length;
      } catch (e) {
        parseError = e.message;
        parseErrorCount++;
      }
      return { index, parsed, parseError, issues };
    });

    const severity =
      parseErrorCount > 0 || validationErrorCount > 0
        ? "error"
        : validationWarningCount > 0
          ? "warning"
          : "success";
    context.setAuditCounterBadge(KEY, String(scripts.length), severity);

    if (!findingsContainer) return;

    findingsContainer.innerHTML = "";
    findingsContainer.classList.add(
      "mageforge-has-findings",
      "mageforge-findings-open",
      "mageforge-jsonld-viewer-open",
    );

    const summary = document.createElement("div");
    summary.className = "mageforge-jsonld-summary";
    summary.innerHTML = `
      <span class="mageforge-jsonld-count">${scripts.length} block${scripts.length !== 1 ? "s" : ""} found</span>
      ${parseErrorCount > 0 ? `<span class="mageforge-jsonld-errors">${parseErrorCount} invalid JSON</span>` : ""}
      ${validationErrorCount > 0 ? `<span class="mageforge-jsonld-errors">${validationErrorCount} missing required</span>` : ""}
      ${validationWarningCount > 0 ? `<span class="mageforge-jsonld-warnings">${validationWarningCount} warnings</span>` : ""}
    `;
    findingsContainer.appendChild(summary);

    blocks.forEach((blockData) => {
      findingsContainer.appendChild(buildBlock(blockData));
    });
  },
};
