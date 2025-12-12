# GitHub Copilot Instructions for MageForge

## Project Overview

MageForge is a powerful CLI front-end development toolkit for Magento 2 that simplifies theme development workflows. It provides tools for many types of Magento themes (Magento Standard, Hyvä, Hyvä Fallback, Custom TailwindCSS, Avanta B2B) and can be easily extended for custom themes.

## Technology Stack

- **Platform**: Magento 2 (requires 2.4.7 or higher)
- **Language**: PHP 8.3+
- **Package Manager**: Composer
- **Type**: magento2-module
- **Dependencies**: 
  - Laravel Prompts (for interactive CLI)
  - Magento Framework

## Coding Standards

### General PHP Standards

- **Follow Magento Coding Standards**: All PHP code must adhere to the Magento 2 Coding Standards
- **PSR-4 Autoloading**: The project uses PSR-4 with namespace `OpenForgeProject\MageForge\`
- **Type Declarations**: Use strict typing (`declare(strict_types=1);`) at the top of every PHP file
- **Type Hints**: Always use type hints for parameters and return types
- **Property Promotion**: Use PHP 8+ constructor property promotion with readonly where appropriate
- **Documentation**: Use PHPDoc blocks for classes and methods

### Code Formatting

- **Indentation**: Use 4 spaces (not tabs) for indentation
- **Line Length**: Keep lines under 80 characters wherever possible
- **Naming Conventions**: 
  - Classes: PascalCase
  - Methods and properties: camelCase
  - Constants: UPPER_SNAKE_CASE
  - Choose meaningful names for variables and functions
- **Comments**: Write clear and concise comments where necessary

### Code Structure

- **Directory Structure**:
  - `src/Console/Command/` - CLI commands
  - `src/Service/` - Business logic services
  - `src/Model/` - Data models
  - `src/Exception/` - Custom exceptions
  - `src/Service/ThemeBuilder/` - Theme builder implementations
- **Interfaces**: Use interfaces for builder patterns (e.g., `BuilderInterface`)
- **Dependency Injection**: Use constructor injection with readonly properties

## Build and Validation

### Linting

- **PHPCS**: Use Magento Coding Standard for PHP code
  ```bash
  composer create-project magento/magento-coding-standard --stability=dev /tmp/magento-coding-standard
  /tmp/magento-coding-standard/vendor/bin/phpcs -p -s --standard=Magento2 src/
  ```
- **Trunk**: Run `trunk check` to lint code before submission (if available)

### Testing

- Thoroughly test code before submitting
- Test all CLI commands with various theme types
- Ensure compatibility with Magento 2.4.7+

## Documentation

- **Format**: Use Markdown syntax for all documentation files
- **Location**: Documentation files go in the `docs/` directory
- **README**: Keep README.md updated with new features or commands
- **Comments**: Provide descriptions for functions, classes, and parameters

## Git Workflow

### Commits

- **Format**: Use format `#<issue-number> - <commit message>`
- **Example**: `#123 - Add support for custom theme builder`
- **Messages**: Write clear, descriptive commit messages
- **VSCode**: Use Git Commit Message Helper extension for proper formatting

### Pull Requests

1. Create an issue first to describe the feature/bug
2. Fork the repository and create a branch for your work
3. Make changes with clear commit messages
4. Submit PR to merge into `main` branch
5. Ensure all GitHub Actions checks pass

## Magento-Specific Guidelines

- **Module Registration**: Module is registered via `src/registration.php`
- **Commands**: All CLI commands extend `AbstractCommand` and follow Magento command patterns
- **Command Naming**: Use format `mageforge:<category>:<action>` (e.g., `mageforge:theme:build`)
- **Shortcodes**: Provide shortcodes for commands (e.g., `m:t:b` for `mageforge:theme:build`)
- **Dependency Injection**: Use Magento's dependency injection container
- **Services**: Service classes should be in the `Service/` directory and follow single responsibility principle

## Theme Builder Development

When adding or modifying theme builders:

- Implement `BuilderInterface`
- Place builders in `src/Service/ThemeBuilder/<ThemeType>/Builder.php`
- Register builders with `BuilderPool`
- Support both build and watch modes
- Handle npm/node dependencies appropriately
- Follow existing builder patterns (Magento Standard, Hyvä, TailwindCSS)

## Best Practices

- **Error Handling**: Use custom exceptions in the `Exception/` directory
- **Console Output**: Use Symfony Console's IO helpers for consistent output
- **Configuration**: Keep configuration flexible to support different theme types
- **Extensibility**: Design features to be easily extended for custom themes
- **Performance**: Optimize build processes for fast development workflows
- **Compatibility**: Ensure compatibility with Magento 2.4.7+ requirements

## Common Commands

- `mageforge:theme:list` (or `m:t:l`) - List available themes
- `mageforge:theme:build` (or `m:t:b`) - Build theme CSS/TailwindCSS
- `mageforge:theme:watch` (or `m:t:w`) - Start watch mode for development
- `mageforge:system:check` (or `m:s:c`) - Get system information
- `mageforge:system:version` (or `m:s:v`) - Show version information

## License

This project is licensed under GPL-3.0. By contributing, you agree that your work will be licensed under the same terms.

## Community

- Report bugs and request features via GitHub Issues
- Join the OpenForgeProject Discord community for support
- Follow the Contributing Guidelines in CONTRIBUTING.md
