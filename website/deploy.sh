#!/usr/bin/env bash
# Builds the MageForge website and syncs it to your server via rsync over SSH.
#
#   SITE_URL=https://mageforge.example.com \
#   DEPLOY_TARGET=user@server:/var/www/mageforge \
#   ./deploy.sh
#
# Settings can also live in website/.env.deploy (git-ignored), one KEY=value per line.
#
# Optional:
#   BASE_PATH=/mageforge     serve the site from a sub-path instead of the domain root
#   GITHUB_TOKEN=...         avoids GitHub API rate limits when fetching releases
#   DEPLOY_SSH_OPTS="-i ~/.ssh/key -p 2222"
#   DRY_RUN=1                build and show what rsync would change, upload nothing
set -euo pipefail

cd "$(dirname "$0")"

if [[ -f .env.deploy ]]; then
	set -a
	# shellcheck disable=SC1091
	source .env.deploy
	set +a
fi

: "${SITE_URL:?Set SITE_URL, e.g. https://mageforge.example.com}"
: "${DEPLOY_TARGET:?Set DEPLOY_TARGET, e.g. user@server:/var/www/mageforge}"
export SITE_URL

command -v node >/dev/null || {
	echo "Node.js 20+ is required" >&2
	exit 1
}
command -v rsync >/dev/null || {
	echo "rsync is required" >&2
	exit 1
}

echo "→ Installing dependencies"
npm ci --no-audit --no-fund

echo "→ Building (releases are fetched from GitHub)"
npm run build

[[ -f dist/index.html ]] || {
	echo "Build produced no dist/index.html – aborting" >&2
	exit 1
}

rsync_args=(-az --delete --delay-updates --human-readable --stats)
[[ ${DRY_RUN:-0} == "1" ]] && rsync_args+=(--dry-run --itemize-changes)

echo "→ Syncing dist/ to ${DEPLOY_TARGET}"
# shellcheck disable=SC2086
rsync "${rsync_args[@]}" -e "ssh ${DEPLOY_SSH_OPTS:-}" dist/ "${DEPLOY_TARGET%/}/"

echo "✓ Done: ${SITE_URL}${BASE_PATH:-}/"
