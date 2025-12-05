# MageForge Advanced Usage

This document provides detailed information and advanced tips for using MageForge effectively in your Magento 2 development workflow.

## Theme Development Tips

### Command Efficiency

- Use shortcodes for faster command execution:
  - `m:t:b` instead of `mageforge:theme:build`
  - `m:t:w` instead of `mageforge:theme:watch`
  - `m:s:c` for system check

- Alternative aliases for common commands:
  - `frontend:build` for `mageforge:theme:build`
  - `frontend:watch` for `mageforge:theme:watch`

### Development Workflow

1. **System Check**: Before starting development, run a system check:
   ```bash
   bin/magento mageforge:system:check
   ```

2. **Enhanced Output**: Use the `-v` option for more detailed information during builds:
   ```bash
   bin/magento mageforge:theme:build <theme-code> -v
   ```

3. **Hot-reloading**: Watch mode automatically detects changes and rebuilds:
   - For LESS files in standard Magento themes
   - For Tailwind-based templates in Hyvä themes
   - For custom CSS implementations

## Theme Type Specifics

### Standard Magento Themes (LESS)

For traditional LESS-based Magento themes, MageForge handles:
- LESS compilation
- Source map generation
- Minification for production

### Hyvä Themes (Tailwind CSS)

MageForge streamlines Hyvä theme development with:
- Automatic TailwindCSS compilation
- PurgeCSS optimization
- Component scanning
- Design tokens support for consistent theming

#### Hyvä Design Tokens

The `mageforge:hyva:tokens` command allows you to generate CSS custom properties from design token definitions, making it easier to maintain consistent design systems across your Hyvä theme.

**Basic Usage:**
```bash
bin/magento mageforge:hyva:tokens <theme-code>
```

**Configuration Options:**

Create a `hyva.config.json` file in your theme's `web/tailwind` directory to customize token generation:

1. **Using a token file (default format):**
   ```json
   {
       "tokens": {
           "src": "design.tokens.json"
       }
   }
   ```

   Then create `design.tokens.json`:
   ```json
   {
       "colors": {
           "primary": {
               "lighter": "oklch(62.3% 0.214 259.815)",
               "DEFAULT": "oklch(54.6% 0.245 262.881)",
               "darker": "oklch(37.9% 0.146 265.522)"
           }
       },
       "spacing": {
           "small": "8px",
           "medium": "16px",
           "large": "24px"
       }
   }
   ```

2. **Using Figma tokens:**
   ```json
   {
       "tokens": {
           "src": "acme.figma-tokens.json",
           "format": "figma"
       }
   }
   ```

3. **Using inline token values:**
   ```json
   {
       "tokens": {
           "values": {
               "colors": {
                   "primary": {
                       "lighter": "oklch(62.3% 0.214 259.815)",
                       "DEFAULT": "oklch(54.6% 0.245 262.881)",
                       "darker": "oklch(37.9% 0.146 265.522)"
                   }
               }
           }
       }
   }
   ```

4. **Using custom CSS selector (for Tailwind v3):**
   ```json
   {
       "tokens": {
           "src": "design.tokens.json",
           "cssSelector": ":root"
       }
   }
   ```

The command generates `generated/hyva-tokens.css` with CSS custom properties that you can import in your Tailwind configuration.

### Custom Tailwind CSS Implementations

For custom Tailwind setups (non-Hyvä), MageForge supports:
- Custom Tailwind configuration
- PostCSS processing
- Custom directory structures

### Avanta B2B Theme

MageForge has special support for Avanta B2B themes with:
- B2B-specific component scanning
- Special optimization for B2B module templates

## Performance Optimization

- Build specific theme components instead of entire themes for faster development
- Use the production flag for minified, optimized output when ready for deployment
- Consider selective watching for large themes to improve performance

## Troubleshooting

Common issues and solutions:

1. **Build Failures**:
   - Ensure Node.js is installed and available
   - Check for syntax errors in LESS or CSS files
   - Verify Tailwind configuration is valid

2. **Watch Mode Issues**:
   - Check file permissions
   - Ensure no conflicting processes are running

3. **Integration Problems**:
   - Clear Magento cache after theme builds
   - Verify theme registration in Magento

For more help, join our [Discord community](https://discord.gg/H5CjMXQQHn) or open an issue on GitHub.
