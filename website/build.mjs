import { readFile, writeFile, mkdir, rm, cp } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { join } from "node:path";
import { loadReleases, loadUpcoming } from "./lib/releases.mjs";
import {
  GITHUB,
  escapeHtml as e,
  slugify,
  stripMarkdown,
  createRenderer,
  splitTitle,
  extractToc,
  loadCommands,
  linkReleasesToCommands,
} from "./lib/content.mjs";
import { ogSvg, renderPng, iconPng, LOGO } from "./lib/og.mjs";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const HERE = fileURLToPath(new URL(".", import.meta.url));
const DIST = join(HERE, "dist");

const SITE_URL = (process.env.SITE_URL || "http://localhost:4173").replace(
  /\/$/,
  "",
);
const BASE = (process.env.BASE_PATH || "").replace(/\/$/, "");
if (!process.env.SITE_URL)
  console.warn(
    "! SITE_URL not set – canonical and social URLs point to http://localhost:4173",
  );

const url = (p) => BASE + p;
const abs = (p) => SITE_URL + BASE + p;
const host = new URL(SITE_URL).host;
const SITE_NAME = "MageForge";
const TAGLINE = "The CLI toolkit for Magento 2 front-end development";
const DEFAULT_DESC =
  "Build, watch, clean and inspect Magento, Hyvä and Tailwind CSS themes with one set of commands – plus a frontend inspector and audit toolbar.";

const pages = []; // { path, title, description, kind, ogTitle... } for sitemap
const searchIndex = [];
const files = new Map(); // path -> content (string|Buffer)
const out = (path, content) => files.set(path, content);

/* ------------------------------------------------------------------ data */
const meta = JSON.parse(
  await readFile(join(HERE, "data/commands-meta.json"), "utf8"),
);
const commands = await loadCommands(
  join(ROOT, "docs/commands_reference.md"),
  meta,
);
const releases = await loadReleases({
  cacheFile: join(HERE, ".cache/releases.json"),
  changelogFile: join(ROOT, "CHANGELOG.md"),
});
linkReleasesToCommands(commands, releases);
const latest = releases[0];
const upcoming = await loadUpcoming({
  cacheFile: join(HERE, ".cache/upcoming.json"),
  since: latest?.version,
});

function upcomingSection() {
  const features = upcoming.filter((i) => i.type === "feat");
  const fixes = upcoming.filter((i) => i.type !== "feat").slice(0, 8);
  const nextCommands = commands.filter((c) => !c.since);
  if (!features.length && !fixes.length && !nextCommands.length) return "";
  const li = (i) =>
    `<li>${i.scope ? `<span class="up-scope">${e(i.scope)}</span>` : ""}<span>${e(i.text)}</span>${i.pr ? ` <a class="up-pr" href="https://github.com/OpenForgeProject/mageforge/pull/${e(i.pr)}"><span class="visually-hidden">Pull request </span>#${e(i.pr)}</a>` : ""}</li>`;
  return `<section class="upcoming" aria-labelledby="up-h">
  <div class="container">
    <p class="up-eyebrow"><span class="up-dot" aria-hidden="true"></span>On the anvil</p>
    <h2 id="up-h">Upcoming features in the next version</h2>
    <p class="up-lead">Already merged on <code>main</code>, shipping with the next release.</p>
    ${nextCommands.length ? `<ul class="up-commands">${nextCommands.map((c) => `<li><a href="${url(`/commands/${c.slug}/`)}"><span class="badge badge-new">New command</span><code>${e(c.name)}</code><span>${e(c.description)}</span></a></li>`).join("")}</ul>` : ""}
    <div class="up-cols">
      ${features.length ? `<div><h3>New</h3><ul class="up-list">${features.map(li).join("")}</ul></div>` : ""}
      ${fixes.length ? `<div><h3>Improved &amp; fixed</h3><ul class="up-list up-list--quiet">${fixes.map(li).join("")}</ul></div>` : ""}
    </div>
  </div>
</section>`;
}
const groups = [...new Set(commands.map((c) => c.group))];
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "long",
  year: "numeric",
  timeZone: "UTC",
});
const fmtDate = (d) => dateFmt.format(new Date(`${d}T00:00:00Z`));
const readmeAssets = url("/assets/img");

/* ------------------------------------------------------------------ docs */
const docRender = createRenderer({
  url,
  headingOffset: 0,
  assetBase: readmeAssets,
});
const cmdRender = createRenderer({
  url,
  headingOffset: 2,
  assetBase: readmeAssets,
});

const readme = await readFile(join(ROOT, "README.md"), "utf8");
const gettingStarted = readme.slice(
  readme.indexOf("## Requirements"),
  readme.indexOf("## Commands Reference"),
);
const docs = [
  {
    slug: "",
    title: "Getting started",
    description:
      "Install MageForge, build your first theme and enable the frontend inspector.",
    md: gettingStarted,
  },
];
for (const [slug, file] of [
  ["custom-theme-builders", "custom_theme_builders.md"],
  ["development", "development.md"],
]) {
  const [title, md] = splitTitle(
    await readFile(join(ROOT, "docs", file), "utf8"),
  );
  const firstPara = stripMarkdown(
    md.split("\n\n").find((p) => /^[A-Z]/.test(p)) || "",
  ).slice(0, 170);
  docs.push({
    slug,
    title:
      slug === "development"
        ? "Development environment"
        : "Custom theme builders",
    description: firstPara || title,
    md,
  });
}
for (const d of docs) {
  d.path = d.slug ? `/docs/${d.slug}/` : "/docs/";
  // Drop the in-page table of contents of the development guide, the sidebar has its own.
  const md = d.md.replace(/^## Table of Contents\n[\s\S]*?(?=\n## )/m, "");
  d.html = docRender(md);
  d.toc = extractToc(d.html);
}

/* ------------------------------------------------------------- layout bits */
const icon = {
  search:
    '<svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
  menu: '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
  github:
    '<svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5a11.5 11.5 0 0 0-3.64 22.41c.58.1.79-.25.79-.56v-2c-3.2.7-3.88-1.37-3.88-1.37-.52-1.33-1.28-1.69-1.28-1.69-1.04-.71.08-.7.08-.7 1.16.08 1.77 1.19 1.77 1.19 1.03 1.76 2.7 1.25 3.36.96.1-.75.4-1.25.73-1.54-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.46.11-3.04 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.77 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.58.23 2.75.11 3.04.74.81 1.19 1.83 1.19 3.09 0 4.42-2.69 5.39-5.25 5.68.41.36.78 1.06.78 2.14v3.17c0 .31.21.67.8.56A11.5 11.5 0 0 0 12 .5Z"/></svg>',
};

const jsonLd = (...items) =>
  items
    .map(
      (i) =>
        `<script type="application/ld+json">${JSON.stringify(i).replace(/</g, "\\u003c")}</script>`,
    )
    .join("\n");

const breadcrumbLd = (trail) => ({
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  itemListElement: trail.map(([name, path], i) => ({
    "@type": "ListItem",
    position: i + 1,
    name,
    item: abs(path),
  })),
});

const breadcrumbs = (trail) =>
  `<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>${trail
    .map(([name, path], i) =>
      i === trail.length - 1
        ? `<li><span aria-current="page">${e(name)}</span></li>`
        : `<li><a href="${url(path)}">${e(name)}</a></li>`,
    )
    .join("")}</ol></nav>`;

const searchBox = (id, { page = false } = {}) => `
<div class="search" data-search${page ? "" : ""}>
  <form role="search" action="${url("/search/")}" method="get">
    <label for="${id}" class="visually-hidden">Search commands, documentation and changelog</label>
    <input id="${id}" type="search" name="q" placeholder="Search commands, docs, releases…" role="combobox" aria-expanded="false" aria-controls="${id}-list" aria-autocomplete="list" autocomplete="off" spellcheck="false">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
  <ul id="${id}-list" class="search-results ${page ? "" : "dialog-results"}" role="listbox" aria-label="Search suggestions" hidden></ul>
  <p class="visually-hidden" role="status" data-search-status></p>
</div>`;

const shareBlock = ({
  title,
  text,
  path,
  heading = "Share this page",
  blurb = "Know someone who builds Magento themes? Pass it on.",
}) => {
  const link = abs(path);
  const enc = encodeURIComponent;
  const items = [
    [
      "X",
      `https://twitter.com/intent/tweet?text=${enc(text)}&url=${enc(link)}`,
    ],
    [
      "LinkedIn",
      `https://www.linkedin.com/sharing/share-offsite/?url=${enc(link)}`,
    ],
    [
      "Bluesky",
      `https://bsky.app/intent/compose?text=${enc(`${text} ${link}`)}`,
    ],
    ["Mastodon", `https://toot.kytta.dev/?text=${enc(`${text} ${link}`)}`],
    [
      "Reddit",
      `https://www.reddit.com/submit?url=${enc(link)}&title=${enc(title)}`,
    ],
    [
      "Hacker News",
      `https://news.ycombinator.com/submitlink?u=${enc(link)}&t=${enc(title)}`,
    ],
    ["Email", `mailto:?subject=${enc(title)}&body=${enc(`${text}\n${link}`)}`],
  ];
  return `<section class="share" aria-labelledby="share-h">
  <h2 id="share-h">${e(heading)}</h2>
  <p>${e(blurb)}</p>
  <ul>
    <li><button type="button" data-share hidden data-title="${e(title)}" data-text="${e(text)}" data-url="${e(link)}">Share…</button></li>
    ${items.map(([n, h]) => `<li><a href="${e(h)}" target="_blank" rel="noopener noreferrer">${n}<span class="visually-hidden"> (opens in a new tab)</span></a></li>`).join("\n    ")}
    <li><button type="button" data-copy="${e(link)}" data-label="Link">Copy link</button></li>
  </ul>
</section>`;
};

function layout({
  title,
  description = DEFAULT_DESC,
  path,
  body,
  og,
  ld = [],
  noindex = false,
  current = "",
  fullTitle = false,
  lang = "en",
}) {
  const pageTitle = fullTitle ? title : `${title} · ${SITE_NAME}`;
  const canonical = abs(path);
  const ogUrl = abs(og?.path || "/assets/og/default.png");
  const nav = [
    ["/docs/", "Docs", "docs"],
    ["/commands/", "Commands", "commands"],
    ["/changelog/", "Changelog", "changelog"],
  ];
  return `<!doctype html>
<html lang="${lang}" data-base="${e(BASE)}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${e(pageTitle)}</title>
<meta name="description" content="${e(description)}">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0d1117">
${noindex ? '<meta name="robots" content="noindex">' : '<meta name="robots" content="index, follow, max-image-preview:large">'}
<link rel="canonical" href="${e(canonical)}">
<link rel="icon" href="${url("/favicon.svg")}" type="image/svg+xml">
<link rel="apple-touch-icon" href="${url("/apple-touch-icon.png")}">
<link rel="manifest" href="${url("/manifest.webmanifest")}">
<link rel="alternate" type="application/atom+xml" title="MageForge releases" href="${url("/feed.xml")}">
<meta property="og:type" content="${og?.type || "website"}">
<meta property="og:site_name" content="${SITE_NAME}">
<meta property="og:locale" content="en_GB">
<meta property="og:title" content="${e(og?.title || pageTitle)}">
<meta property="og:description" content="${e(description)}">
<meta property="og:url" content="${e(canonical)}">
<meta property="og:image" content="${e(ogUrl)}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="${e(og?.alt || `${SITE_NAME} – ${TAGLINE}`)}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${e(og?.title || pageTitle)}">
<meta name="twitter:description" content="${e(description)}">
<meta name="twitter:image" content="${e(ogUrl)}">
<meta name="twitter:image:alt" content="${e(og?.alt || `${SITE_NAME} – ${TAGLINE}`)}">
<link rel="stylesheet" href="${url("/assets/styles.css")}">
<script src="${url("/assets/app.js")}" defer></script>
${jsonLd(...ld)}
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
  <div class="container">
    <a class="brand" href="${url("/")}"><img src="${url("/assets/img/MageForge-Logo.svg")}" alt="" width="32" height="32"><span>MageForge</span></a>
    <nav class="nav" id="site-nav" aria-label="Main">
      ${nav.map(([p, l, k]) => `<a href="${url(p)}"${current === k ? ' aria-current="page"' : ""}>${l}</a>`).join("\n      ")}
      <a href="${GITHUB}" rel="noopener noreferrer">${icon.github}<span>GitHub</span></a>
    </nav>
    <button type="button" class="btn-ghost search-trigger" data-open-search aria-keyshortcuts="Control+K Meta+K /">${icon.search}<span>Search</span><kbd aria-hidden="true"><span data-mod>Ctrl</span>+K</kbd></button>
    <button type="button" class="btn-ghost nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav" hidden>${icon.menu}<span class="visually-hidden">Menu</span></button>
  </div>
</header>
<dialog id="search-dialog" class="search-dialog" aria-label="Search">
  <div class="dialog-head"><h2>Search</h2><button type="button" class="btn-ghost" data-close-search>Close<span class="visually-hidden"> search</span></button></div>
  ${searchBox("dlg-search")}
  <p class="hint">Tip: type a command such as <code>theme build</code>. Press <kbd>Esc</kbd> to close.</p>
</dialog>
<div id="live-region" class="visually-hidden" role="status" aria-live="polite"></div>
<main id="main" tabindex="-1">
${body}
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <img class="footer-logo" src="${url("/assets/img/MageForge-Slogan.svg")}" alt="MageForge" width="180" height="46">
      <p>Open source under GPL-3.0, built by the <a href="https://github.com/OpenForgeProject">OpenForgeProject</a> community.</p>
    </div>
    <nav aria-label="Product"><h2>Product</h2><ul>
      <li><a href="${url("/commands/")}">Commands</a></li>
      <li><a href="${url("/docs/")}">Documentation</a></li>
      <li><a href="${url("/changelog/")}">Changelog</a></li>
      <li><a href="${url("/feed.xml")}">Release feed (Atom)</a></li>
    </ul></nav>
    <nav aria-label="Community"><h2>Community</h2><ul>
      <li><a href="${GITHUB}">GitHub</a></li>
      <li><a href="${GITHUB}/issues">Issues</a></li>
      <li><a href="${GITHUB}/discussions">Discussions</a></li>
      <li><a href="https://packagist.org/packages/openforgeproject/mageforge">Packagist</a></li>
      <li><a href="https://mageforge.myspreadshop.de/">Merch</a></li>
    </ul></nav>
    <nav aria-label="Legal"><h2>Legal</h2><ul>
      <li><a href="${url("/impressum/")}" hreflang="de">Impressum</a></li>
      <li><a href="${url("/datenschutz/")}" hreflang="de">Datenschutz</a></li>
    </ul></nav>
  </div>
</footer>
</body>
</html>
`;
}

function addPage({ path, title, description, body, ...rest }) {
  const html = layout({ title, description, path, body, ...rest });
  out(`${path}index.html`.replace(/^\//, ""), html);
  pages.push({ path, lastmod: rest.lastmod });
}

const addSearch = (title, path, kind, text, aliases = "") =>
  searchIndex.push({
    t: title,
    u: path,
    k: kind,
    x: text.slice(0, 700),
    ...(aliases && { a: aliases }),
  });

/* -------------------------------------------------------- OG image queue */
const ogJobs = [];
const queueOg = (name, opts) => {
  ogJobs.push([name, opts]);
  return { path: `/assets/og/${name}.png` };
};

/* ------------------------------------------------------------ components */
const sinceBadge = (c) =>
  c.since
    ? `<a class="badge badge-new" href="${url(`/changelog/#v${c.since}`)}">Since v${e(c.since)}</a>`
    : `<span class="badge badge-new">Next release</span>`;

const commandCard = (c) => `<li><article class="card">
  <h3><a href="${url(`/commands/${c.slug}/`)}">${e(c.name).replace(/:/g, ":<wbr>")}</a></h3>
  <p>${e(c.description)}</p>
  <div class="card-foot"><span class="badge">${e(c.group)}</span>${c.since ? `<span class="badge">since ${e(c.since)}</span>` : '<span class="badge badge-new">Next release</span>'}</div>
</article></li>`;

const terminal = `<div class="terminal">
  <div class="terminal-bar" aria-hidden="true"><i></i><i></i><i></i><span>bin/magento</span></div>
<pre tabindex="0" aria-label="Example terminal session"><span class="t-prompt">$</span> bin/magento mageforge:theme:list
<span class="t-dim">Code              Title            Builder</span>
Magento/luma      Magento Luma     Magento Standard
Hyva/default      Hyvä Default     Hyvä (Tailwind 4)

<span class="t-prompt">$</span> bin/magento mageforge:theme:build Hyva/default
<span class="t-ok">✔</span> Dependencies in sync
<span class="t-ok">✔</span> Tailwind CSS compiled
<span class="t-ok">✔</span> Static content deployed <span class="t-dim">(4.2s)</span>

<span class="t-prompt">$</span> bin/magento mageforge:theme:watch Hyva/default
<span class="t-dim">Watching for changes… (Ctrl+C to stop)</span></pre>
</div>`;

/* ------------------------------------------------------------------ HOME */
{
  const featured = [
    "mageforge:theme:build",
    "mageforge:theme:watch",
    "mageforge:theme:inspector",
    "mageforge:hyva:compatibility:check",
    "mageforge:template:override",
    "mageforge:dependencies:update",
  ]
    .map((n) => commands.find((c) => c.name === n))
    .filter(Boolean);
  const newest = latest?.newCommands ?? [];
  const lastNew = releases.find((r) => r.newCommands.length);

  const body = `
<section class="hero" aria-labelledby="hero-h">
  <div class="container hero-grid">
    <div>
      <p class="eyebrow">Magento 2 · Hyvä · Tailwind CSS</p>
      <h1 id="hero-h">Forge your Magento <em>front end</em> faster.</h1>
      <p class="lead">${e(DEFAULT_DESC)}</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="${url("/docs/")}">Get started</a>
        <a class="btn btn-secondary" href="${url("/commands/")}">Explore ${commands.length} commands</a>
      </div>
      <div class="install"><code id="install-cmd">composer require openforgeproject/mageforge</code><button type="button" class="copy" data-copy="composer require openforgeproject/mageforge" data-label="Install command" aria-label="Copy install command">Copy</button></div>
      <div class="mt-lg">${searchBox("hero-search")}<p class="hint">Press <kbd>/</kbd> anywhere to search.</p></div>
      <ul class="hero-meta">
        <li><strong>${commands.length}</strong> commands</li>
        ${latest ? `<li>Latest <a href="${url("/changelog/")}"><strong>v${e(latest.version)}</strong></a></li>` : ""}
        <li>Magento <strong>2.4.7+</strong></li>
        <li>PHP <strong>8.3+</strong></li>
      </ul>
    </div>
    <div class="hero-art">
      <img class="pixel" src="${url("/assets/img/hero-pixel.svg")}" alt="Pixel art: a glowing MageForge emblem above a blacksmith's anvil, sparks flying into the night sky" width="960" height="540" fetchpriority="high">
    </div>
  </div>
</section>

${
  latest
    ? `<section class="section" aria-labelledby="latest-h"><div class="container">
  <div class="latest-strip">
    <div><h2 id="latest-h" class="h-compact">What's new in v${e(latest.version)}</h2>
    <p>Released ${fmtDate(latest.date)} · ${latest.groups.added.filter((i) => !i.noise).length} new features · ${latest.groups.fixed.length} fixes${newest.length ? ` · new: ${newest.map((c) => `<a href="${url(`/commands/${c.slug}/`)}"><code>${e(c.name)}</code></a>`).join(", ")}` : ""}</p></div>
    <a class="btn btn-secondary" href="${url("/changelog/")}">Read the changelog</a>
  </div>
  ${!newest.length && lastNew ? `<p class="hint">Latest new command: <a href="${url(`/commands/${lastNew.newCommands[0].slug}/`)}"><code>${e(lastNew.newCommands[0].name)}</code></a> in v${e(lastNew.version)}.</p>` : ""}
</div></section>`
    : ""
}

${upcomingSection()}

<section class="section" aria-labelledby="cmd-h"><div class="container">
  <div class="section-head"><h2 id="cmd-h">One toolkit, every theme type</h2>
  <p>Magento Standard, Hyvä, Hyvä Checkout, custom Tailwind CSS themes or your own builder – MageForge detects the theme type and does the right thing.</p></div>
  <ul class="grid">${featured.map(commandCard).join("")}</ul>
  <p class="mt-lg"><a class="btn btn-secondary" href="${url("/commands/")}">See all commands</a></p>
</div></section>

<section class="section" aria-labelledby="see-h"><div class="container split">
  <div><h2 id="see-h" class="mt-0">Fast feedback, minimal ceremony</h2>
  <p class="lead">Pick a theme interactively or pass codes and wildcards like <code>Magento/*</code>. Parent themes, Node dependencies and static content are handled for you.</p>
  <p><a href="${url("/commands/theme-build/")}">Learn about <code>theme:build</code> →</a></p></div>
  ${terminal}
</div></section>

<section class="section" aria-labelledby="insp-h"><div class="container split">
  <div class="shot"><img src="${url("/assets/img/MageForge-Toolbar.jpeg")}" alt="MageForge toolbar and inspector overlay running in a Magento storefront" loading="lazy" decoding="async" width="1668" height="1312"></div>
  <div><h2 id="insp-h" class="mt-0">Inspect and audit in the browser</h2>
  <p class="lead">The frontend inspector shows template paths, block classes, cache status and Web Vitals right on the page. The toolbar adds accessibility and usability audits with a health score.</p>
  <ul>
    <li>Template, block and module names at a glance</li>
    <li>PHP render times, cache lifetime and tags</li>
    <li>Web Vitals and accessibility checks per element</li>
    <li>Toggle with <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>I</kbd> or <kbd>Cmd</kbd>+<kbd>Option</kbd>+<kbd>I</kbd></li>
  </ul>
  <p><a href="${url("/commands/theme-inspector/")}">Enable the inspector →</a></p></div>
</div></section>

<section class="section" aria-labelledby="start-h"><div class="container">
  <div class="section-head"><h2 id="start-h">Up and running in three steps</h2></div>
  <ol class="steps">
    <li><h3>Install</h3><p>Add the module with Composer.</p><div class="code"><pre tabindex="0"><code>composer require openforgeproject/mageforge</code></pre></div></li>
    <li><h3>Enable</h3><p>Register the module in Magento.</p><div class="code"><pre tabindex="0"><code>bin/magento module:enable OpenForgeProject_MageForge
bin/magento setup:upgrade</code></pre></div></li>
    <li><h3>Build</h3><p>Compile your first theme.</p><div class="code"><pre tabindex="0"><code>bin/magento mageforge:theme:build Magento/luma</code></pre></div></li>
  </ol>
</div></section>
`;

  const ld = [
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: SITE_NAME,
      url: abs("/"),
      description: DEFAULT_DESC,
      inLanguage: "en",
      potentialAction: {
        "@type": "SearchAction",
        target: {
          "@type": "EntryPoint",
          urlTemplate: `${abs("/search/")}?q={search_term_string}`,
        },
        "query-input": "required name=search_term_string",
      },
    },
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      name: SITE_NAME,
      applicationCategory: "DeveloperApplication",
      operatingSystem: "Linux, macOS, Windows (WSL)",
      description: DEFAULT_DESC,
      softwareVersion: latest?.version,
      datePublished: releases.at(-1)?.date,
      dateModified: latest?.date,
      license: "https://www.gnu.org/licenses/gpl-3.0.html",
      codeRepository: GITHUB,
      downloadUrl: "https://packagist.org/packages/openforgeproject/mageforge",
      image: abs("/assets/og/default.png"),
      offers: { "@type": "Offer", price: "0", priceCurrency: "EUR" },
      author: {
        "@type": "Organization",
        name: "OpenForgeProject",
        url: "https://github.com/OpenForgeProject",
      },
    },
  ];
  addPage({
    path: "/",
    title: `${SITE_NAME} – ${TAGLINE}`,
    fullTitle: true,
    description: DEFAULT_DESC,
    body,
    ld,
    og: queueOg("default", {
      eyebrow: "Open source · GPL-3.0",
      title: "The CLI toolkit for Magento 2 front ends",
      subtitle:
        "Build, watch, clean and inspect Magento, Hyvä and Tailwind themes.",
      badge: latest ? `v${latest.version}` : undefined,
      host,
    }),
  });
}

/* -------------------------------------------------------------- COMMANDS */
{
  const body = `
<div class="page-head"><div class="container">
  ${breadcrumbs([
    ["Home", "/"],
    ["Commands", "/commands/"],
  ])}
  <h1>Commands</h1>
  <p>${commands.length} commands for building, watching, cleaning and inspecting themes. Every command page is shareable and shows the version it arrived in.</p>
</div></div>
<div class="container commands-list">
  ${groups
    .map(
      (g) =>
        `<h2 class="group-title" id="${slugify(g)}">${e(g)}</h2><ul class="grid">${commands
          .filter((c) => c.group === g)
          .map(commandCard)
          .join("")}</ul>`,
    )
    .join("\n")}
  ${shareBlock({ title: "MageForge commands", text: `${commands.length} CLI commands for Magento 2 front-end development with MageForge`, path: "/commands/", heading: "Tell people about MageForge", blurb: "Share the full command list with your team or community." })}
</div>`;
  addPage({
    path: "/commands/",
    title: "Commands",
    description: `All ${commands.length} MageForge commands for Magento 2 themes: build, watch, clean, inspector, Hyvä tokens and more.`,
    current: "commands",
    body,
    og: queueOg("commands", {
      eyebrow: "Commands",
      title: `${commands.length} commands for Magento 2 front-end work`,
      subtitle: "Build · Watch · Clean · Inspect · Hyvä tooling",
      host,
    }),
    ld: [
      {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        name: "MageForge commands",
        url: abs("/commands/"),
      },
      {
        "@context": "https://schema.org",
        "@type": "ItemList",
        itemListElement: commands.map((c, i) => ({
          "@type": "ListItem",
          position: i + 1,
          name: c.name,
          url: abs(`/commands/${c.slug}/`),
        })),
      },
      breadcrumbLd([
        ["Home", "/"],
        ["Commands", "/commands/"],
      ]),
    ],
  });
  addSearch(
    "Commands overview",
    "/commands/",
    "Command",
    commands.map((c) => `${c.name} ${c.description}`).join(" "),
  );

  commands.forEach((c, i) => {
    const path = `/commands/${c.slug}/`;
    const prev = commands[i - 1];
    const next = commands[i + 1];
    const html = cmdRender(c.body);
    const history = c.history.slice(0, 8);
    const body = `
<div class="page-head"><div class="container">
  ${breadcrumbs([
    ["Home", "/"],
    ["Commands", "/commands/"],
    [c.name, path],
  ])}
  <h1><code class="h1-code">${e(c.name)}</code></h1>
  <p>${e(c.description)}</p>
  <div class="meta-row">${sinceBadge(c)}<span class="badge">${e(c.group)}</span>${c.aliases.map((a) => `<span class="badge">alias: <code>${e(a)}</code></span>`).join("")}</div>
</div></div>
<div class="container layout">
  <article class="prose">
    <h2 id="run-it">Run it</h2>
    <div class="code"><pre tabindex="0"><code class="language-bash">${e(c.usage)}</code></pre></div>
    ${html}
    ${history.length ? `<h2 id="history">Version history</h2><ul>${history.map((h) => `<li><a href="${url(`/changelog/#v${h.release.version}`)}"><strong>v${e(h.release.version)}</strong></a> <span class="visually-hidden">–</span> ${e(h.items[0].text)}${h.items.length > 1 ? ` <em>(+${h.items.length - 1} more)</em>` : ""}</li>`).join("")}</ul>` : ""}
    ${shareBlock({ title: `${c.name} – MageForge`, text: `${c.name}: ${c.description} #Magento #MageForge`, path, heading: `Share ${c.name}`, blurb: "Show your team what MageForge can do." })}
    <nav class="pager" aria-label="More commands">
      ${prev ? `<a class="prev" href="${url(`/commands/${prev.slug}/`)}" rel="prev"><small>Previous</small><code>${e(prev.name)}</code></a>` : "<span></span>"}
      ${next ? `<a class="next" href="${url(`/commands/${next.slug}/`)}" rel="next"><small>Next</small><code>${e(next.name)}</code></a>` : ""}
    </nav>
  </article>
</div>`;
    addPage({
      path,
      title: c.name,
      description:
        `${c.description}. ${c.since ? `Available since MageForge ${c.since}.` : ""} Usage, arguments and options.`.replace(
          /\.\s*\./g,
          ".",
        ),
      current: "commands",
      body,
      og: {
        type: "article",
        ...queueOg(`cmd-${c.slug}`, {
          eyebrow: `${c.group} command`,
          title: c.name,
          subtitle: c.description,
          badge: c.since ? `Since v${c.since}` : "Next release",
          mono: true,
          host,
        }),
        alt: `${c.name} – ${c.description}`,
      },
      ld: [
        {
          "@context": "https://schema.org",
          "@type": "TechArticle",
          headline: c.name,
          description: c.description,
          url: abs(path),
          image: abs(`/assets/og/cmd-${c.slug}.png`),
          inLanguage: "en",
          isPartOf: { "@type": "WebSite", name: SITE_NAME, url: abs("/") },
          about: { "@type": "SoftwareApplication", name: SITE_NAME },
          dateModified: latest?.date,
        },
        breadcrumbLd([
          ["Home", "/"],
          ["Commands", "/commands/"],
          [c.name, path],
        ]),
      ],
    });
    addSearch(
      c.name,
      path,
      "Command",
      `${c.description} ${stripMarkdown(c.body)}`,
      `${c.aliases.join(" ")} ${c.name.replace(/:/g, " ")}`,
    );
  });
}

/* ------------------------------------------------------------------ DOCS */
{
  const nav = (
    active,
  ) => `<nav class="side" aria-label="Documentation"><h2>Documentation</h2><ul>
    ${docs.map((d) => `<li><a href="${url(d.path)}"${d.path === active ? ' aria-current="page"' : ""}>${e(d.title)}</a></li>`).join("")}
    <li><a href="${url("/commands/")}">Commands reference</a></li>
    <li><a href="${url("/changelog/")}">Changelog</a></li>
  </ul></nav>`;
  for (const d of docs) {
    const toc = d.toc.length
      ? `<details class="toc side" open><summary>On this page</summary><nav aria-label="On this page"><ul>${d.toc.map((t) => `<li><a class="toc-${t.level}" href="#${t.id}">${t.text}</a></li>`).join("")}</ul></nav></details>`
      : "<div></div>";
    const trail = [
      ["Home", "/"],
      ["Docs", "/docs/"],
      ...(d.slug ? [[d.title, d.path]] : []),
    ];
    const body = `
<div class="page-head"><div class="container">
  ${breadcrumbs(trail)}
  <h1>${e(d.title)}</h1>
  <p>${e(d.description)}</p>
</div></div>
<div class="container layout layout-docs">
  ${nav(d.path)}
  <article class="prose">${d.html}
    <p><a href="${GITHUB}/edit/main/${d.slug ? `docs/${d.slug === "development" ? "development" : "custom_theme_builders"}.md` : "README.md"}" rel="noopener noreferrer">Edit this page on GitHub</a></p>
    ${shareBlock({ title: `${d.title} – MageForge docs`, text: `${d.title} – MageForge documentation #Magento #MageForge`, path: d.path })}
  </article>
  ${toc}
</div>`;
    addPage({
      path: d.path,
      title: `${d.title} – Docs`,
      description: d.description,
      current: "docs",
      body,
      og: queueOg(`docs-${d.slug || "index"}`, {
        eyebrow: "Documentation",
        title: d.title,
        subtitle: d.description,
        host,
      }),
      ld: [
        {
          "@context": "https://schema.org",
          "@type": "TechArticle",
          headline: d.title,
          description: d.description,
          url: abs(d.path),
          inLanguage: "en",
          dateModified: latest?.date,
        },
        breadcrumbLd(trail),
      ],
    });
    // Index per section so search jumps straight to the right heading
    const sections = d.md.split(/^## /m);
    sections.forEach((s, i) => {
      const heading =
        i === 0 ? d.title : s.split("\n")[0].replace(/[`*]/g, "").trim();
      if (!heading) return;
      addSearch(
        i === 0 ? d.title : `${heading} – ${d.title}`,
        i === 0 ? d.path : `${d.path}#${slugify(heading)}`,
        "Docs",
        stripMarkdown(i === 0 ? s : s.slice(s.indexOf("\n"))),
      );
    });
  }
}

/* ------------------------------------------------------------- CHANGELOG */
{
  const items = (list, { limit } = {}) =>
    `<ul>${list
      .slice(0, limit)
      .map(
        (i) =>
          `<li>${i.scope ? `<span class="scope">${e(i.scope)}</span>` : ""}${e(i.text).replace(/`([^`]+)`/g, "<code>$1</code>")}${i.pr ? ` <a href="${e(i.pr.url)}" rel="noopener noreferrer">#${e(i.pr.number)}</a>` : ""}</li>`,
      )
      .join("")}</ul>`;

  const plural = (n) => `${n} new command${n > 1 ? "s" : ""}`;

  const releaseHead = (r, idx, open) => {
    const level = open ? 2 : 3;
    const latestBadge =
      idx === 0 ? '<span class="badge badge-new">Latest</span>' : "";
    const cmdBadge = r.newCommands.length
      ? `<span class="badge badge-new">${plural(r.newCommands.length)}</span>`
      : "";
    return `<header><h${level} id="v${e(r.version)}">v${e(r.version)}</h${level}><time datetime="${e(r.date)}">${fmtDate(r.date)}</time>${latestBadge}${cmdBadge}<a href="${e(r.url)}" rel="noopener noreferrer">GitHub release<span class="visually-hidden"> v${e(r.version)}</span></a></header>`;
  };

  const commandLink = (c) =>
    `<a href="${url(`/commands/${c.slug}/`)}"><code>${e(c.name)}</code></a>`;

  const releaseBody = (r, open) => {
    const added = r.groups.added.filter((i) => !i.noise);
    const fixed = r.groups.fixed;
    const rest = [...r.groups.other, ...r.groups.added.filter((i) => i.noise)];
    const sub = open ? 3 : 4;
    const section = (label, list) =>
      list.length
        ? `<h${sub} class="rel-label">${label}</h${sub}>${items(list)}`
        : "";
    const empty = !added.length && !fixed.length && !rest.length;
    return `
    ${r.newCommands.map((c) => `<p class="callout"><span class="badge badge-new">New command</span><a href="${url(`/commands/${c.slug}/`)}">${e(c.name)}</a><span>${e(c.description)}</span></p>`).join("")}
    ${section("Added", added)}
    ${section("Fixed", fixed)}
    ${rest.length ? `<details><summary>Maintenance and other changes (${rest.length})</summary>${items(rest)}</details>` : ""}
    ${empty ? `<p>Early release – see the <a href="${e(r.url)}" rel="noopener noreferrer">release notes on GitHub</a>.</p>` : ""}
    ${r.mentions.length ? `<p class="hint">Related commands: ${r.mentions.map(commandLink).join(", ")}</p>` : ""}`;
  };

  const releaseHtml = (r, idx) => {
    const open = idx < 3;
    const head = releaseHead(r, idx, open);
    const inner = releaseBody(r, open);
    if (open) {
      return `<article class="release${idx === 0 ? " is-latest" : ""}" aria-labelledby="v${e(r.version)}">${head}${inner}</article>`;
    }
    const summary = `<strong id="v${e(r.version)}">v${e(r.version)}</strong> · ${fmtDate(r.date)}${r.newCommands.length ? ` · ${plural(r.newCommands.length)}` : ""}`;
    return `<article class="release collapsed"><details><summary><span>${summary}</span></summary>${head}${inner}</details></article>`;
  };

  const timeline = releases
    .filter((r) => r.newCommands.length)
    .map(
      (r) =>
        `<li><strong><a href="#v${e(r.version)}">v${e(r.version)}</a></strong> <span class="hint">${fmtDate(r.date)}</span><ul>${r.newCommands.map((c) => `<li><a href="${url(`/commands/${c.slug}/`)}">${e(c.name)}</a></li>`).join("")}</ul></li>`,
    )
    .join("");
  const unreleased = commands.filter((c) => !c.since);

  const body = `
<div class="page-head"><div class="container">
  ${breadcrumbs([
    ["Home", "/"],
    ["Changelog", "/changelog/"],
  ])}
  <h1>Changelog</h1>
  <p>Every release, straight from <a href="${GITHUB}/releases" rel="noopener noreferrer">GitHub</a>. Rebuilt automatically whenever a new version is published${latest ? ` – currently <strong>v${e(latest.version)}</strong> (${fmtDate(latest.date)})` : ""}. <a href="${url("/feed.xml")}">Subscribe via Atom</a>.</p>
</div></div>
<div class="container layout layout-single">
  <section aria-labelledby="newcmd-h">
    <h2 id="newcmd-h" class="mt-0">New commands by version</h2>
    ${unreleased.length ? `<p class="callout"><span class="badge badge-new">Coming in the next release</span>${unreleased.map((c) => `<a href="${url(`/commands/${c.slug}/`)}">${e(c.name)}</a>`).join(" ")}</p>` : ""}
    <ol class="timeline" reversed>${timeline}</ol>
  </section>
  <section aria-labelledby="rel-h">
    <h2 id="rel-h">All releases</h2>
    ${releases.map(releaseHtml).join("\n")}
  </section>
  ${shareBlock({ title: "MageForge changelog", text: latest ? `MageForge v${latest.version} is out – see what's new #Magento #MageForge` : "What's new in MageForge", path: "/changelog/", heading: "Spread the news", blurb: "Share the latest MageForge release with your community." })}
</div>`;
  addPage({
    path: "/changelog/",
    title: "Changelog",
    description: latest
      ? `MageForge ${latest.version}: ${latest.groups.added
          .filter((i) => !i.noise)
          .slice(0, 3)
          .map((i) => i.text)
          .join("; ")}. Full release history with new commands per version.`
      : "MageForge release history.",
    current: "changelog",
    body,
    lastmod: latest?.date,
    og: queueOg("changelog", {
      eyebrow: "Changelog",
      title: latest ? `MageForge v${latest.version} is out` : "Changelog",
      subtitle: latest
        ? latest.groups.added
            .filter((i) => !i.noise)
            .slice(0, 2)
            .map((i) => i.text)
            .join(" · ")
        : "",
      badge: latest ? fmtDate(latest.date) : undefined,
      host,
    }),
    ld: [
      {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        name: "MageForge changelog",
        url: abs("/changelog/"),
        dateModified: latest?.date,
      },
      {
        "@context": "https://schema.org",
        "@type": "ItemList",
        itemListElement: releases.slice(0, 20).map((r, i) => ({
          "@type": "ListItem",
          position: i + 1,
          name: `MageForge ${r.version}`,
          url: abs(`/changelog/#v${r.version}`),
        })),
      },
      breadcrumbLd([
        ["Home", "/"],
        ["Changelog", "/changelog/"],
      ]),
    ],
  });
  for (const r of releases) {
    addSearch(
      `Release v${r.version}`,
      `/changelog/#v${r.version}`,
      "Changelog",
      [...r.groups.added, ...r.groups.fixed, ...r.groups.other]
        .filter((i) => !i.noise)
        .map((i) => i.text)
        .join(". ") || "Early release",
      `${r.version} ${r.newCommands.map((c) => c.name).join(" ")}`,
    );
  }

  // Atom feed
  out(
    "feed.xml",
    `<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
<title>MageForge releases</title>
<subtitle>${e(TAGLINE)}</subtitle>
<author><name>OpenForgeProject</name></author>
<id>${abs("/changelog/")}</id>
<link rel="self" href="${abs("/feed.xml")}"/>
<link rel="alternate" href="${abs("/changelog/")}"/>
<updated>${latest ? `${latest.date}T00:00:00Z` : new Date().toISOString()}</updated>
${releases
  .slice(0, 30)
  .map((r) => {
    const list = [...r.groups.added, ...r.groups.fixed].filter((i) => !i.noise);
    return `<entry><title>MageForge ${e(r.version)}</title><id>${abs(`/changelog/#v${r.version}`)}</id><link rel="alternate" href="${abs(`/changelog/#v${r.version}`)}"/><updated>${r.date}T00:00:00Z</updated><summary type="html">${e(`<ul>${list.map((i) => `<li>${e(i.text)}</li>`).join("")}</ul>`)}</summary></entry>`;
  })
  .join("\n")}
</feed>
`,
  );
}

/* ---------------------------------------------------------------- SEARCH */
addPage({
  path: "/search/",
  title: "Search",
  description: "Search MageForge commands, documentation and releases.",
  noindex: true,
  body: `
<div class="page-head"><div class="container">${breadcrumbs([
    ["Home", "/"],
    ["Search", "/search/"],
  ])}<h1>Search</h1></div></div>
<div class="container layout layout-single">
  <div data-search-page>
    <form role="search" action="${url("/search/")}" method="get" class="search search-page-form">
      <label for="page-q" class="visually-hidden">Search</label>
      <input id="page-q" type="search" name="q" placeholder="Search commands, docs, releases…" autocomplete="off" spellcheck="false" autofocus>
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <p id="search-status" role="status" class="hint"></p>
    <ul id="search-results" class="search-results search-results--page"></ul>
    <noscript><p>Search needs JavaScript. Browse the <a href="${url("/commands/")}">commands</a>, <a href="${url("/docs/")}">docs</a> or <a href="${url("/changelog/")}">changelog</a> instead.</p></noscript>
  </div>
</div>`,
});
pages.pop(); // keep the search page out of the sitemap

/* ----------------------------------------------------------------- LEGAL */
const OPERATOR = `<p><strong>Mathias Elle</strong><br>Schöneberger Weg 26<br>78056 Villingen-Schwenningen<br>E-Mail: <a href="mailto:hello@dermatz.de">hello@dermatz.de</a></p>`;

const legalPages = [
  {
    path: "/impressum/",
    title: "Impressum",
    description: "Anbieterkennzeichnung und rechtliche Hinweise zu mageforge.",
    html: `
<h2>Angaben gemäß § 5 DDG</h2>
${OPERATOR}
<p>Kleinunternehmer nach § 19 UStG</p>
<h2>Umsatzsteuer-ID</h2>
<p>Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz: <em>DE288302596</em></p>
<h2>Haftungsausschluss (Disclaimer)</h2>
<h3>1. Haftung für Inhalte</h3>
<p>Die Inhalte unserer Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen. Als Diensteanbieter sind wir für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Wir sind als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.</p>
<p>Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.</p>
<h3>2. Haftung für Links</h3>
<p>Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.</p>
<p>Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.</p>
<h3>3. Urheberrecht</h3>
<p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.</p>
<p>MageForge selbst ist Open-Source-Software und steht unter der <a href="https://www.gnu.org/licenses/gpl-3.0.html" rel="noopener noreferrer">GPL-3.0</a>; für den Quellcode gelten die Bedingungen dieser Lizenz.</p>
<p>Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.</p>
<h2>Alternative Streitbeilegung</h2>
<p>Zur Teilnahme an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle sind wir nicht verpflichtet und nicht bereit (§ 36 VSBG).</p>`,
  },
  {
    path: "/datenschutz/",
    title: "Datenschutz",
    description: "Datenschutzerklärung für die MageForge-Website.",
    html: `
<h2>Verantwortlicher</h2>
${OPERATOR}
<h2>Allgemeine Hinweise</h2>
<p>Der Schutz deiner persönlichen Daten ist uns ein besonderes Anliegen. Wir verarbeiten deine Daten ausschließlich auf Grundlage der gesetzlichen Bestimmungen (DSGVO, TDDDG). Diese Erklärung informiert dich über die wichtigsten Aspekte der Datenverarbeitung auf dieser Website.</p>
<h2>Hosting über GitHub Pages</h2>
<p>Diese Website wird über <strong>GitHub Pages</strong> bereitgestellt, einen Dienst der GitHub Inc., 88 Colin P. Kelly Jr. Street, San Francisco, CA 94107, USA (für Nutzer im EWR: GitHub B.V., Prins Bernhardplein 200, 1097 JB Amsterdam, Niederlande).</p>
<p>Beim Aufruf der Seite verarbeitet GitHub technisch notwendige Verbindungsdaten, insbesondere deine IP-Adresse, Datum und Uhrzeit des Zugriffs, die aufgerufene URL, Browser- und Betriebssysteminformationen sowie die Referrer-URL. Wir selbst erheben und speichern diese Daten nicht und haben keinen Zugriff auf diese Logs. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einer sicheren und effizienten Bereitstellung der Website).</p>
<p>Eine Übermittlung in die USA ist möglich. GitHub ist unter dem EU-US Data Privacy Framework zertifiziert. Weitere Informationen findest du in der <a href="https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement" rel="noopener noreferrer">Datenschutzerklärung von GitHub</a>.</p>
<h2>Cookies, Tracking und externe Inhalte</h2>
<p>Diese Website setzt keine Cookies, verwendet keine Analyse- oder Tracking-Dienste und lädt keine Schriftarten, Skripte oder sonstigen Inhalte von Drittanbietern nach. Die Suche läuft vollständig in deinem Browser.</p>
<h2>Externe Links</h2>
<p>Diese Website verlinkt auf externe Angebote, etwa <a href="${GITHUB}" rel="noopener noreferrer">GitHub</a>, <a href="https://packagist.org/packages/openforgeproject/mageforge" rel="noopener noreferrer">Packagist</a> und den <a href="https://mageforge.myspreadshop.de/" rel="noopener noreferrer">Merch-Shop</a>. Erst wenn du einem Link folgst, werden Daten an den jeweiligen Anbieter übermittelt. Für die dortige Datenverarbeitung gelten deren Datenschutzerklärungen.</p>
<h2>Kontaktaufnahme</h2>
<p>Wenn du uns per E-Mail kontaktierst, speichern wir die von dir übermittelten Daten nur so lange, wie es zur Bearbeitung deiner Anfrage erforderlich ist. Eine Weitergabe an Dritte erfolgt nicht. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (vorvertragliche Maßnahmen) bzw. Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der Beantwortung von Anfragen).</p>
<h2>Deine Rechte</h2>
<p>Dir stehen grundsätzlich folgende Rechte zu:</p>
<ul>
<li>Recht auf Auskunft (Art. 15 DSGVO)</li>
<li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
<li>Recht auf Löschung (Art. 17 DSGVO)</li>
<li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
<li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
<li>Recht auf Widerspruch (Art. 21 DSGVO)</li>
</ul>
<p>Zur Wahrnehmung deiner Rechte kannst du dich jederzeit per E-Mail an <a href="mailto:hello@dermatz.de">hello@dermatz.de</a> an uns wenden.</p>
<h2>Beschwerderecht</h2>
<p>Du hast das Recht, bei der zuständigen Datenschutzaufsichtsbehörde Beschwerde einzulegen:</p>
<p><strong>Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Baden-Württemberg</strong><br>Königstraße 10a<br>70173 Stuttgart<br><a href="https://www.baden-wuerttemberg.datenschutz.de" rel="noopener noreferrer">https://www.baden-wuerttemberg.datenschutz.de</a></p>`,
  },
];
for (const p of legalPages) {
  addPage({
    path: p.path,
    title: p.title,
    description: p.description,
    lang: "de",
    body: `
<div class="page-head"><div class="container">${breadcrumbs([
      ["Home", "/"],
      [p.title, p.path],
    ])}<h1>${p.title}</h1></div></div>
<div class="container layout layout-single">
  <article class="prose">${p.html}</article>
</div>`,
  });
}

out(
  "404.html",
  layout({
    title: "Page not found",
    path: "/404.html",
    noindex: true,
    body: `<div class="container not-found"><h1>404 – page not found</h1><p class="lead">This page has been smelted away.</p><p><a class="btn btn-primary" href="${url("/")}">Back to the forge</a> <a class="btn btn-secondary" href="${url("/commands/")}">Browse commands</a></p></div>`,
  }),
);

/* ---------------------------------------------------------------- ASSETS */
const assetDir = join(ROOT, ".github/assets");
await rm(DIST, { recursive: true, force: true });
await mkdir(join(DIST, "assets/img"), { recursive: true });
await mkdir(join(DIST, "assets/og"), { recursive: true });

for (const f of [
  "MageForge-Logo.svg",
  "MageForge-Slogan.svg",
  "MageForge-Slogan-dark.svg",
  "MageForge-Header.png",
  "MageForge-cli-chooser.png",
  "MageForge-Toolbar.jpeg",
  "MageForge-Merch.jpg",
]) {
  await cp(join(assetDir, f), join(DIST, "assets/img", f));
}
await cp(
  join(HERE, "src/assets/hero-pixel.svg"),
  join(DIST, "assets/img/hero-pixel.svg"),
);
await cp(join(HERE, "src/styles.css"), join(DIST, "assets/styles.css"));
await cp(join(HERE, "src/app.js"), join(DIST, "assets/app.js"));

out(
  "favicon.svg",
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 352 399"><path fill="#e5622a" fill-rule="evenodd" d="${LOGO}"/></svg>`,
);
out("apple-touch-icon.png", iconPng(180));
out("icon-192.png", iconPng(192));
out("icon-512.png", iconPng(512));
out(
  "manifest.webmanifest",
  JSON.stringify({
    name: SITE_NAME,
    short_name: SITE_NAME,
    description: DEFAULT_DESC,
    start_url: url("/"),
    scope: url("/"),
    display: "browser",
    background_color: "#0d1117",
    theme_color: "#0d1117",
    icons: [
      { src: url("/icon-192.png"), sizes: "192x192", type: "image/png" },
      { src: url("/icon-512.png"), sizes: "512x512", type: "image/png" },
    ],
  }),
);
out(
  "robots.txt",
  `User-agent: *\nAllow: /\n\nSitemap: ${abs("/sitemap.xml")}\n`,
);
out(
  "sitemap.xml",
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${pages.map((p) => `<url><loc>${abs(p.path)}</loc>${p.lastmod ? `<lastmod>${p.lastmod}</lastmod>` : ""}</url>`).join("\n")}\n</urlset>\n`,
);
out("search-index.json", JSON.stringify(searchIndex));
out(
  "releases.json",
  JSON.stringify(
    releases.map((r) => ({
      version: r.version,
      date: r.date,
      url: r.url,
      newCommands: r.newCommands.map((c) => c.name),
      added: r.groups.added.filter((i) => !i.noise).map((i) => i.text),
      fixed: r.groups.fixed.map((i) => i.text),
    })),
  ),
);

for (const [name, opts] of ogJobs)
  out(`assets/og/${name}.png`, renderPng(ogSvg(opts)));

for (const [path, content] of files) {
  const target = join(DIST, path);
  await mkdir(join(target, ".."), { recursive: true });
  await writeFile(target, content);
}
console.log(
  `✓ built ${pages.length + 2} pages, ${ogJobs.length} social images, ${searchIndex.length} search entries → website/dist`,
);
