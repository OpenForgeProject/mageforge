# Changelog for MageForge

All notable changes to this project will be documented in this file.

---

## UNRELEASED

## Latest Release

## [0.4.0](https://github.com/OpenForgeProject/mageforge/compare/0.3.1...0.4.0) (2026-01-17)


### Added

* add theme suggestion service and integrate with commands [#75](https://github.com/OpenForgeProject/mageforge/issues/75) ([#76](https://github.com/OpenForgeProject/mageforge/issues/76)) ([1347782](https://github.com/OpenForgeProject/mageforge/commit/13477823e82b81bda5412a7f8d4cbd747254d6c5))
* implement Release Please workflow and update configuration ([d814853](https://github.com/OpenForgeProject/mageforge/commit/d814853c220dd098ad11340ea179ad66f9fb01f7))


### Fixed

* adjust command argument order and clean up whitespace ([9c4fb73](https://github.com/OpenForgeProject/mageforge/commit/9c4fb7356d23c38096e798d797fdfcb4ec455ff5))
* enhance interactive mode for compatibility checks and prompts ([#79](https://github.com/OpenForgeProject/mageforge/issues/79)) ([428a133](https://github.com/OpenForgeProject/mageforge/commit/428a1338479a2ad1642ea7a353d0c73e37ef155b))
* improve theme selection and validation in TokensCommand ([#77](https://github.com/OpenForgeProject/mageforge/issues/77)) ([9167e95](https://github.com/OpenForgeProject/mageforge/commit/9167e956ddd1faebc03f39a26cf9a8434ecbe99a))


### Documentation

* update community support links to GitHub Discussions ([c67380e](https://github.com/OpenForgeProject/mageforge/commit/c67380ea8366a364ab5161b7e01c1a66872d3e24))
* update dependencies and naming conventions in Copilot instructions ([cf98266](https://github.com/OpenForgeProject/mageforge/commit/cf98266c331d41516c96eddaa32983038adc8ee4))
* update README for command list and support section ([f4fb886](https://github.com/OpenForgeProject/mageforge/commit/f4fb886ca698a8f6d4ed3800bb60937f0f88331f))

### [0.3.1] - 2026-01-12

#### Fixed

- fix: add missing static property `$cachedEnv` in CleanCommand to resolve undeclared property error

### [0.3.0] - 2026-01-12

#### Added

- feat: add verbose output support for watch task with `-v` flag
  - Shows informative messages during watch mode based on verbosity level
  - Captures and reports exit codes from npm/grunt watch commands
  - Displays clear error messages when watch mode exits with errors
  - Provides hint to use `-v` flag for verbose output in non-verbose mode
- feat: add `mageforge:theme:tokens` command to generate Hyvä design tokens from design.tokens.json or hyva.config.json
- feat: add `mageforge:hyva:compatibility:check` command to add a Hyvä compatibility checker
  - Scans Magento modules for Hyvä theme compatibility issues
  - Detects RequireJS, Knockout.js, jQuery, and UI Components usage
  - Interactive menu with Laravel Prompts for scan options
  - Options: `--show-all`, `--third-party-only`, `--include-vendor`, `--detailed`
  - Color-coded output (✓ Compatible, ⚠ Warnings, ✗ Incompatible)
  - Detailed file-level issues with line numbers
  - Exit code 1 for critical issues, 0 for success
  - Command aliases: `m:h:c:c`, `hyva:check`
- feat: add `mageforge:static:clean` command for comprehensive cache and generated files cleanup
  - feat: add interactive multi-theme selection for static:clean command using Laravel Prompts
  - feat: add `--all` option to clean all themes at once
  - feat: add `--dry-run` option to preview what would be cleaned without deleting
  - feat: add command alias `frontend:clean` for quick access
  - feat: add CI/CD tests for static:clean command in compatibility workflow

#### Fixed

- fix: remove duplicate `--verbose` option from WatchCommand that conflicted with Symfony Console's built-in verbose option

#### Changed

- refactor: improve build commands to show full output in verbose mode
  - Remove `--quiet` flag from npm/grunt build commands when using verbose mode
  - Allow better debugging of build issues during theme compilation
- refactor: split complex executeCommand method into smaller, focused methods to reduce cyclomatic complexity
- docs: update copilot-instructions.md with CI/CD integration guidelines for new commands

### [0.2.2] - 2025-06-05

- feat: enhance theme command arguments for better clarity and compatibility

### [0.2.1] - 2025-06-04

- feat: reduce cyclomatic complexity
- fix: normalize theme name check to be case-insensitive for Hyva themes

### [0.2.0] - 2025-05-30

- docs: clean up `CHANGELOG.md`
- feat: add PHP 8.4 and Magento 2.4.8 compatibilty check with opensearch support
- feat: enhance MySQL and Search Engine checks for `mageforge:system:check` command
- removed: removed Github Action to watch for Changelog edits
- fix: fixed issue where missing node_modules were not being installed
- fix: fixed issue where watch output was not displayed correctly

### [0.1.0] - 2025-05-23

- docs: add cli-chooser image to README.md
- docs: simplify installation instructions in README.md
- feat: add comprehensive documentation for MageForge commands and custom ThemeBuilders
- feat: add Magento compatibility tests workflow (#35)
- feat: add spinner for theme building process in BuildThemeCommand
- feat: add system check commands for Node.js, MySQL, and environment status
- feat: enhance npm installation process with package-lock.json check
- feat: enhance system check command to display additional environment information
- feat: implement abstract command structure for improved command handling
- fix: improve theme builder to reduce cyclomatic complexity
- fix: restore TTY after prompting for theme selection in BuildThemeCommand and ThemeWatchCommand 🎨
- fix: update MageForge version command in compatibility test workflow
- refactor system and theme commands
- refactor: remove redundant docblocks and improve table headers in SystemCheckCommand
- refactor: simplify theme options retrieval in ThemeWatchCommand
- Update ListCommand.php
- Update custom_theme_builders.md
- Update magento-compatibility.yml
