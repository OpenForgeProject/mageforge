# MageForge Development Environment

Welcome to the **MageForge** development repository! This is where the MageForge Magento 2 module is being developed.

## 🏗️ Repository Structure

Understanding the structure is crucial for contributing:

```
/mageforge
├── /src/                  # ⭐ The actual MageForge module code
│   ├── /Console/         # CLI commands
│   ├── /Service/         # Business logic & theme builders
│   ├── /Model/           # Domain models
│   ├── /etc/             # Module configuration (di.xml, module.xml)
│   ├── composer.json     # Module dependencies
│   └── README.md         # End-user documentation (install guide)
│
├── /magento/             # Local Magento 2 installation for testing
│   ├── /app/design/      # Test themes
│   ├── /vendor/          # Magento & dependencies
│   └── /bin/magento      # Magento CLI
│
├── /.ddev/               # DDEV configuration
│   └── /commands/web/    # Custom DDEV commands (e.g., install-magento)
│
└── README.md             # This file - Developer setup guide
```

**Important**:

- 💻 **Module development happens in `/src/`** - this is where you write code
- 🧪 **Testing happens in `/magento/`** - a full Magento installation for local testing
- The `/src/` directory is symlinked into `/magento/app/code/OpenForgeProject/MageForge/` during installation

## 🚀 Quick Start for Developers

### Prerequisites

- **DDEV**: [Installation Guide](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)
- **Git**: For cloning the repository
- Basic knowledge of Magento 2 module development

### Initial Setup

1. **Clone the repository**:

   ```bash
   git clone git@github.com:OpenForgeProject/mageforge.git
   cd mageforge
   ```

2. **Start DDEV** (downloads containers, configures environment):

   ```bash
   ddev start
   ```

3. **Install Magento 2** (creates database, installs sample data, symlinks module):

   ```bash
   ddev install-magento
   ```

   This script:

   - Installs a fresh Magento 2.4.7 instance in `/magento`
   - Creates sample data and test themes
   - Symlinks `/src` → `/magento/app/code/OpenForgeProject/MageForge`
   - Enables the MageForge module

4. **Verify installation**:
   ```bash
   ddev magento mageforge:system:check
   ```

**🎉 Done!** You now have a fully functional development environment.

## 🛠️ Development Workflow

### Making Changes to the Module

1. **Edit code in `/src/`** (e.g., commands, services, builders)

2. **Apply changes**:

   ```bash
   ddev magento setup:upgrade     # Activate module updates
   ddev magento cache:clean       # Clear cache
   ```

3. **Test your changes**:
   ```bash
   ddev magento m:t:l             # List themes
   ddev magento m:t:b <theme>     # Build a theme
   ```

### Useful DDEV Commands

```bash
ddev magento <command>          # Run any Magento CLI command
ddev ssh                        # SSH into the container
ddev xdebug on/off              # Toggle Xdebug (or use VSCode tasks)
ddev logs                       # View container logs
ddev restart                    # Restart containers
```

### Running Tests Manually

```bash
# Test theme detection
ddev magento m:t:l

# Test Hyvä theme build
ddev magento m:t:b Hyva/default

# Test watch mode (Ctrl+C to exit)
ddev magento m:t:w Hyva/default

# System diagnostics
ddev magento m:s:c
```

## 📚 Documentation for Developers

- **[src/README.md](src/README.md)** - End-user documentation (features, installation for production)
- **[src/docs/commands.md](src/docs/commands.md)** - Command reference
- **[src/docs/custom_theme_builders.md](src/docs/custom_theme_builders.md)** - How to create custom theme builders
- **[src/docs/advanced_usage.md](src/docs/advanced_usage.md)** - Troubleshooting & advanced topics
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Contribution guidelines
- **[.github/copilot-instructions.md](.github/copilot-instructions.md)** - Coding standards & architecture

## 🧪 Code Quality & Linting

```bash
# Run all linters (via Trunk)
trunk check

# Auto-format code
trunk fmt

# Magento Coding Standard (manual)
ddev phpcs src/
```

See [Copilot Instructions](.github/copilot-instructions.md) for detailed PHP conventions (PER-CS-2.0, strict typing, etc.).

## 🐛 Common Issues

**Module not found after changes?**

```bash
ddev magento setup:upgrade && ddev magento cache:clean
```

**DDEV not starting?**

```bash
ddev poweroff
ddev start
```

**Need to reinstall Magento?**

```bash
ddev install-magento  # Script will handle cleanup
```

## 🤝 Contributing

1. Create a feature/fix branch: `git checkout -b feature/your-feature`
2. Make your changes in `/src/`
3. Test locally using `ddev magento` commands
4. Run linters: `trunk check`
5. Commit: `#<issue-nr> - <message>` (e.g., `#42 - Add Hyvä builder`)
6. Push and create a Pull Request

## 📦 Building a Release

The module in `/src/` is what gets released to Packagist. End users install it via:

```bash
composer require openforgeproject/mageforge
```

The `/magento/` directory is **only for local development** and is not part of the release.

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/OpenForgeProject/mageforge/issues)
- **Discord**: [Join our community](https://discord.gg/H5CjMXQQHn)

---

**Happy Coding!** 🧙‍♂️✨
