# MageForge Advanced Usage

This document provides detailed information and advanced tips for using MageForge effectively in your Magento 2 development workflow.

## Theme Development Tips

### Command Efficiency

- Alternative aliases for common commands:
  - `frontend:build` for `mageforge:theme:build`
  - `frontend:watch` for `mageforge:theme:watch`

Check `bin/magento mageforge` for a full list of available commands.

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
- LESS compilation via Grunt
- Source map generation
- Minification for production

#### Vendor Themes

MageForge automatically detects themes installed via Composer (located in `vendor/` directory):
- **Build mode**: Skips all Grunt/Node.js steps as vendors themes have pre-built assets
- **Watch mode**: Returns an error as vendor themes are read-only and cannot be modified

This prevents accidental modification attempts and ensures build process stability.

#### Themes Without Node.js/Grunt Setup

MageForge automatically detects if a Magento Standard theme intentionally omits Node.js/Grunt setup. If none of the following files exist:
- `package.json`
- `package-lock.json`
- `Gruntfile.js`
- `grunt-config.json`

The builder will skip all Node/Grunt-related steps and only:
- Clean static content (if in developer mode)
- Deploy static content
- Clean cache

This is useful for:
- Themes that use pre-compiled CSS
- Minimal themes without custom LESS
- Simple theme inheritance without asset compilation

**Note**: Watch mode requires Node.js/Grunt setup and will return an error if these files are missing.

### Hyvä Themes (Tailwind CSS)

MageForge streamlines Hyvä theme development with:
- Automatic TailwindCSS compilation
- PurgeCSS optimization
- Component scanning

### Custom Tailwind CSS Implementations

For custom Tailwind setups (non-Hyvä), MageForge supports:
- Custom Tailwind configuration
- PostCSS processing
- Custom directory structures

### Avanta B2B Themes

Avanta is a B2B theme built on top of Hyvä. Because MageForge detects Hyvä themes via `etc/hyva-themes.json`, Avanta themes are automatically recognised and built using the Hyvä builder — no additional configuration required.

```bash
bin/magento mageforge:theme:build Vendor/avanta
```

All Hyvä builder features apply: TailwindCSS compilation, PurgeCSS optimisation, and watch mode.

## Performance Optimization

### Build Multiple Themes in One Pass

`mageforge:theme:build` accepts multiple theme codes, so you can build several themes without re-running the command:

```bash
bin/magento mageforge:theme:build Vendor/theme1 Vendor/theme2
```

You can also pass a vendor prefix to build all themes from that vendor at once:

```bash
bin/magento mageforge:theme:build Hyva
```

### Use Watch Mode During Development

Instead of triggering full rebuilds manually, use watch mode. It detects file changes and recompiles only what is needed, keeping feedback loops short:

```bash
bin/magento mageforge:theme:watch Vendor/theme
```

### Verbose Output for Debugging Slow Builds

Add `-v` to see each step's timing and output, which helps identify bottlenecks:

```bash
bin/magento mageforge:theme:build Vendor/theme -v
```

### Vendor Themes Are Built Instantly

Themes installed via Composer (in the `vendor/` directory) are detected automatically. MageForge skips all Node.js and Grunt steps for them, as their assets are pre-built. Building a vendor theme only runs static content deployment and cache clean.

### Themes Without Node.js/Grunt

Themes that omit `package.json` / `Gruntfile.js` also skip the Node.js pipeline entirely, resulting in significantly faster builds (static content deployment and cache clean only).

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

For more help, open an issue on [GitHub Issues](https://github.com/OpenForgeProject/mageforge/issues) or start a discussion on [GitHub Discussions](https://github.com/OpenForgeProject/mageforge/discussions).
