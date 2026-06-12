# MageForge Development Environment

Welcome to the MageForge development repository. This guide covers everything you need to set up a local development environment and contribute to the module.

## Table of Contents

- [Repository Structure](#repository-structure)
- [Prerequisites](#prerequisites)
- [Initial Setup](#initial-setup)
- [Development Workflow](#development-workflow)
- [Code Quality](#code-quality)
- [Common Issues](#common-issues)
- [Building a Release](#building-a-release)

---

## Repository Structure

```
/mageforge
├── /src/                   # ⭐ The MageForge module code
│   ├── /Console/Command/   # CLI commands
│   ├── /Service/           # Business logic & theme builders
│   ├── /Model/             # Domain models
│   ├── /etc/               # Module configuration (di.xml, module.xml, …)
│   ├── /view/              # Frontend assets & templates
│   └── /i18n/              # Localisation files
│
├── /magento/               # Local Magento 2 installation (testing only)
│   ├── /app/design/        # Test themes
│   ├── /vendor/            # Magento & dependencies
│   └── /bin/magento        # Magento CLI
│
├── /.ddev/                 # DDEV configuration
│   └── /commands/web/      # Custom DDEV web-container commands
│
├── /docs/                  # Documentation (you are here)
├── CONTRIBUTING.md         # Contribution guidelines & commit conventions
└── README.md               # End-user documentation (install, features)
```

> **Key points**
>
> - All module development happens in `/src/` — this is where you write code.
> - Testing happens in `/magento/` — a full Magento 2 installation wired up for local use.
> - The repository is **mounted read-only by Docker** into `/magento/mageforge-source/` and installed into Magento as a regular Composer package (path repository with symlink). Changes in `/src/` are visible instantly; no Composer step needed. The mount is established on `ddev start` / `ddev restart`.
> - `/magento/` is never included in a release. It exists solely for local development.

---

## Prerequisites

| Tool | Notes |
| ---- | ----- |
| [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/) | v1.23 or higher recommended |
| Git | For cloning and branching |
| Docker | Required by DDEV |

Basic familiarity with Magento 2 module development is assumed.

---

## Initial Setup

1. **Clone the repository:**

   ```bash
   git clone git@github.com:OpenForgeProject/mageforge.git
   cd mageforge
   ```

2. **Start DDEV** (pulls containers, configures the environment):

   ```bash
   ddev start
   ```

3. **Install Magento 2:**

   ```bash
   ddev install-magento
   ```

   This script will:
   - Install a fresh Magento 2 instance inside `/magento/`
   - Install MageForge via Composer (path repository pointing at the mounted module source)
   - Install Magento sample data
   - Enable the MageForge module (`bin/magento module:enable`)
   - Set developer mode and disable 2FA

   > **Note:** `ddev start` must run before this command to establish the Docker mount.

4. **Verify the installation:**

   ```bash
   ddev magento mageforge:system:check
   ```

You now have a fully functional local development environment.

---

## How the Module Is Installed

The repository root is bind-mounted **read-only** by Docker (`.ddev/docker-compose.mageforge-source.yaml`) into the Magento root:

```
..  →  magento/mageforge-source/   (read-only)
```

`ddev install-magento` registers this directory as a Composer **path repository** and installs the module with `composer require openforgeproject/mageforge:@dev`. Composer creates a symlink:

```
magento/vendor/openforgeproject/mageforge  →  ../../mageforge-source
```

Why this construction?

- **Magento's path validator** rejects paths that resolve (via `realpath()`) outside the Magento root. A plain Composer symlink to the repository root would fail; the mount point lives *inside* the Magento root, so validation passes.
- **Read-only protects the source**: an accidental `rm -rf magento` inside the container stops at the mount instead of deleting the repository through it.
- **Real Composer install**: third-party dependencies of the module (e.g. `laravel/prompts`) are resolved by Composer like for any end-user installation — no extra sync scripts needed.
- An anonymous volume shadows `mageforge-source/magento/` so the mount does not recurse into itself.

**The mount is (re-)established every time the containers start.** Always run `ddev start` or `ddev restart` when:

- setting up for the first time
- pulling config changes from the repository
- `/magento/` was deleted while DDEV was running (see [Common Issues](#common-issues))

**Changed module dependencies** (`composer.json` in the repository root) are picked up with:

```bash
ddev composer update openforgeproject/mageforge
```

---

## Development Workflow

### Making Changes

1. Edit code in `/src/` (commands, services, builders, etc.)
2. Apply changes to Magento:

   ```bash
   ddev magento setup:upgrade   # Register module updates
   ddev magento cache:clean     # Clear application cache
   ```

3. Test your changes:

   ```bash
   ddev magento mageforge:theme:list                # List all themes
   ddev magento mageforge:theme:build <theme>       # Build a theme
   ddev magento mageforge:theme:watch <theme>       # Watch mode (Ctrl+C to stop)
   ddev magento mageforge:system:check              # System diagnostics
   ```

### Useful DDEV Commands

```bash
ddev magento <command>    # Run any Magento CLI command inside the container
ddev ssh                  # Open a shell inside the container
ddev xdebug on/off        # Toggle Xdebug (VS Code tasks also available)
ddev logs                 # Tail container logs
ddev restart              # Restart all containers
```

### Running Tests Manually

```bash
# Theme detection
ddev magento mageforge:theme:list

# Standard Magento build
ddev magento mageforge:theme:build Magento/luma

# Hyvä theme build (requires Hyvä to be installed)
ddev magento mageforge:theme:build Hyva/default

# Watch mode
ddev magento mageforge:theme:watch Magento/luma

# System diagnostics
ddev magento mageforge:system:check
```

CI runs the following matrix against OpenSearch — test locally before opening a PR:

| Magento | PHP |
| ------- | --- |
| 2.4.7-p10 | 8.3 |
| 2.4.8-p5  | 8.4 |
| 2.4.9     | 8.5 |

---

## Code Quality

Run all checks before submitting a pull request:

```bash
# All linters (actionlint, hadolint, markdownlint, prettier, shellcheck, yamllint)
trunk check

# Auto-format
trunk fmt

# PHP CodeSniffer – Magento Coding Standard
ddev phpcs

# PHPStan – static analysis
ddev phpstan
```

### PHP Conventions

- `declare(strict_types=1)` in every PHP file
- Constructor property promotion with `readonly`
- Native type hints only — no FQNs in docblocks
- Named arguments when a function takes many parameters
- Enums over constants (PHP 8.3+)

For full details see [CONTRIBUTING.md](../CONTRIBUTING.md).

### Soft Dependencies (Hyvä Compatibility)

MageForge must work **with and without** Hyvä installed. Never add hard type-hints on optional third-party classes in constructors. Use `mixed` + `class_exists()` for optional dependencies and inline `@var` annotations for PHPStan.

---

## Common Issues

**Module not found after changes:**

```bash
ddev magento setup:upgrade && ddev magento cache:clean
```

**DDEV not starting:**

```bash
ddev poweroff
ddev start
```

**MageForge source mount not active / `mageforge-source` empty:**

This happens when `/magento/` was deleted while DDEV was still running. Docker holds the old inode; the new directory is not covered by the mount.

```bash
ddev restart            # Re-establishes the mount on the new inode
ddev install-magento    # Re-installs Magento with the mount now active
```

**Module dependencies missing after updating `composer.json`:**

```bash
ddev composer update openforgeproject/mageforge   # Re-resolves the module's dependencies
```

**Need to reinstall Magento from scratch:**

```bash
ddev restart            # Ensure the source mount is fresh first
ddev install-magento   # Handles cleanup automatically
```

**`detect()` returns wrong builder:**

Each builder's `detect()` method must uniquely identify its theme type. The `BuilderPool` picks the _first_ matching builder, so overlapping detection logic causes unpredictable results.

**Shell commands in builders:**

Always use the `Shell` service via dependency injection — never call `exec()` or `shell_exec()` directly.

---

## Building a Release

The module in `/src/` is what gets packaged and published to Packagist. End users install it via:

```bash
composer require openforgeproject/mageforge
```

### Automated Releases & Changelog Generation

Releases are fully automated via [Release Please](https://github.com/googleapis/release-please). The workflow works as follows:

1. **PR titles drive versioning**: When a PR is merged into `main` with a valid [Conventional Commits](https://www.conventionalcommits.org/) title, Release Please automatically determines the next version number:
   - `feat:` → **minor** bump (e.g., `0.3.0` → `0.4.0`)
   - `fix:`, `docs:`, `perf:` → **patch** bump (e.g., `0.3.0` → `0.3.1`)
   - `feat!:` or `fix!:` → **major** bump (e.g., `0.3.0` → `1.0.0`)

2. **Automatic changelog**: Release Please generates a changelog from all PR titles and descriptions since the last release. Contributors should write clear PR descriptions so they appear meaningfully in the release notes.

3. **Release PR**: Release Please opens an automated PR that updates `CHANGELOG.md`, `composer.json` version, and any other versioned files. Merging this PR triggers the actual release to Packagist and publishes a GitHub Release with the full changelog.

### Changelog Sections

Release Please groups changes into sections based on the Conventional Commits type in your PR title:

| Commit Type | Changelog Section | Visible in Notes |
|-------------|-------------------|------------------|
| `feat:` | **Added** | ✅ Yes |
| `fix:` | **Fixed** | ✅ Yes |
| `refactor:` | **Changed** | ✅ Yes |
| `perf:` | **Performance** | ✅ Yes |
| `docs:` | **Documentation** | ✅ Yes |
| `style:` | **Styling** | ✅ Yes |
| `chore:` | Chore | ❌ Hidden |
| `test:` | Tests | ❌ Hidden |
| `build:` | Build | ❌ Hidden |
| `ci:` | CI/CD | ❌ Hidden |

### Automatic PR Labeling

PRs are automatically labelled by the [Labeler](https://github.com/actions/labeler) workflow based on branch name, PR title, or changed files:

#### Branch / Title Based Labels

| Label | Trigger |
|-------|---------|
| **Documentation** | Any `*.md` file changed |
| **Feature** | Branch matches `add-*`, `feature-*`, `feat-*`; or PR title starts with `feat:`, `feature:` |
| **Fix** | Branch matches `fix-*`, `bugfix-*`; or PR title starts with `fix:`, `bugfix:` |
| **Next-Release** | Branch/PR title matches `chore: release*` or `release-please*` |

#### File-Based Labels

| Label | Trigger |
|-------|---------|
| **Command** | Files changed in `src/Console/Command/**` |
| **Frontend** | Files changed in `src/view/frontend/**` |
| **Theme-Builder** | Files changed in `src/Service/ThemeBuilder/**` |

> **Tip**: Name your branch according to the conventions above (`feat/my-feature`, `fix/issue-123`) and the correct labels are applied automatically.

> **Tip**: Use visible section types (`feat`, `fix`, `docs`, etc.) for changes you want in the release notes. Use hidden types (`chore`, `test`, `ci`) for internal work that doesn't affect end users.

> **Tip**: Check the Release Please PR before merging — it shows exactly what will appear in the release notes. You can edit the PR description to improve changelog entries if needed.

The `/magento/` directory is excluded from all release artefacts.
