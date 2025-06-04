# Theme Checker System

The Theme Checker System in MageForge provides a modular architecture to check Magento 2 themes for outdated dependencies (Composer and NPM packages) based on the theme type.

## Architecture Overview

The Theme Checker System follows a service-based architecture, similar to the Theme Builder System, consisting of:

1. **CheckerInterface** - Defines the contract for all theme checkers
2. **AbstractChecker** - Provides common functionality for all checkers
3. **Specific Checkers** - Implementations for different theme types:
   - **MagentoStandard/Checker** - For standard Magento LESS-based themes
   - **HyvaThemes/Checker** - For Hyvä Tailwind CSS-based themes
   - **TailwindCSS/Checker** - For standalone Tailwind CSS themes
   - **Custom/Checker** - Fallback for custom theme implementations
4. **CheckerPool** - Registry of all available checkers

## How It Works

1. The `mageforge:theme:check` command is executed for specified themes
2. For each theme, the command:
   - Resolves the theme path
   - Gets an appropriate checker from the CheckerPool based on theme detection
   - Executes the checker to find outdated Composer and NPM dependencies
   - Displays the results in a formatted table
3. Each checker implements:
   - A `detect()` method to determine if it can handle a specific theme
   - A `checkComposerDependencies()` method to find outdated Composer packages
   - A `checkNpmDependencies()` method to find outdated NPM packages
   - A `getName()` method to return the checker's name

## Supported Theme Types

The system supports various theme types with specialized checking logic:

### Magento Standard Themes
- Checks for `composer.json` in the theme root
- Falls back to project root for Composer dependencies if needed
- Checks for NPM dependencies in the theme root

### Hyvä Themes
- Extends the Standard theme checker
- Detects Hyvä themes based on:
  - Presence of web/tailwind directory
  - Hyvä references in theme.xml
  - Hyvä references in composer.json
- Checks NPM dependencies in web/tailwind directory

### TailwindCSS Themes
- Detects themes with tailwindcss dependency in package.json
- Checks NPM dependencies in the theme root

### Custom Themes
- Fallback detector for any theme type not covered by other checkers
- Uses standard checking logic

## Creating Custom Checkers

To create a checker for a custom theme type:

1. Create a new class implementing `CheckerInterface` (or extending `AbstractChecker`)
2. Implement the required methods, particularly the `detect()` method
3. Register your checker in `di.xml` with the CheckerPool:

```xml
<type name="OpenForgeProject\MageForge\Service\ThemeChecker\CheckerPool">
    <arguments>
        <argument name="checkers" xsi:type="array">
            <item name="your_checker" xsi:type="object">Your\Namespace\Service\ThemeChecker\YourTheme\Checker</item>
        </argument>
    </arguments>
</type>
```

## Usage Examples

```bash
# Check all themes
bin/magento mageforge:theme:check

# Check specific themes
bin/magento mageforge:theme:check Magento/luma Vendor/theme

# Check with verbose output
bin/magento mageforge:theme:check -v
```
