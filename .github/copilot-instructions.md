# MageForge Copilot Instructions

## Project Architecture

**MageForge** is a CLI-based frontend toolkit for Magento 2, structured as a Magento module (`magento2-module`). The project uses a Builder Pattern architecture to support different theme types (Magento Standard, Hyvä, TailwindCSS, etc.).

### Core Structure

```bash
/magento               # Magento 2 installation (local with DDEV)
/src                   # MageForge module code
  /src/Console/Command # CLI commands (extend AbstractCommand)
  /src/Service         # Business logic & theme builders
  /src/Model           # Domain models
  /src/etc/di.xml      # DI configuration for commands & builders
```

**Important**: Module code lives in `/src`, Magento installation in `/magento`. DDEV manages local dev environment.

## Development Environment

### DDEV Workflow

```bash
ddev start                    # Start DDEV environment
ddev install-magento          # Fresh Magento install (from .ddev/commands/web/)
ddev magento <cmd>            # Run Magento CLI
ddev xdebug on/off            # Toggle Xdebug (tasks available)
ddev ssh                      # SSH into container
```

- **Magento Installation**: Script under `.ddev/commands/web/install-magento` installs Magento from scratch, creates sample data and symlinks MageForge module
- **PHP Version**: 8.3 (configured in `.ddev/config.yaml`)
- **Database**: MariaDB 10.6
- **Webserver**: nginx-fpm
- **Node.js**: For theme builders (npm/grunt run in containers)

### Testing Changes

After module changes:

```bash
ddev magento setup:upgrade      # Activate module updates
ddev magento cache:clean        # Clear cache
ddev magento m:t:b <theme>      # Test theme build
```

## PHP Conventions (PER-CS-2.0 + Magento 2)

### Strict Typing & Property Promotion

**All** PHP files MUST start with:

```php
<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\...;
```

**Constructor Property Promotion** with `readonly`:

```php
public function __construct(
    private readonly ThemeList $themeList,
    private readonly Shell $shell
) {}
```

**No** FQN in docblocks - use type hints:

```php
// ✓ CORRECT
public function build(string $themePath, SymfonyStyle $io): bool
{
    // ...
}

// ✗ WRONG
/**
 * @param string $themePath
 * @param \Symfony\Component\Console\Style\SymfonyStyle $io
 * @return bool
 */
public function build($themePath, $io)
```

### Named Arguments & Enums

- Use **named arguments** when many parameters
- Prefer **Enums** over constants (PHP 8.3+)

## Builder Pattern for Theme Types

### Creating New Builders

1. **Builder class** under `src/Service/ThemeBuilder/<Type>/Builder.php`:

   ```php
   class Builder implements BuilderInterface
   {
       public function detect(string $themePath): bool { /* Logic */ }
       public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool { /* Logic */ }
       public function watch(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool { /* Logic */ }
       public function autoRepair(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool { /* Logic */ }
       public function getName(): string { return 'TypeName'; }
   }
   ```

2. **Registration** in `src/etc/di.xml`:

   ```xml
   <type name="OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool">
       <arguments>
           <argument name="builders" xsi:type="array">
               <item name="yourbuilder" xsi:type="object">OpenForgeProject\MageForge\Service\ThemeBuilder\YourType\Builder</item>
           </argument>
       </arguments>
   </type>
   ```

3. **detect()** method is critical: Must uniquely identify if builder handles a theme (e.g. via `hyva-themes.json` for Hyvä)

### Example: Hyvä Builder Detection

```php
public function detect(string $themePath): bool
{
    return $this->fileDriver->isExists($themePath . '/etc/hyva-themes.json');
}
```

## CLI Commands

### Command Structure

All commands extend `AbstractCommand` and use `executeCommand()`:

```php
class BuildCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setName($this->getCommandName('theme', 'build'))
             ->setAliases(['m:t:b', 'frontend:build'])
             ->setDescription('Build theme assets')
             ->addArgument('theme', InputArgument::OPTIONAL);
    }

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        // $this->io already available (SymfonyStyle)
        // Return Cli::RETURN_SUCCESS or Cli::RETURN_FAILURE
    }
}
```

**Important**: Define shortcodes (`m:t:b`) and aliases (`frontend:build`) in `setAliases()`.

## Code Quality & Linting

### Trunk Integration

```bash
trunk check              # Run all enabled linters
trunk fmt                # Auto-format
```

**Active Linters** (`.trunk/trunk.yaml`):

- `actionlint`, `hadolint` (Docker), `markdownlint`, `prettier`, `shellcheck`, `yamllint`
- **No PHPCS**: Use Magento Coding Standard manually instead (see below)
- **No checkov**: Disabled due to tmpfile handling issues with Trunk

### Magento Coding Standard

```bash
# In DDEV:
ddev phpcs src/

# Or locally:
composer create-project magento/magento-coding-standard --stability=dev /tmp/mcs
/tmp/mcs/vendor/bin/phpcs -p -s --standard=Magento2 src/
```

**User Settings**: JSON shorthand `{"php":"s=1,PER-CS-2.0,8.3>mag,typed,ro,ctor,enum>const,named,!FQN"}` means:

- `s=1`: strict_types=1
- `PER-CS-2.0`: PHP Extended Coding Style 2.0
- `8.3>mag`: PHP 8.3+ over Magento conventions
- `typed`: Type hints mandatory
- `ro`: Prefer readonly properties
- `ctor`: Constructor property promotion
- `enum>const`: Enums before constants
- `named`: Use named arguments
- `!FQN`: No FQN in docblocks

## Git Workflow

### Branch Naming

```bash
fix/<issue-description>
feature/<issue-description>
```

**Current branch**: `fix/codequality` (per attachment)

### Commit Format

```bash
#<issue-nr> - <message>
```

Example: `#42 - Fix Hyvä builder detection logic`

**VSCode**: Git Commit Message Helper extension auto-extracts issue number from branch name.

## Testing Strategy

### Manual Tests

For every change:

1. Check theme list: `ddev magento m:t:l`
2. Test build: `ddev magento m:t:b <theme-code>`
3. Watch mode: `ddev magento m:t:w <theme-code>` (Ctrl+C to exit)
4. System check: `ddev magento m:s:c` (shows PHP, Node, DB, etc.)

### Theme Codes for Testing

- **Standard**: `Magento/luma`, `Magento/blank`
- **Hyvä**: Themes with `hyva-themes.json`
- **Custom**: Themes with `tailwind.config.js` (without Hyvä)

## Common Pitfalls

- **Shell commands in builders**: Use `Shell` service (DI), not `exec()` directly
- **Cache**: After `di.xml` changes always run `ddev magento cache:clean`
- **Builder order**: BuilderPool selects first matching builder - `detect()` must be unique
- **Watch mode**: Blocks terminal - user must exit with Ctrl+C
- **Node/npm**: Runs in DDEV container, not on host

## Documentation

### Structure

- `README.md` (root): Overview, installation, quick start
- `src/README.md`: Developer setup with DDEV
- `src/docs/`:
  - `commands.md`: Command reference
  - `custom_theme_builders.md`: Guide for custom builders
  - `advanced_usage.md`: Troubleshooting

**Documentation style**: DRY (Don't Repeat Yourself), concise, no fluff, British English
