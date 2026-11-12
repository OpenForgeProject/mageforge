import { readFile } from "node:fs/promises";
import { Marked } from "marked";

export const GITHUB = "https://github.com/OpenForgeProject/mageforge";

export const escapeHtml = (s) =>
  String(s ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );

// Repeats until stable so nested fragments such as "<<b>script>" cannot reassemble into a tag.
const stripTags = (s) => {
  let prev;
  do {
    prev = s;
    s = s.replace(/<[^>]*>/g, "");
  } while (s !== prev);
  return s.replace(/[<>]/g, "");
};

export const slugify = (s) =>
  stripTags(s.toLowerCase())
    .replace(/[^\p{L}\p{N}]+/gu, "-")
    .replace(/^-+|-+$/g, "");

export const stripMarkdown = (s) =>
  s
    .replace(/```[\s\S]*?```/g, " ")
    .replace(/!\[[^\]]*\]\([^)]*\)/g, " ")
    .replace(/\[([^\]]+)\]\([^)]*\)/g, "$1")
    .replace(/[`*_>#|]/g, "")
    .replace(/\s+/g, " ")
    .trim();

const IMAGE_ALTS = {
  "MageForge-Header.png": "MageForge – Magento 2 front-end CLI toolkit",
  "MageForge-cli-chooser.png":
    "Interactive MageForge theme chooser in the terminal",
  "MageForge-Toolbar.jpeg":
    "MageForge toolbar and inspector overlay running in a Magento storefront",
  "MageForge-Merch.jpg": "MageForge merchandise: shirts and mugs",
};

const DOC_LINKS = {
  "docs/commands_reference.md": "/commands/",
  "commands_reference.md": "/commands/",
  "docs/custom_theme_builders.md": "/docs/custom-theme-builders/",
  "custom_theme_builders.md": "/docs/custom-theme-builders/",
  "docs/development.md": "/docs/development/",
  "development.md": "/docs/development/",
  "README.md": "/docs/",
};

// Rewrites links written for GitHub so they work on the website.
export function rewriteHref(href, { url }) {
  if (!href || /^(https?:|mailto:|#)/.test(href)) return href;
  const [path, hash] = href.split("#");
  const clean = path.replace(/^(\.\.?\/)+/, "");
  if (DOC_LINKS[clean]) return url(DOC_LINKS[clean]) + (hash ? `#${hash}` : "");
  return `${GITHUB}/blob/main/${clean}${hash ? `#${hash}` : ""}`;
}

export function createRenderer({ url, headingOffset = 0, assetBase }) {
  const marked = new Marked({ gfm: true });
  marked.use({
    renderer: {
      heading({ tokens, depth }) {
        const text = this.parser.parseInline(tokens);
        const id = slugify(text);
        const level = Math.min(6, Math.max(2, depth - headingOffset));
        return `<h${level} id="${id}"><a class="anchor" href="#${id}"><span class="visually-hidden">Link to section: </span>${text}</a></h${level}>\n`;
      },
      link({ href, title, tokens }) {
        const text = this.parser.parseInline(tokens);
        const target = rewriteHref(href, { url });
        const external =
          /^https?:/.test(target) && !target.startsWith(url("/"));
        const attrs = external ? ' rel="noopener noreferrer"' : "";
        return `<a href="${escapeHtml(target)}"${title ? ` title="${escapeHtml(title)}"` : ""}${attrs}>${text}</a>`;
      },
      image({ href, text }) {
        const file = href.split("/").pop();
        const alt = IMAGE_ALTS[file] || text;
        const src = /^https?:/.test(href) ? href : `${assetBase}/${file}`;
        return `<img src="${escapeHtml(src)}" alt="${escapeHtml(alt)}" loading="lazy" decoding="async">`;
      },
      table(token) {
        const head = token.header
          .map(
            (c) => `<th scope="col">${this.parser.parseInline(c.tokens)}</th>`,
          )
          .join("");
        const rows = token.rows
          .map(
            (r) =>
              `<tr>${r.map((c) => `<td>${this.parser.parseInline(c.tokens)}</td>`).join("")}</tr>`,
          )
          .join("");
        return `<div class="table-wrap" tabindex="0"><table><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table></div>\n`;
      },
      code({ text, lang }) {
        const language = (lang || "text").split(/\s/)[0];
        return `<div class="code"><pre tabindex="0"><code class="language-${escapeHtml(language)}">${escapeHtml(text)}</code></pre></div>\n`;
      },
    },
  });
  return (md) => marked.parse(md);
}

// Strips the first "# Title" line and returns [title, rest].
export function splitTitle(md) {
  const m = md.match(/^#\s+(.+)\n/);
  return m ? [m[1].trim(), md.slice(m[0].length)] : ["", md];
}

export function extractToc(html) {
  return [
    ...html.matchAll(
      /<h([23]) id="([^"]+)"><a class="anchor"[^>]*>(?:<span[^>]*>.*?<\/span>)?(.*?)<\/a><\/h\1>/g,
    ),
  ].map((m) => ({
    level: Number(m[1]),
    id: m[2],
    text: stripTags(m[3]),
  }));
}

export const commandSlug = (name) =>
  name.replace(/^mageforge:/, "").replace(/:/g, "-");

export async function loadCommands(file, meta) {
  const md = await readFile(file, "utf8");

  // Overview table: group, command, description, aliases
  const overview = new Map();
  for (const line of md.split("\n")) {
    const cells = line.split("|").map((c) => c.trim());
    const m = cells[2]?.match(/^`(mageforge:[^`]+)`$/);
    if (!m) continue;
    overview.set(m[1], {
      group: cells[1].replace(/\*/g, ""),
      description: cells[3],
      aliases: [...(cells[4] || "").matchAll(/`([^`]+)`/g)].map((a) => a[1]),
    });
  }

  const commands = [];
  const sections = md.split(/^### `/m).slice(1);
  for (const section of sections) {
    const name = section.slice(0, section.indexOf("`"));
    const info = overview.get(name);
    if (!info) continue;
    const body = section
      .slice(section.indexOf("\n") + 1)
      .replace(/\n---\s*(\n## [\s\S]*)?$/, "\n")
      .replace(/\n## [\s\S]*$/, "\n");
    const usage =
      body.match(/```bash\n([\s\S]*?)```/)?.[1].trim() ?? `bin/magento ${name}`;
    const rest = body.replace(/```bash\n[\s\S]*?```\n?/, "");
    const lead = body.trim().split("\n\n")[0].replace(/\n/g, " ");
    commands.push({
      name,
      slug: commandSlug(name),
      group: info.group,
      description: info.description,
      lead,
      aliases: info.aliases,
      usage,
      body: rest,
      since: meta[name]?.since ?? null,
      keywords: [
        ...new Set([
          name.replace(/^mageforge:/, ""),
          ...(meta[name]?.keywords ?? []),
        ]),
      ],
    });
  }
  return commands;
}

// Which releases mention a command, and where was it introduced?
export function linkReleasesToCommands(commands, releases) {
  for (const cmd of commands) {
    const needles = [cmd.name, ...cmd.aliases, ...cmd.keywords].map((k) =>
      k.toLowerCase(),
    );
    cmd.history = [];
    for (const rel of releases) {
      const items = [
        ...rel.groups.added,
        ...rel.groups.fixed,
        ...rel.groups.other,
      ].filter(
        (i) =>
          !i.noise &&
          needles.some(
            (n) =>
              i.text.toLowerCase().includes(n) ||
              `${i.scope}`.toLowerCase() === n,
          ),
      );
      if (items.length) cmd.history.push({ release: rel, items });
    }
  }
  // Commands without a curated version: use the oldest release that lists them under "Added".
  for (const cmd of commands.filter((c) => !c.since)) {
    const short = cmd.name.replace(/^mageforge:/, "");
    const needles = [
      cmd.name,
      ...cmd.aliases,
      short,
      short.replace(/:/g, " "),
    ].map((k) => k.toLowerCase());
    const hit = releases.filter((rel) =>
      rel.groups.added.some(
        (i) =>
          !i.noise && needles.some((n) => i.text.toLowerCase().includes(n)),
      ),
    );
    if (hit.length) cmd.since = hit[hit.length - 1].version;
  }
  for (const rel of releases) {
    rel.newCommands = commands.filter((c) => c.since === rel.version);
    rel.mentions = commands.filter((c) =>
      c.history.some((h) => h.release === rel),
    );
  }
}
