/**
 * MageForge Toolbar Audit – Schema.org Microdata Viewer
 *
 * Reads all schema.org microdata (itemscope / itemtype / itemprop attributes)
 * from the current page DOM and renders a structured tree view in the findings
 * panel. Also detects RDFa (typeof / property attributes).
 *
 * Page-level audit: no DOM element highlighting, findings panel shows the data.
 */

const KEY = "schema-org-viewer";

/**
 * Recursively collect microdata properties from an itemscope element.
 *
 * @param {Element} root
 * @returns {{ type: string, props: Array<{name: string, value: string, nested?: object}> }}
 */
function collectMicrodata(root) {
  const type = root.getAttribute("itemtype") ?? "";
  const props = [];

  root.querySelectorAll("[itemprop]").forEach((el) => {
    // Skip deeply nested items already handled by their own itemscope
    const closestScope = el.parentElement?.closest("[itemscope]");
    if (closestScope && closestScope !== root) return;

    const name = el.getAttribute("itemprop") ?? "";
    let value = "";
    let nested = null;

    if (el.hasAttribute("itemscope")) {
      nested = collectMicrodata(el);
    } else if (el.tagName === "META") {
      value = el.getAttribute("content") ?? "";
    } else if (el.tagName === "LINK") {
      value = el.getAttribute("href") ?? "";
    } else if (el.tagName === "IMG") {
      value = el.getAttribute("src") ?? "";
    } else if (el.tagName === "TIME") {
      value = el.getAttribute("datetime") ?? el.textContent.trim();
    } else if (el.tagName === "A") {
      value = el.getAttribute("href") ?? el.textContent.trim();
    } else {
      value = el.textContent.trim().slice(0, 120);
    }

    props.push({ name, value, nested });
  });

  return { type, props };
}

/**
 * Collect RDFa-annotated root elements (typeof attribute on non-nested elements).
 *
 * @returns {Array<{type: string, el: Element}>}
 */
function collectRdfa() {
  return Array.from(document.querySelectorAll("[typeof]"))
    .filter((el) => !el.parentElement?.closest("[typeof]"))
    .map((el) => ({
      type: el.getAttribute("typeof") ?? "",
      el,
    }));
}

/**
 * Build a DOM tree for a collected microdata item.
 *
 * @param {{ type: string, props: Array }} item
 * @param {string} badgeLabel
 * @returns {HTMLElement}
 */
function buildBlockDOM(item, badgeLabel) {
  const typeShort = item.type.replace(/https?:\/\/schema\.org\//i, "");

  const block = document.createElement("div");
  block.className = "mageforge-jsonld-block";

  const header = document.createElement("button");
  header.type = "button";
  header.className = "mageforge-jsonld-block-header";
  header.setAttribute("aria-expanded", "false");
  // Static SVG only – dynamic text set via textContent (XSS-safe)
  header.innerHTML = `
    <span class="mageforge-jsonld-block-type">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
      <span class="mageforge-schema-type"></span>
      <span class="mageforge-schema-badge"></span>
    </span>
    <svg class="mageforge-jsonld-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
  `;
  header.querySelector(".mageforge-schema-type").textContent =
    typeShort || "Unknown Type";
  header.querySelector(".mageforge-schema-badge").textContent = badgeLabel;

  const content = document.createElement("div");
  content.className = "mageforge-jsonld-block-content";
  content.hidden = true;

  if (item.type) {
    const typeRow = document.createElement("div");
    typeRow.className = "mageforge-schema-type-row";
    const typePropName = document.createElement("span");
    typePropName.className = "mageforge-schema-prop-name";
    typePropName.textContent = "@type";
    typeRow.appendChild(typePropName);
    // Only linkify http(s) URLs to prevent javascript: injection
    if (/^https?:\/\//i.test(item.type)) {
      const typeLink = document.createElement("a");
      typeLink.className = "mageforge-schema-type-link";
      typeLink.href = item.type;
      typeLink.target = "_blank";
      typeLink.rel = "noopener noreferrer";
      typeLink.textContent = item.type;
      typeRow.appendChild(typeLink);
    } else {
      const typeText = document.createElement("span");
      typeText.className = "mageforge-schema-type-link";
      typeText.textContent = item.type;
      typeRow.appendChild(typeText);
    }
    content.appendChild(typeRow);
  }

  if (item.props.length === 0) {
    const empty = document.createElement("p");
    empty.className = "mageforge-jsonld-empty";
    empty.textContent = "No itemprop attributes found.";
    content.appendChild(empty);
  } else {
    const table = document.createElement("dl");
    table.className = "mageforge-schema-props";
    item.props.forEach(({ name, value, nested }) => {
      const dt = document.createElement("dt");
      dt.className = "mageforge-schema-prop-name";
      dt.textContent = name;
      table.appendChild(dt);

      const dd = document.createElement("dd");
      dd.className = "mageforge-schema-prop-value";
      if (nested) {
        const nestedType = nested.type.replace(/https?:\/\/schema\.org\//i, "");
        dd.textContent = `[${nestedType || "Nested item"} — ${nested.props.length} prop${nested.props.length !== 1 ? "s" : ""}]`;
        dd.classList.add("mageforge-schema-prop-nested");
      } else {
        dd.textContent = value || "—";
      }
      table.appendChild(dd);
    });
    content.appendChild(table);
  }

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
  icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
  label: "Schema.org Viewer",
  description:
    "Shows all schema.org microdata (itemscope/itemprop) and RDFa blocks",

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

    // Collect microdata root elements (not nested)
    const microdataRoots = Array.from(
      document.querySelectorAll("[itemscope][itemtype]"),
    ).filter((el) => !el.parentElement?.closest("[itemscope]"));

    const rdfaRoots = collectRdfa();
    const total = microdataRoots.length + rdfaRoots.length;

    context.setAuditCounterBadge(
      KEY,
      String(total),
      total > 0 ? "success" : "warning",
    );

    if (!findingsContainer) return;

    findingsContainer.innerHTML = "";
    findingsContainer.classList.add(
      "mageforge-has-findings",
      "mageforge-findings-open",
      "mageforge-jsonld-viewer-open",
    );

    if (total === 0) {
      const empty = document.createElement("p");
      empty.className = "mageforge-jsonld-empty";
      empty.textContent = "No schema.org microdata or RDFa found on this page.";
      findingsContainer.appendChild(empty);
      return;
    }

    const summary = document.createElement("div");
    summary.className = "mageforge-jsonld-summary";
    summary.innerHTML = `
      ${microdataRoots.length > 0 ? `<span class="mageforge-jsonld-count">${microdataRoots.length} microdata item${microdataRoots.length !== 1 ? "s" : ""}</span>` : ""}
      ${rdfaRoots.length > 0 ? `<span class="mageforge-schema-badge mageforge-schema-badge--rdfa">RDFa: ${rdfaRoots.length}</span>` : ""}
    `;
    findingsContainer.appendChild(summary);

    microdataRoots.forEach((el) => {
      const item = collectMicrodata(el);
      findingsContainer.appendChild(buildBlockDOM(item, "Microdata"));
    });

    rdfaRoots.forEach(({ type, el }) => {
      const typeShort = type.replace(/https?:\/\/schema\.org\//i, "");
      const props = Array.from(el.querySelectorAll("[property]"))
        .filter(
          (p) =>
            !p.parentElement?.closest("[typeof]") ||
            p.closest("[typeof]") === el,
        )
        .map((p) => ({
          name: p.getAttribute("property") ?? "",
          value:
            p.getAttribute("content") ??
            p.getAttribute("href") ??
            p.textContent.trim().slice(0, 120),
          nested: null,
        }));

      findingsContainer.appendChild(
        buildBlockDOM(
          { type: `https://schema.org/${typeShort}`, props },
          "RDFa",
        ),
      );
    });
  },
};
