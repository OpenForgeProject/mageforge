# MageForge for Magento 2 (Beta)

![Mageforge Hero](./.github/assets/mageforge-hero.jpg)

[![Codacy Badge](https://app.codacy.com/project/badge/Grade/7d7c46d7492043c7ada514ed1d4a4c05)](https://app.codacy.com/gh/OpenForgeProject/mageforge/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)  [![CodeFactor](https://www.codefactor.io/repository/github/openforgeproject/mageforge/badge)](https://www.codefactor.io/repository/github/openforgeproject/mageforge)

MageForge is a Magento 2 module designed to assist frontend developers in streamlining their workflow and enhancing productivity.

---

[![Join our OpenForgeProject Discord community](./.github/assets/small_logo_blurple_RGB.png)](https://discord.gg/H5CjMXQQHn)

## Magento Requirements

MageForge requires Magento 2.4.7 or higher.
Please ensure that your Magento installation meets this requirement before installation.

## Features


### Supported Theme-Types 🎨

![Mageforge Hero](./.github/assets/cli-chooser.png)

| Theme Type | Support Status |
|------------|----------------|
| 🎯 Magento Standard | ✅ Fully Supported |
| 🚀 Hyvä | ✅ Fully Supported |
| 🔄 Hyvä Fallback | ✅ Fully Supported |
| 🎨 Custom TailwindCSS (no Hyvä) | ✅ Fully Supported |
| 💼 Avanta B2B | ✅ Fully Supported |
| 🥰 Your Custom Theme | [Create your own Builder](./docs/custom_theme_builders.md) |

---

### Available Commands

| Command                    | Description                                                 | Shortcodes                         |
|---------------------------|-------------------------------------------------------------|-----------------------------------|
| `mageforge:system:version`       | Shows current and latest version of the module             | `m:s:v`                            |
| `mageforge:system:check`  | Get system information (OS, PHP, Database, Node.js, etc.)     | `m:s:c`                            |
| `mageforge:theme:list`    | Lists all available themes                                 | `m:t:l`                            |
| `mageforge:theme:build`   | Builds selected themes (CSS/TailwindCSS)                   | `m:t:b`, `frontend:build`          |
| `mageforge:theme:watch`   | Starts watch mode for theme development                    | `m:t:w`, `frontend:watch`          |


---

## Getting Started
### Installation

Install the module via Composer:
```bash
composer require openforgeproject/mageforge
```

Enable the module:
```bash
bin/magento module:enable OpenForgeProject_MageForge
bin/magento setup:upgrade
```

## Getting Started

### Theme Development

1. List all available themes:
   ```bash
   bin/magento mageforge:theme:list
   ```

2. Build a specific theme:
   ```bash
   bin/magento mageforge:theme:build <theme-code>
   ```
   Example: `bin/magento mageforge:theme:build Magento/luma`

3. Start watch mode for development:
   ```bash
   bin/magento mageforge:theme:watch <theme-code>
   ```

### Supported Theme Types

- **Magento Standard Themes**: LESS-based themes
- **Hyvä Themes**: Tailwind CSS based themes
- **Custom Tailwind Themes**: Standalone Tailwind implementations

### Tips & Tricks

- Use the `-v` option for more detailed output
- Watch mode supports hot-reloading for LESS and Tailwind
- Check system information anytime with `mageforge:system:check`
- Use shortcodes for faster command execution, for example `bin/magento m:t:b` instead of `bin/magento mageforge:theme:build`
- Theme build and watch commands have special aliases: `frontend:build` and `frontend:watch`

## Extending MageForge

MageForge provides a modular architecture that allows developers to create custom theme builders for specific project requirements. For more information, see:

- [Custom Theme Builders Documentation](./docs/custom_theme_builders.md)
- [Commands Documentation](./docs/commands.md)

## Report Feature or Bugs

MageForge provides several forms to submit feature requests or report a bug.
You will find it in the [issue section](https://github.com/OpenForgeProject/mageforge/issues) of GitHub.

## Contributing

We welcome contributions from the community! Please see our [Contributing Guidelines](./CONTRIBUTING.md) for more information on how to get involved.

## License

See the [LICENSE](LICENSE) file for more details.

## Support

For support, please open an issue on the [GitHub repository](https://github.com/OpenForgeProject/mageforge/issues) or join our [Discord community](https://discord.gg/H5CjMXQQHn).

## Changelog

All notable changes to this project will be documented in the [CHANGELOG](CHANGELOG.md) file.

---

Thank you for using MageForge!
We hope it makes your development process smoother and more efficient.
