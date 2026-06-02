# MageForge Copilot Instructions

## Structure

```
magento/              # Magento 2 installation (DDEV)
src/
  Console/Command     # CLI commands (extend AbstractCommand)
  Service             # Business logic & theme builders
  Model               # Domain models
  etc/di.xml          # DI configuration
docs/                 # commands.md, custom_theme_builders.md, advanced_usage.md
```

## DDEV

All Magento CLI commands run via DDEV from the `magento/` directory. Never run `bin/magento` directly.

```bash
ddev magento <command>
ddev install-magento        # Fresh install
ddev phpcs && ddev phpstan  # PHP quality
ddev xdebug on/off
```

After module changes: `ddev magento setup:upgrade && ddev magento cache:clean`

## PHP Conventions (PER-CS-2.0 + Magento 2)

Every PHP file:

```php
<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\...;
```

- Constructor property promotion with `readonly`
- No FQN in docblocks — native type hints only
- Named arguments for many parameters
- Enums over constants (PHP 8.3+)

### Soft Dependencies (Hyvä compatibility)

MageForge must work **with and without** Hyvä installed.

- No hard type-hints on optional module classes in constructors
- Use `mixed` + `class_exists()` for optional third-party classes
- Use inline `@var` for PHPStan type narrowing after `class_exists()` checks

## Builder Pattern

New builders: `src/Service/ThemeBuilder/<Type>/Builder.php` implementing `BuilderInterface`:

```php
public function detect(string $themePath): bool {}   // Must be unique per builder
public function build(...): bool {}
public function watch(...): bool {}
public function autoRepair(...): bool {}
public function getName(): string {}
```

Register in `src/etc/di.xml` under `BuilderPool`:

```xml
<item name="yourbuilder" xsi:type="object">OpenForgeProject\MageForge\Service\ThemeBuilder\YourType\Builder</item>
```

BuilderPool picks the first matching builder — `detect()` must be unique (e.g. check for `hyva-themes.json`).

## CLI Commands

Extend `AbstractCommand`, use `executeCommand()`. `$this->io` (SymfonyStyle) is pre-injected.

```php
$this->setName($this->getCommandName('theme', 'build'))
     ->setAliases(['m:t:b', 'frontend:build']);
// Return Cli::RETURN_SUCCESS or Cli::RETURN_FAILURE
```

**New command**: update `.github/workflows/magento-compatibility.yml` — add tests (use `--help` or `--dry-run`) to **both** `test-elasticsearch` and `test-opensearch` jobs.

## Admin Settings

When adding an admin config field, all steps must be done together:

1. `etc/adminhtml/system.xml` — `<field>` with `translate="label comment"`
2. `etc/config.xml` — default value
3. Config model — `XML_PATH_*` constant
4. Block — getter via `ScopeConfigInterface`
5. Template — pass as `data-*` attribute on Alpine element
6. JavaScript — `this.$el?.getAttribute('data-...')`
7. **`src/i18n/*.csv`** — add label **and** comment string to all locale files

## Frontend Inspector

- Frontend: `src/view/frontend/web/js/inspector.js`
- Backend: `OpenForgeProject\MageForge\Model\TemplateEngine\Decorator\InspectorHints`
- Toggle: `Ctrl+Shift+I` / `Cmd+Option+I`
- Commands: `ddev magento mageforge:theme:inspector enable|disable|status`

## MageForge Toolbar

Standalone Alpine.js component (`mageforgeToolbar`), separate from Inspector.

```
src/view/frontend/web/js/
  toolbar.js              # Entry point
  toolbar/
    ui.js                 # DOM construction
    menu.js               # toggleMenu/openMenu/closeMenu
    feedback.js           # showFeedback() toast
    audits.js             # runAudit() dispatcher
    audits/
      index.js            # Import & register all audits here
src/view/frontend/web/css/toolbar.css  # All styles via --mageforge-* CSS vars
```

Adding an audit: create `toolbar/audits/<key>.js` (export `{ key, icon, label, description, run(context) }`), import and add to array in `audits/index.js`. Menu builds automatically.

- Events: `mageforge:toolbar:toggle-inspector`, `mageforge:toolbar:inspector-state`
- Never use hardcoded `rgba()` — use `--mageforge-*` CSS variables

## Code Quality

```bash
ddev phpcs
ddev phpstan
trunk check    # actionlint, hadolint, markdownlint, prettier, shellcheck, yamllint
trunk fmt
```

Trunk also runs checkov (see `.trunk/trunk.yaml`).

## Git

```
fix/<issue-description>
feature/<issue-description>
#<issue-nr> - <message>
```

## Testing

Manual after every change:

1. `ddev magento m:t:l` — theme list
2. `ddev magento m:t:b <theme>` — build
3. `ddev magento m:t:w <theme>` — watch (Ctrl+C)
4. `ddev magento m:s:c` — system check

CI covers Magento 2.4.7-p10 (PHP 8.3), 2.4.8-p5 (PHP 8.4), and 2.4.9 (PHP 8.5) with OpenSearch.
Theme codes: `Magento/luma`, `Magento/blank`, Hyvä (has `hyva-themes.json`), Custom (has `tailwind.config.js`).

## Common Pitfalls

- Shell commands in builders → use `Shell` service (DI), not `exec()`
- After `di.xml` changes → `ddev magento cache:clean`
- `detect()` must be unique — BuilderPool picks first match
- Watch mode blocks terminal
- Node/npm runs in DDEV container, not on host
- New admin settings → always update `src/i18n/*.csv`

## Documentation

`docs/` (`commands.md`, `custom_theme_builders.md`, `advanced_usage.md`). Style: DRY, concise, British English.
