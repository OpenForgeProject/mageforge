# MageForge Commands Reference

Complete reference of all CLI commands provided by the MageForge module.

## Quick Overview

| Group      | Command                              | Description                                         | Aliases          |
| ---------- | ------------------------------------ | --------------------------------------------------- | ---------------- |
| **Theme**  | `mageforge:theme:list`               | List all available Magento themes                   | `frontend:list`  |
| **Theme**  | `mageforge:theme:build`              | Build selected themes (CSS/TailwindCSS)             | `frontend:build` |
| **Theme**  | `mageforge:theme:watch`              | Watch theme files and auto-rebuild                  | `frontend:watch` |
| **Theme**  | `mageforge:theme:clean`              | Clean static files and cache directories            | `frontend:clean` |
| **Theme**  | `mageforge:theme:inspector`          | Manage Frontend Inspector (enable/disable/status)   | —                |
| **Hyvä**   | `mageforge:hyva:tokens`              | Generate Hyvä design tokens                         | `hyva:tokens`    |
| **Hyvä**   | `mageforge:hyva:compatibility:check` | Check modules for Hyvä compatibility issues         | `hyva:check`     |
| **System** | `mageforge:system:version`           | Show current and latest module version              | `system:version` |
| **System** | `mageforge:system:check`             | Display system information (PHP, Node.js, DB, etc.) | `system:check`   |

---

## Theme Commands

### `mageforge:theme:list`

Lists all available Magento themes in the installation.

```bash
bin/magento mageforge:theme:list
# or alias
bin/magento frontend:list
```

**Output:** Displays a table with Code, Title, and Path for each theme.

---

### `mageforge:theme:build`

Builds selected themes by compiling assets (CSS/TailwindCSS) and deploying static content.

```bash
bin/magento mageforge:theme:build <theme-code> [<theme-code> ...]
bin/magento frontend:build Magento/luma Magento/blank
```

**Arguments:**

- `themeCodes` — One or more theme codes in format `Vendor/theme`. Accepts wildcards like `Magento/*`.

**Behavior:**

- If no theme codes are provided, an interactive prompt lets you select themes.
- For each theme, the appropriate builder is determined automatically (Hyvä, TailwindCSS, Magento Standard, etc.).
- Displays a summary of built themes and execution time.

---

### `mageforge:theme:watch`

Watches theme files for changes and automatically rebuilds when modifications are detected.

```bash
bin/magento mageforge:theme:watch <theme-code>
bin/magento frontend:watch Magento/luma
```

**Arguments:**

- `themeCode` — Optional. Theme to watch in format `Vendor/theme`. If omitted, an interactive prompt appears.

**Options:**

- `-t, --theme=VALUE` — Alternative way to specify the theme code.

**Behavior:**

- Runs indefinitely until interrupted (Ctrl+C).
- Monitors source files (SCSS, JS, etc.) and triggers rebuilds on change.
- Useful for active theme development.

---

### `mageforge:theme:clean`

Cleans theme static files and cache directories.

```bash
bin/magento mageforge:theme:clean [<theme-code> ...]
bin/magento mageforge:theme:clean --all
bin/magento mageforge:theme:clean --dry-run
```

**Arguments:**

- `themeCodes` — Optional. One or more theme codes to clean.

**Options:**

- `-a, --all` — Clean all themes.
- `--dry-run` — Show what would be cleaned without actually deleting anything.

---

### `mageforge:theme:inspector`

Manage the MageForge Frontend Inspector (developer tool for inspecting blocks, templates, and performance metrics).

```bash
bin/magento mageforge:theme:inspector enable
bin/magento mageforge:theme:inspector disable
bin/magento mageforge:theme:inspector status
```

**Arguments:**

- `action` — Required. One of: `enable`, `disable`, `status`.

**Notes:**

- Requires Magento Developer Mode for enabling.
- Can also be toggled via Admin: `Stores > Configuration > MageForge > Frontend Inspector`.
- Browser shortcut: `Ctrl+Shift+I` (Windows/Linux) or `Cmd+Option+I` (macOS).
- Not compatible with Magewire components (automatically excluded).

---

## Hyvä Commands

### `mageforge:hyva:tokens`

Generate Hyvä design tokens from `design.tokens.json` or `hyva.config.json`.

```bash
bin/magento mageforge:hyva:tokens [<theme-code>]
bin/magento hyva:tokens Hyva/default
```

**Arguments:**

- `themeCode` — Optional. Theme code in format `Vendor/theme`. If omitted, an interactive prompt appears.

**Output:** Creates a `generated/hyva-tokens.css` file from the design tokens configuration.

---

### `mageforge:hyva:compatibility:check`

Scans Magento modules for Hyvä theme compatibility issues (RequireJS, Knockout.js, jQuery, UI Components).

```bash
bin/magento mageforge:hyva:compatibility:check
bin/magento hyva:check
```

**Options:**

- `-a, --show-all` — Show all modules including compatible ones.
- `-t, --third-party-only` — Check only third-party modules (exclude Magento\_\*).
- `--include-vendor` — Include Magento core modules in the check.
- `--detailed` — Show detailed compatibility information.

**Output:** Displays a table with compatibility status per module.

---

## System Commands

### `mageforge:system:version`

Displays the current installed MageForge version and checks for the latest release.

```bash
bin/magento mageforge:system:version
bin/magento system:version
```

**Output:** Shows Module Version and Latest Version (fetched from GitHub API).

---

### `mageforge:system:check`

Displays comprehensive system information for troubleshooting and setup validation.

```bash
bin/magento mageforge:system:check
bin/magento system:check
```

**Reports:**

- PHP version and extensions
- Magento version
- Database type and version (MySQL/MariaDB)
- Node.js version (with LTS reference)
- Composer and npm versions
- Git version
- Xdebug status
- Redis status
- Search engine status (Elasticsearch/OpenSearch)
- Disk space

---

## Command Groups Summary

```text
mageforge:theme:list          → List available themes
mageforge:theme:build         → Build theme assets
mageforge:theme:watch         → Watch & auto-rebuild
mageforge:theme:clean         → Clean static files
mageforge:theme:inspector     → Manage inspector tool
mageforge:hyva:tokens         → Generate Hyvä design tokens
mageforge:hyva:compatibility:check → Check Hyvä compatibility
mageforge:system:version      → Show module version
mageforge:system:check        → System diagnostics
```
