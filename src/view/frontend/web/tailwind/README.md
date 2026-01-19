# MageForge Inspector - TailwindCSS Build

This directory contains the TailwindCSS configuration for the MageForge Inspector frontend component.

## Build Instructions

The Inspector CSS is built separately from theme CSS to ensure complete isolation and avoid conflicts.

### Development Build

```bash
cd src/view/frontend/web/tailwind
npm install
npm run build
```

### Watch Mode (for development)

```bash
cd src/view/frontend/web/tailwind
npm run watch
```

### Production Build

The build script automatically minifies CSS for production. Run:

```bash
npm run build
```

Output: `../css/inspector.css`

## Configuration

- **Prefix**: All Tailwind utilities are prefixed with `mf-` to avoid conflicts
- **Important**: Scoped to `.mageforge-inspector` class
- **Purge**: Automatically scans `../js/inspector.js` and `../templates/**/*.phtml`

## Architecture

The Inspector uses its own TailwindCSS bundle to:
- Avoid conflicts with theme styles
- Ensure consistent appearance across different Magento themes
- Maintain independence from theme TailwindCSS versions
- Allow updates without affecting theme builds

## Rebuilding After Changes

If you modify:
- `input.css` - Rebuild required
- `tailwind.config.js` - Rebuild required
- `inspector.js` or `inspector.phtml` - Rebuild recommended (for purging unused CSS)

Always rebuild before committing changes to ensure the compiled CSS is up-to-date.
