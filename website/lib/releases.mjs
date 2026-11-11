import { readFile, writeFile, mkdir } from "node:fs/promises";
import { dirname } from "node:path";

const REPO = "OpenForgeProject/mageforge";
const API = `https://api.github.com/repos/${REPO}/releases?per_page=100`;

async function fetchReleases(cacheFile) {
  const headers = {
    Accept: "application/vnd.github+json",
    "User-Agent": "mageforge-website",
  };
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN;
  if (token) headers.Authorization = `Bearer ${token}`;

  const all = [];
  for (let page = 1; page <= 10; page++) {
    const res = await fetch(`${API}&page=${page}`, {
      headers,
      signal: AbortSignal.timeout(20000),
    });
    if (!res.ok) throw new Error(`GitHub API responded with ${res.status}`);
    const chunk = await res.json();
    all.push(...chunk);
    if (chunk.length < 100) break;
  }
  await mkdir(dirname(cacheFile), { recursive: true });
  await writeFile(cacheFile, JSON.stringify(all));
  return all;
}

// Release Please writes "### Added" style sections, older releases use
// conventional prefixes ("feat: ...") or GitHub's generated "by @user in URL" lines.
function classify(heading, text) {
  const h = (heading || "").toLowerCase();
  if (/^(added|features?|new)/.test(h)) return "added";
  if (/^(fixed|bug ?fixes|fixes)/.test(h)) return "fixed";
  if (h) return "other";
  if (/^feat(\(|:)/i.test(text)) return "added";
  if (/^fix(\(|:)/i.test(text)) return "fixed";
  return "other";
}

function cleanItem(raw) {
  let text = raw.replace(/^[*-]\s+/, "").trim();
  let pr = null;
  let sha = null;

  text = text.replace(
    /\s*\(\[([0-9a-f]{7,40})\]\(([^)]+)\)\)\s*$/i,
    (_, s, u) => {
      sha = { short: s, url: u };
      return "";
    },
  );
  text = text.replace(/\s*\(\[#(\d+)\]\(([^)]+)\)\)/, (_, n, u) => {
    pr = { number: n, url: u };
    return "";
  });
  text = text.replace(
    /\s+by @[\w-]+ in (https:\/\/github\.com\/\S+\/pull\/(\d+))/,
    (_, u, n) => {
      pr = { number: n, url: u };
      return "";
    },
  );
  text = text.replace(/\[([^\]]+)\]\([^)]+\)/g, "$1");

  let scope = null;
  const scoped = text.match(/^\*\*([\w-]+):\*\*\s*/);
  if (scoped) {
    scope = scoped[1];
    text = text.slice(scoped[0].length);
  }
  const conv = text.match(
    /^(feat|fix|docs|refactor|chore|removed|perf|style|test|build|ci)(?:\(([\w-]+)\))?:\s*/i,
  );
  if (conv) {
    scope = scope || conv[2] || null;
    text = text.slice(conv[0].length);
  }
  text = text.replace(/\s+/g, " ").trim();
  return { text: text.charAt(0).toUpperCase() + text.slice(1), scope, pr, sha };
}

export function parseBody(body) {
  const groups = { added: [], fixed: [], other: [] };
  let heading = "";
  for (const line of (body || "").split(/\r?\n/)) {
    const h = line.match(/^#{3,4}\s+(.+)/);
    if (h) {
      heading = h[1].trim();
      continue;
    }
    if (!/^\s*[*-]\s+\S/.test(line)) continue;
    const raw = line.replace(/^\s*/, "");
    const item = cleanItem(raw);
    if (!item.text) continue;
    const kind = classify(heading, raw.replace(/^[*-]\s+/, ""));
    item.noise =
      /^deps(-dev)?$/.test(item.scope || "") ||
      /^(bump|update) .*(requirement|group|from)/i.test(item.text);
    groups[kind].push(item);
  }
  return groups;
}

function fromChangelog(md) {
  const out = [];
  const parts = md.split(/^## \[/m).slice(1);
  for (const part of parts) {
    const head = part.match(/^([\d.]+)\]\(([^)]*)\)\s*\((\d{4}-\d{2}-\d{2})\)/);
    if (!head) continue;
    out.push({
      tag_name: head[1],
      html_url: `https://github.com/${REPO}/releases/tag/${head[1]}`,
      published_at: `${head[3]}T00:00:00Z`,
      body: part.slice(part.indexOf("\n") + 1),
      draft: false,
      prerelease: false,
    });
  }
  return out;
}

export async function loadReleases({ cacheFile, changelogFile }) {
  let raw;
  let source = "github";
  try {
    raw = await fetchReleases(cacheFile);
  } catch (error) {
    console.warn(
      `! GitHub releases unavailable (${error.message}); trying cache`,
    );
    try {
      raw = JSON.parse(await readFile(cacheFile, "utf8"));
      source = "cache";
    } catch {
      raw = fromChangelog(await readFile(changelogFile, "utf8"));
      source = "CHANGELOG.md";
    }
  }

  const releases = raw
    .filter((r) => !r.draft && !r.prerelease)
    .map((r) => ({
      version: r.tag_name.replace(/^v/, ""),
      tag: r.tag_name,
      url: r.html_url,
      date: (r.published_at || r.created_at).slice(0, 10),
      groups: parseBody(r.body),
    }))
    .sort((a, b) => compareVersions(b.version, a.version));

  console.log(`✓ ${releases.length} releases (${source})`);
  return releases;
}

export function compareVersions(a, b) {
  const pa = a.split(".").map(Number);
  const pb = b.split(".").map(Number);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const d = (pa[i] || 0) - (pb[i] || 0);
    if (d) return d;
  }
  return 0;
}

// Conventional commits on main that are not in a release yet.
export async function loadUpcoming({ cacheFile, since }) {
  if (!since) return [];
  const headers = {
    Accept: "application/vnd.github+json",
    "User-Agent": "mageforge-website",
  };
  const token = process.env.GITHUB_TOKEN || process.env.GH_TOKEN;
  if (token) headers.Authorization = `Bearer ${token}`;

  let commits;
  try {
    let res;
    for (const tag of [since, `v${since}`]) {
      res = await fetch(
        `https://api.github.com/repos/${REPO}/compare/${tag}...main`,
        { headers, signal: AbortSignal.timeout(20000) },
      );
      if (res.ok) break;
    }
    if (!res.ok) throw new Error(`GitHub API responded with ${res.status}`);
    commits = (await res.json()).commits.map(
      (c) => c.commit.message.split("\n")[0],
    );
    await mkdir(dirname(cacheFile), { recursive: true });
    await writeFile(cacheFile, JSON.stringify(commits));
  } catch (err) {
    console.warn(`! Upcoming features: ${err.message}; using cache if present`);
    try {
      commits = JSON.parse(await readFile(cacheFile, "utf8"));
    } catch {
      return [];
    }
  }

  const seen = new Set();
  const items = [];
  for (const line of commits) {
    const m = line
      .replace(/^[^\p{L}\p{N}]+/u, "")
      .match(
        /^(feat|fix|perf)(?:\(([\w-]+)\))?!?:\s*(.+?)(?:\s*\(#(\d+)\))?$/i,
      );
    if (!m) continue;
    const [, type, scope, text, pr] = m;
    const key = `${type}:${scope}:${text}`.toLowerCase();
    if (seen.has(key) || ["deps", "deps-dev", "ci", "test"].includes(scope))
      continue;
    seen.add(key);
    items.push({
      type: type.toLowerCase(),
      scope: scope || null,
      text: text[0].toUpperCase() + text.slice(1),
      pr: pr || null,
    });
  }
  return items;
}
