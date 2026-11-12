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

### GitHub Pages (default)

`.github/workflows/website.yml` builds the site and publishes it with GitHub Pages. One-off setup: **Settings → Pages → Source: GitHub Actions**. The workflow takes the origin and base path from Pages, so project sites (`/mageforge`) and custom domains both work.

It runs on pushes to docs and website files, when a release is published, every six hours (so new releases show up even when the release event does not fire) and manually.

### Your own server

```bash
SITE_URL=https://mageforge.example.com \
DEPLOY_TARGET=user@server:/var/www/mageforge \
./deploy.sh            # DRY_RUN=1 to preview
```

The script builds and rsyncs `dist/`. See `nginx.example.conf` for a server configuration with a strict CSP; the site uses no inline scripts.
