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
- If no theme name is provided, displays available themes
- Validates that the specified theme exists
- Cleans the following directories for the theme:
  - `var/view_preprocessed/css/frontend/Vendor/theme`
  - `var/view_preprocessed/source/frontend/Vendor/theme`
  - `pub/static/frontend/Vendor/theme`
- Additionally cleans these global directories:
  - `var/page_cache/*`
  - `var/tmp/*`
  - `generated/*` (preserves .htaccess)
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

## Command Services

The commands rely on several services for their functionality:

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
