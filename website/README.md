# MageForge website

Dark-only static site for MageForge: landing page with search, docs, one page per command (with share links) and a changelog built from GitHub Releases.

## Build

```bash
cd website
npm ci
SITE_URL=https://mageforge.example.com npm run build   # writes dist/
npm run dev                                            # build and serve on :4173
```

| Variable       | Purpose                                                        |
| -------------- | -------------------------------------------------------------- |
| `SITE_URL`     | Public origin for canonical URLs, Open Graph, sitemap and feed |
| `BASE_PATH`    | Optional sub-path, e.g. `/mageforge`                           |
| `GITHUB_TOKEN` | Optional, avoids GitHub API rate limits                        |

## Content sources

- Docs and commands: `../docs/*.md` and `../README.md`.
- Changelog: GitHub Releases API, cached in `.cache/`. If the API is unreachable, the cache and then `../CHANGELOG.md` are used.
- Command "since" versions: `data/commands-meta.json`. Set `since` when a release adds a command.

The hero is generated with `npm run pixel` (output: `src/assets/hero-pixel.svg`).

## Deploy

```bash
SITE_URL=https://mageforge.example.com \
DEPLOY_TARGET=user@server:/var/www/mageforge \
./deploy.sh            # DRY_RUN=1 to preview
```

The script builds and rsyncs `dist/`. Run it from cron or a systemd timer (for example hourly) so new releases appear automatically. See `nginx.example.conf` for a server configuration with a strict CSP; the site uses no inline scripts.
