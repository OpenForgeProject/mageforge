# MageForge Commands Documentation

This document provides a comprehensive overview of all commands available in the MageForge module. It's designed to help developers understand the structure and functionality of each command.

## Command Architecture

All commands in MageForge follow a consistent structure based on Symfony's Console Component. They extend the `Symfony\Component\Console\Command\Command` class and implement:

- A constructor that injects dependencies
- A `configure()` method that sets the command name, description, and arguments/options
- An `execute()` method that handles the command logic

## Available Commands

### 1. ListThemeCommand (`mageforge:theme:list`)

**Purpose**: Lists all available Magento themes in the installation.

**File**: `/src/Console/Command/ListThemeCommand.php`

**Dependencies**:

- `ThemeList` - Service to retrieve theme information

**Usage**:

```bash
bin/magento mageforge:theme:list
```

**Implementation Details**:

- Retrieves all themes from the `ThemeList` service
- Displays a formatted table with theme information (code, title, path)
- Returns success status code

---

### 2. BuildThemeCommand (`mageforge:theme:build`)

**Purpose**: Builds specified Magento themes by compiling assets and deploying static content.

**File**: `/src/Console/Command/BuildThemeCommand.php`

**Dependencies**:

- `ThemePath` - Service to resolve theme paths
- `ThemeList` - Service to retrieve theme information
- `BuilderPool` - Service to get appropriate builders for themes

**Usage**:

```bash
bin/magento mageforge:theme:build [<themeCodes>...]
```

**Implementation Details**:

- If no theme codes are provided, displays an interactive prompt to select themes
- For each selected theme:
  1. Resolves the theme path
  2. Determines the appropriate builder for the theme type
  3. Executes the build process
- Displays a summary of built themes and execution time
- Has an alias: `frontend:build`

---

### 3. ThemeWatchCommand (`mageforge:theme:watch`)

**Purpose**: Watches theme files for changes and automatically rebuilds when changes are detected.

**File**: `/src/Console/Command/ThemeWatchCommand.php`

**Dependencies**:

- `BuilderPool` - Service to get appropriate builders for themes
- `ThemeList` - Service to retrieve theme information
- `ThemePath` - Service to resolve theme paths

**Usage**:

```bash
bin/magento mageforge:theme:watch [--theme=THEME]
```

**Implementation Details**:

- If no theme code is provided, displays an interactive prompt to select a theme
- Resolves the theme path
- Determines the appropriate builder for the theme type
- Starts a watch process that monitors for file changes
- Has an alias: `frontend:watch`

---

### 4. CleanCommand (`mageforge:static:clean`)

**Purpose**: Cleans var/view_preprocessed, pub/static, var/page_cache, var/tmp and generated directories for specific theme.

**File**: `/src/Console/Command/Static/CleanCommand.php`

**Dependencies**:
- `Filesystem` - Magento filesystem component for file operations
- `ThemeList` - Service to retrieve theme information
- `ThemePath` - Service to resolve theme paths

**Usage**:
```bash
bin/magento mageforge:static:clean [<themename>]
```

**Implementation Details**:
- If no theme name is provided:
  - In interactive terminals, displays an interactive prompt to select the theme to clean
  - In non-interactive environments, prints the list of available themes and exits, requiring an explicit theme name
- Validates that the specified theme exists
- Cleans the following directories for the theme:
  - `var/view_preprocessed/css/frontend/Vendor/theme`
  - `var/view_preprocessed/source/frontend/Vendor/theme`
  - `pub/static/frontend/Vendor/theme`
- Additionally cleans these global directories:
  - `var/page_cache/*`
  - `var/tmp/*`
  - `generated/*`
- Displays a summary of cleaned directories
- Returns success status code

---

### 5. SystemCheckCommand (`mageforge:system:check`)

**Purpose**: Displays system information relevant to Magento development.

**File**: `/src/Console/Command/SystemCheckCommand.php`

**Dependencies**:

- `ProductMetadataInterface` - For retrieving Magento version
- `Escaper` - For HTML escaping output

**Usage**:

```bash
bin/magento mageforge:system:check
```

**Implementation Details**:

- Retrieves and displays:
  - PHP version
  - Node.js version (with comparison to latest LTS)
  - MySQL version
  - Operating System information
  - Magento version
- Utilizes Symfony's table component for formatted output

---

### 6. VersionCommand (`mageforge:version`)

**Purpose**: Displays the current and latest version of the MageForge module.

**File**: `/src/Console/Command/VersionCommand.php`

**Dependencies**:

- `File` - Filesystem driver for reading files

**Usage**:

```bash
bin/magento mageforge:version
```

**Implementation Details**:

- Reads the current module version from `composer.lock`
- Fetches the latest version from GitHub API
- Displays both versions for comparison

---

### 6. CompatibilityCheckCommand (`mageforge:hyva:compatibility:check`)

**Purpose**: Scans all Magento modules for Hyvä theme compatibility issues such as RequireJS, Knockout.js, jQuery, and UI Components usage.

**File**: `/src/Console/Command/Hyva/CompatibilityCheckCommand.php`

**Dependencies**:

- `CompatibilityChecker` - Main orchestrator service for scanning modules

**Usage**:

```bash
bin/magento mageforge:hyva:compatibility:check [options]
```

**Aliases**:

- `m:h:c:c`
- `hyva:check`

**Options**:

- `--show-all` / `-a` - Show all modules including compatible ones
- `--third-party-only` / `-t` - Check only third-party modules (exclude Magento\_\* modules)
- `--include-vendor` - Include Magento core modules in scan (default: third-party only)
- `--detailed` / `-d` - Show detailed file-level issues for incompatible modules

**Interactive Mode**:
When running **without any options**, the command launches an interactive menu (using Laravel Prompts):

```bash
# Launch interactive menu
bin/magento m:h:c:c
```

The menu allows you to select:

- ☐ Show all modules including compatible ones
- ☐ Show only incompatible modules (default behavior)
- ☐ Include Magento core modules (default: third-party only)
- ☐ Show detailed file-level issues with line numbers

Use **Space** to toggle options, **Enter** to confirm and start the scan.

**Default Behavior**:
Without any flags, the command scans **third-party modules only** (excludes `Magento_*` modules but includes vendor third-party like Hyva, PayPal, Mollie, etc.).

**Examples**:

```bash
# Basic scan (third-party modules only - DEFAULT)
bin/magento m:h:c:c

# Include Magento core modules
bin/magento m:h:c:c --include-vendor

# Show all modules including compatible ones
bin/magento m:h:c:c -a

# Show detailed file-level issues
bin/magento m:h:c:c -d

# Using full command name
bin/magento mageforge:hyva:compatibility:check --detailed
```

**Implementation Details**:

- Scans module directories for JS, XML, and PHTML files
- Detects incompatibility patterns:
  - **Critical Issues**:
    - RequireJS `define()` and `require()` usage
    - Knockout.js observables and computed properties
    - Magento UI Components in XML
    - `data-mage-init` and `x-magento-init` in templates
  - **Warnings**:
    - jQuery AJAX direct usage
    - jQuery DOM manipulation
    - Block removal in layout XML (review needed)
- Displays results in formatted tables with color-coded status:
  - ✓ Green: Compatible modules
  - ⚠ Yellow: Warnings (non-critical issues)
  - ✗ Red: Incompatible (critical issues)
  - ✓ Hyvä-Aware: Modules with Hyvä compatibility packages
- Provides summary statistics:
  - Total modules scanned
  - Compatible vs. incompatible count
  - Hyvä-aware modules count
  - Critical issues and warnings count
- Shows detailed file paths and line numbers with `--detailed` flag
- Provides helpful recommendations for resolving issues
- Returns exit code 1 if any critical issues are found. If only warnings (and no critical issues) are detected, the command returns exit code 0 so CI/CD pipelines do not fail on warnings alone.

**Detected Patterns**:

_JavaScript Files (.js)_:

- `define([` - RequireJS module definition
- `require([` - RequireJS dependency loading
- `ko.observable` / `ko.observableArray` / `ko.computed` - Knockout.js
- `$.ajax` / `jQuery.ajax` - jQuery AJAX
- `mage/` - Magento RequireJS module references

_XML Files (.xml)_:

- `<uiComponent` - UI Component declarations
- `component="uiComponent"` - UI Component references
- `component="Magento_Ui/js/` - Magento UI JS components
- `<referenceBlock.*remove="true"` - Block removals

_PHTML Files (.phtml)_:

- `data-mage-init=` - Magento JavaScript initialization
- `x-magento-init` - Magento 2.4+ JavaScript initialization
- `$(.*).` - jQuery DOM manipulation patterns
- `require([` - RequireJS in templates

**Recommendations Provided**:

- Check for Hyvä compatibility packages on hyva.io/compatibility
- Review module vendor documentation for Hyvä support
- Consider refactoring RequireJS/Knockout to Alpine.js
- Contact module vendors for Hyvä-compatible versions

---

## Command Services

The commands rely on several services for their functionality:

### Hyvä Services

- `CompatibilityChecker`: Main orchestrator for Hyvä compatibility scanning
- `ModuleScanner`: Recursively scans module directories for relevant files
- `IncompatibilityDetector`: Pattern matching service for detecting incompatibilities

### Builder Services

- `BuilderPool`: Manages theme builders and selects appropriate builders for themes
- `BuilderInterface`: Implemented by all theme builders
- `MagentoStandard\Builder`: Processes standard Magento LESS-based themes
- Various other builders for different theme types

### Theme Services

- `ThemeList`: Retrieves all installed themes
- `ThemePath`: Resolves theme codes to filesystem paths
- `StaticContentDeployer`: Handles static content deployment
- `CacheCleaner`: Manages cache cleaning after theme builds

### Utility Services

- `DependencyChecker`: Verifies required dependencies for theme building
- `GruntTaskRunner`: Executes Grunt tasks for theme compilation

## Command Execution Flow

1. The command is executed via the Magento CLI framework
2. Dependencies are injected via constructor
3. Arguments and options are processed
4. Interactive prompts are shown if required
5. The appropriate services are called to perform the command's task
6. Formatted output is displayed to the user
7. A status code is returned (success or failure)

## Error Handling

All commands implement error handling via try-catch blocks and return appropriate error messages and status codes when failures occur. Interactive commands also provide suggestions for resolving issues.
