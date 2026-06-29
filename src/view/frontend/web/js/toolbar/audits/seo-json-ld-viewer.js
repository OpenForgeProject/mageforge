/**
 * MageForge Toolbar Audit – JSON-LD Viewer
 *
 * Reads all <script type="application/ld+json"> blocks from the current page,
 * parses them, and renders a formatted viewer in the audit findings panel.
 * Invalid JSON blocks are flagged as errors.
 *
 * Page-level audit: no DOM element highlighting, findings panel shows the data.
 */

const KEY = "seo-json-ld-viewer";

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

    let errorCount = 0;
    const blocks = scripts.map((script, index) => {
      let parsed = null;
      let parseError = null;
      try {
        parsed = JSON.parse(script.textContent.trim());
      } catch (e) {
        parseError = e.message;
        errorCount++;
      }
      return { index, parsed, parseError, raw: script.textContent.trim() };
    });

    const severity = errorCount > 0 ? "error" : "success";
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
      ${errorCount > 0 ? `<span class="mageforge-jsonld-errors">${errorCount} invalid</span>` : ""}
    `;
    findingsContainer.appendChild(summary);

    blocks.forEach(({ index, parsed, parseError }) => {
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
        ? " — " + (name.length > 75 ? name.slice(0, 74) + "…" : name)
        : "";
      const typeLabel = type + nameLabel;

      const block = document.createElement("div");
      block.className =
        "mageforge-jsonld-block" +
        (parseError ? " mageforge-jsonld-block--error" : "");

      const header = document.createElement("button");
      header.type = "button";
      header.className = "mageforge-jsonld-block-header";
      header.setAttribute("aria-expanded", "false");
      header.innerHTML = `
        <span class="mageforge-jsonld-block-type">
          ${
            parseError
              ? '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
              : '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>'
          }
          ${parseError ? `Block ${index + 1} — Invalid JSON` : typeLabel}
        </span>
        <svg class="mageforge-jsonld-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
      `;

      const content = document.createElement("div");
      content.className = "mageforge-jsonld-block-content";
      content.hidden = true;

      if (parseError) {
        const errorMsg = document.createElement("p");
        errorMsg.className = "mageforge-jsonld-parse-error";
        errorMsg.textContent = `Parse error: ${parseError}`;
        content.appendChild(errorMsg);
      } else {
        const pre = document.createElement("pre");
        pre.className = "mageforge-jsonld-pre";
        const code = document.createElement("code");
        code.textContent = JSON.stringify(parsed, null, 2);
        pre.appendChild(code);
        content.appendChild(pre);

        const copyBtn = document.createElement("button");
        copyBtn.type = "button";
        copyBtn.className = "mageforge-jsonld-copy-btn";
        copyBtn.textContent = "Copy";
        copyBtn.onclick = (e) => {
          e.stopPropagation();
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
        content.appendChild(copyBtn);
      }

      header.onclick = (e) => {
        e.stopPropagation();
        const isOpen = content.hidden === false;
        content.hidden = isOpen;
        header.setAttribute("aria-expanded", String(!isOpen));
        header
          .querySelector(".mageforge-jsonld-chevron")
          .classList.toggle("mageforge-jsonld-chevron--open", !isOpen);
      };

      block.appendChild(header);
      block.appendChild(content);
      findingsContainer.appendChild(block);
    });
  },
};
