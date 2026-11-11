# MageForge Commands Reference

Complete reference of all CLI commands provided by the MageForge module.

## Quick Overview

| Group            | Command                              | Description                                         | Aliases               |
| ---------------- | ------------------------------------ | --------------------------------------------------- | --------------------- |
| **Theme**        | `mageforge:theme:list`               | List all available Magento themes                   | `frontend:list`       |
| **Theme**        | `mageforge:theme:info`               | Show theme details (package, builder, overrides, …)     | `frontend:info`, `m:t:i` |
| **Theme**        | `mageforge:theme:build`              | Build selected themes (CSS/TailwindCSS)             | `frontend:build`      |
| **Theme**        | `mageforge:theme:watch`              | Watch theme files and auto-rebuild                  | `frontend:watch`      |
| **Theme**        | `mageforge:theme:clean`              | Clean static files and cache directories            | `frontend:clean`      |
| **Theme**        | `mageforge:theme:inspector`          | Manage Frontend Inspector (enable/disable/status)   | —                     |
| **Template**     | `mageforge:template:override`        | Copy a module template into a theme (override)      | `template:override`   |
| **Template**     | `mageforge:template:obsolete`        | Find theme overrides that are no longer needed      | `template:obsolete`   |
| **Dependencies** | `mageforge:dependencies:update`      | Update the Node.js dependencies of themes           | `dependencies:update` |
| **Hyvä**         | `mageforge:hyva:tokens`              | Generate Hyvä design tokens                         | `hyva:tokens`         |
| **Hyvä**         | `mageforge:hyva:compatibility:check` | Check Hyvä and Tailwind CSS v4 compatibility        | `hyva:check`          |
| **System**       | `mageforge:system:version`           | Show current and latest module version              | `system:version`      |
| **System**       | `mageforge:system:check`             | Display system information (PHP, Node.js, DB, etc.) | `system:check`        |

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

### `mageforge:theme:info`

Shows details about a theme: area, path, Composer package and version, parent chain, detected builder, Node.js version, Tailwind CSS version (required vs. installed), `node_modules` status, override counts (templates, layouts, requirejs-config, web assets), deployed static locales and, for Hyvä themes, the Hyvä version, Tailwind config file and extensions from `hyva-themes.json`. Without an argument, a select menu is shown.

```bash
bin/magento mageforge:theme:info [<theme-code>]
bin/magento mageforge:theme:info Magento/luma --json
```

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

## Template Commands

### `mageforge:template:override`

Copies a module view file into a theme as an override, following Magento's view file fallback
logic. The command resolves both the correct source file and the correct target directory for
you — including the tricky cases where Hyvä compatibility modules ship the template that is
actually rendered.

```bash
bin/magento mageforge:template:override <template> --theme <theme-code>
bin/magento mageforge:template:override 'Magento_Catalog::product/view/details.phtml' --theme Vendor/theme
bin/magento mageforge:template:override vendor/magento/module-catalog/view/frontend/templates/product/view/details.phtml -t Vendor/theme
bin/magento mageforge:template:override 'Magento_Sales::order/new.html' --theme Vendor/theme
bin/magento mageforge:template:override 'Magento_Theme::css/source/_module.less' --theme Vendor/theme
bin/magento mageforge:template:override vendor/magento/module-catalog/view/frontend/templates --theme Vendor/theme
```

**Arguments:**

- `template` — The view file or directory to override. Accepts the `Module_Name::path/to/file.phtml`
  notation or a file path (absolute, or relative to the Magento root / current directory).
  File paths may point into a module's `view/<area>/templates`, `view/<area>/email` or
  `view/<area>/web` directory, a Hyvä compat module, or another theme's override directory.
  Email templates (`.html`) and static view files (CSS, LESS, JS, images, fonts, ...) are
  detected automatically and copied to `<theme>/<Module_Name>/email/` or
  `<theme>/<Module_Name>/web/` respectively.
  A directory path (e.g. `vendor/magento/module-catalog/view/frontend/templates`) copies
  all files inside it, including subdirectories.

**Options:**

- `-t, --theme=VALUE` — Target theme code (format: `Vendor/theme`). If omitted, an interactive prompt appears.
- `--dry-run` — Show source, target, and the full fallback search order without copying.
- `-f, --force` — Replace an existing override with the next file in the fallback chain
  (useful to reset an override to the original template).

**Behavior:**

- Uses Magento's own fallback rule (`RulePool`) with the frontend area DI configuration
  loaded, so plugins like Hyvä's compat module fallback are honored.
- Hyvä compat module templates are copied from the compat module (the file actually
  rendered), but the override is placed under the **original** module's directory name,
  e.g. `<theme>/Mollie_Payment/templates/...` — exactly where Magento looks for it.
- Email templates are handled with the same fallback logic and placed under
  `<theme>/<Module_Name>/email/`.
- Static view files (CSS, LESS, JS, images, fonts, ...) use Magento's static file fallback
  and are placed under `<theme>/<Module_Name>/web/`.
- After copying, the command re-resolves the file to verify the new override wins and cleans
  the relevant caches (`full_page`, `block_html`, `layout`, `translate`).
- If the file is already overridden in the target theme, nothing is copied unless `--force`
  is given. When copying a directory, already overridden files are skipped and the rest is
  copied; with `--force` they are replaced as well.

---

### `mageforge:template:obsolete`

Finds theme overrides (`<theme>/<Module_Name>/{templates,email,web}`) that are no longer needed.
In a terminal it shows the same searchable theme selection as `mageforge:theme:build`, then an
options menu (Space to toggle) for the slower `--deep` check and for deleting the overrides that are
safe to remove (deleting still asks for confirmation). Options already given as flags are not offered. Without a terminal (CI, pipes) or with `--format=json` all frontend themes outside `vendor/`
are checked and nothing is deleted unless `--delete` is given.

```bash
bin/magento mageforge:template:obsolete
bin/magento mageforge:template:obsolete Vendor/theme --deep
bin/magento mageforge:template:obsolete --delete
bin/magento mageforge:template:obsolete --format=json --fail-on-found
```

**Arguments:**

- `themeCodes` — Themes to check (format: `Vendor/theme`), also vendor themes. Optional.

**Options:**

- `--deep` — Also list templates without a found reference (slower, heuristic, may include templates that are still used).
- `--delete` — Delete the overrides marked as safe (asks for confirmation) and clean the caches.
- `-y, --yes` — Delete without confirmation.
- `--format=VALUE` — `table` (default) or `json`.
- `--fail-on-found` — Exit with a failure code if obsolete overrides exist (for CI).

**Findings:**

| Reason          | Meaning                                                                           | Safe to delete                |
| --------------- | --------------------------------------------------------------------------------- | ----------------------------- |
| Identical       | Same content as the file Magento would use without the override                   | Yes                           |
| Orphaned        | The module is not installed/enabled, or the recorded source file no longer exists | Only if the module is missing |
| Possibly unused | `--deep`: no reference to the `.phtml` template was found (hint only, see notes)  | No                            |
| Outdated        | Copied from an older module version (taken from the MageForge header)             | No                            |

**Notes:**

- The comparison ignores the MageForge source header and line endings, and uses Magento's
  fallback rule, so parent themes and Hyvä compat modules are taken into account.
- Theme layout files extend the module layout and are only checked for missing modules.
- `Possibly unused` is only a hint and often a false positive. Templates can be used through
  `widget.xml`, `di.xml`, vendor layouts, the database (e.g. CMS blocks) or dynamically built
  paths, none of which are reliably detected. Never delete them without checking; `--delete`
  never touches them.

---

## Dependencies Commands

### `mageforge:dependencies:update`

Updates the Node.js dependencies (npm packages) of one or more themes. Works with all themes that ship their own `package.json`, e.g. Hyvä and TailwindCSS themes (`web/tailwind/package.json`). For standard Magento themes (e.g. `Magento/luma`) the Node.js setup in the Magento root is updated instead, since that is what builds their assets.

```bash
bin/magento mageforge:dependencies:update <theme-code> [<theme-code> ...]
bin/magento mageforge:dependencies:update Vendor/theme
bin/magento mageforge:dependencies:update Vendor/theme --dry-run
bin/magento mageforge:dependencies:update Vendor/theme --latest
```

**Arguments:**

- `themeCodes` — One or more theme codes in format `Vendor/theme`. Accepts wildcards like `Vendor/*`. If omitted, an interactive prompt lets you select themes.

**Options:**

- `--dry-run` — Only show the outdated packages without changing anything.
- `-l, --latest` — Update packages beyond their semver ranges to the latest available versions. This rewrites `package.json` and may pull in breaking changes.

**Behavior:**

- Shows a table of all outdated packages (current, wanted, latest version and dependency type) per theme.
- By default runs a safe `npm update`: packages are only updated within the semver ranges defined in `package.json` (`package.json` itself stays untouched).
- With `--latest`, outdated packages are updated to their latest released versions, grouped by dependency type (`dependencies`, `devDependencies`, ...).
- Installs `node_modules` first if missing, so the report is meaningful.
- Themes installed in `vendor/` (managed by Composer) are skipped with an explanation.
- Standard Magento themes (Luma-based, no own `package.json`) fall back to the `package.json` in the Magento root (copied from `package.json.sample`), which powers their Grunt build. The root setup is shared, so it is updated at most once per run; if the Magento root has no `package.json`, the theme is skipped with a setup hint. Note that with `--latest` this major-bumps Grunt tooling used by **all** standard themes.
- Other themes without their own `package.json` are skipped.
- After a successful update, a hint reminds you to rebuild the affected themes with `mageforge:theme:build`.

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
- `--include-core` — Include Magento core modules in the check.
- `--include-vendor` — Include modules installed in the vendor directory (default: excluded).
- `--detailed` — Show detailed compatibility information.
- `--tailwind4` — Force the Tailwind CSS v4 readiness check. It runs automatically when Hyvä is installed via Composer. See below.

**Output:** Displays a table with compatibility status per module.

#### Tailwind CSS v4 readiness

Read-only check of what is needed for Tailwind CSS v4 (Hyvä 1.4). See the [Hyvä upgrade guide](https://docs.hyva.io/hyva-themes/working-with-tailwindcss/updating-to-tailwind-4.html). It runs automatically when Hyvä is installed via Composer (version of `hyva-themes/magento2-default-theme`, with the theme module as fallback). On Hyvä 1.3.x it works as a checklist for the upgrade to 1.4, so errors are expected until the theme is migrated. Use `--tailwind4` to run it for installations Composer does not know about (e.g. Hyvä in `app/code`). The same module filters apply (`--include-core`, `--include-vendor`); vendor themes such as `Hyva/default` are skipped unless `--include-vendor` is set.

```bash
bin/magento hyva:check --tailwind4
```

- **Themes** (Hyvä themes, `web/tailwind`): Node.js version (20+), `tailwindcss` version (declared and installed), removed packages (PostCSS, `@tailwindcss/forms`, `@tailwindcss/typography`), required packages (`@tailwindcss/cli`, `@hyva-themes/hyva-modules`), npm scripts, obsolete `postcss.config.js` / `tailwind.config.js`, `hyva.config.json`, the CSS folder structure and the `tailwind-source.css` entry point.
- **Themes and modules** (CSS files in `web/tailwind` and `view/frontend/tailwind`): syntax that changed in v4 (`@tailwind`, `@screen`, `theme()` with dotted paths, non-relative `@import` paths).

Errors make the command fail; warnings do not.

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
mageforge:theme:info          → Show theme details
mageforge:theme:build         → Build theme assets
mageforge:theme:watch         → Watch & auto-rebuild
mageforge:theme:clean         → Clean static files
mageforge:theme:inspector     → Manage inspector tool
mageforge:template:override   → Copy module template into a theme
mageforge:template:obsolete   → Find unneeded theme overrides
mageforge:dependencies:update → Update theme Node.js dependencies
mageforge:hyva:tokens         → Generate Hyvä design tokens
mageforge:hyva:compatibility:check → Check Hyvä compatibility
mageforge:system:version      → Show module version
mageforge:system:check        → System diagnostics
```
