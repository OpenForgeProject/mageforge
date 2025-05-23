# Developing Custom ThemeBuilders for MageForge

This documentation describes how to create your own ThemeBuilders for MageForge, allowing you to customize the build process for your project's specific requirements.

## Overview

ThemeBuilders in MageForge are modular components responsible for building and watching different types of themes. Each builder specializes in a specific theme type and implements a common interface.

## Basic Architecture

The ThemeBuilder architecture consists of the following components:

1. **BuilderInterface**: Defines the methods that each ThemeBuilder must implement
2. **BuilderPool**: Manages the available builders and selects the appropriate builder for a theme
3. **Concrete Builder Implementations**: Specialized builders for different theme types

## Directory Structure for Your Custom Module

To create your own ThemeBuilder, you'll need to set up a custom Magento 2 module with the following structure:

```
app/code/YourCompany/YourModule/
├── Console/
│   └── Command/
│       └── [Optional custom commands]
├── Service/
│   └── ThemeBuilder/
│       └── YourBuilder/
│           └── Builder.php
├── etc/
│   ├── di.xml
│   └── module.xml
└── registration.php
```

This is a minimal structure for your module. You can add more files and directories as needed for your specific implementation.

## Creating Your Own ThemeBuilder

### Step 1: Create the Module Structure

First, create the basic module structure as shown above:

1. Create the module directory: `app/code/YourCompany/YourModule/`
2. Create a registration.php file:
```php
<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'YourCompany_YourModule',
    __DIR__
);
```

3. Create a module.xml file in the etc directory:
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="YourCompany_YourModule" setup_version="1.0.0">
        <sequence>
            <module name="OpenForgeProject_MageForge"/>
        </sequence>
    </module>
</config>
```

### Step 2: Create a New Builder Class

Create a new Builder class in `app/code/YourCompany/YourModule/Service/ThemeBuilder/YourBuilder/Builder.php`:

```php
<?php

declare(strict_types=1);

namespace YourCompany\YourModule\Service\ThemeBuilder\YourBuilder;

use OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Builder implements BuilderInterface
{
    private const THEME_NAME = 'YourCustomBuilder';

    public function __construct(
        // Add your dependencies here
        // e.g., Filesystem, Shell, custom services
    ) {
    }

    public function detect(string $themePath): bool
    {
        // Implement your detection logic for your theme type
        // For example: Check for specific files or directories

        // Example:
        return file_exists($themePath . '/your-custom-identifier.json');
    }

    public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        // Check if this builder is responsible for the theme
        if (!$this->detect($themePath)) {
            return false;
        }

        // Optional: Call repair logic
        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        // Implement your build logic
        try {
            // Execute build steps
            // e.g., run shell commands, copy files, compile assets

            if ($isVerbose) {
                $io->success('Build for ' . self::THEME_NAME . ' completed successfully.');
            }
        } catch (\Exception $e) {
            $io->error('Build failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function autoRepair(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        // Implement your logic for automatic repair
        // e.g., install missing dependencies, create configurations

        return true;
    }

    public function watch(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
    {
        // Check if this builder is responsible for the theme
        if (!$this->detect($themePath)) {
            return false;
        }

        // Optional: Call repair logic
        if (!$this->autoRepair($themePath, $io, $output, $isVerbose)) {
            return false;
        }

        // Implement your watch logic
        try {
            // Start watch process
            // e.g., execute shell command with exec() or shell_exec()

            if ($isVerbose) {
                $io->success('Watch for ' . self::THEME_NAME . ' started.');
            }
        } catch (\Exception $e) {
            $io->error('Watch failed: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return self::THEME_NAME;
    }
}
```

### Step 3: Register Your Builder in the DI System

Create a `di.xml` file in `app/code/YourCompany/YourModule/etc/`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="OpenForgeProject\MageForge\Service\ThemeBuilder\BuilderPool">
        <arguments>
            <argument name="builders" xsi:type="array">
                <item name="yourCustomBuilder" xsi:type="object">YourCompany\YourModule\Service\ThemeBuilder\YourBuilder\Builder</item>
            </argument>
        </arguments>
    </type>
</config>
```

### Step 4: Install and Enable Your Module

After creating all the required files, you need to enable your module:

1. Run `bin/magento module:enable YourCompany_YourModule`
2. Run `bin/magento setup:upgrade`
3. Run `bin/magento cache:clean`

After these steps, your custom ThemeBuilder will be available and integrated with MageForge.

## Implementation Details

### The detect() Method

This method is crucial as it determines whether your builder is responsible for a specific theme. Implement logic here that uniquely identifies your theme type.

```php
public function detect(string $themePath): bool
{
    // Examples of detection strategies:

    // 1. Check for specific configuration files
    if (file_exists($themePath . '/your-config.json')) {
        return true;
    }

    // 2. Check for specific directory structures
    if (is_dir($themePath . '/your-special-folder')) {
        return true;
    }

    // 3. Check for content in specific files
    if (file_exists($themePath . '/theme.xml')) {
        $content = file_get_contents($themePath . '/theme.xml');
        if (strpos($content, 'your-identifier') !== false) {
            return true;
        }
    }

    return false;
}
```

### The build() Method

This method performs the actual build process. You can implement any steps required for your theme type:

```php
public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    // Build process examples:

    // 1. SCSS/SASS Compilation
    $io->text('Compiling SCSS files...');
    try {
        $this->shell->execute('sass ' . $themePath . '/web/scss:' . $themePath . '/web/css --style compressed');
    } catch (\Exception $e) {
        $io->error('SCSS compilation failed: ' . $e->getMessage());
        return false;
    }

    // 2. Create JavaScript bundles
    $io->text('Creating JavaScript bundles...');
    try {
        $this->shell->execute('webpack --config ' . $themePath . '/webpack.config.js');
    } catch (\Exception $e) {
        $io->error('JavaScript bundling failed: ' . $e->getMessage());
        return false;
    }

    // 3. Deploy static content
    $themeCode = basename($themePath);
    $this->staticContentDeployer->deploy($themeCode, $io, $output, $isVerbose);

    // 4. Clear cache
    $this->cacheCleaner->clean($io, $isVerbose);

    return true;
}
```

### The autoRepair() Method

This method should automatically fix potential issues before starting the build process:

```php
public function autoRepair(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    // Examples of repair measures:

    // 1. Create missing configuration files
    if (!file_exists($themePath . '/your-config.json')) {
        if ($isVerbose) {
            $io->warning('Configuration file missing. Creating default configuration...');
        }
        file_put_contents(
            $themePath . '/your-config.json',
            json_encode(['version' => '1.0.0', 'options' => []])
        );
    }

    // 2. Install missing dependencies
    if (!is_dir($themePath . '/node_modules')) {
        if ($isVerbose) {
            $io->warning('Node modules missing. Running npm install...');
        }
        try {
            $this->shell->execute('cd ' . $themePath . ' && npm install --quiet');
        } catch (\Exception $e) {
            $io->error('Error installing node modules: ' . $e->getMessage());
            return false;
        }
    }

    return true;
}
```

### The watch() Method

This method starts a process that monitors changes to theme files and automatically rebuilds when necessary:

```php
public function watch(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    // Examples of watch implementations:

    // 1. Start your custom watcher
    try {
        $command = 'node ' . $themePath . '/your-watcher.js';
        if ($isVerbose) {
            $io->text('Starting watch process with: ' . $command);
        }
        exec($command);
    } catch (\Exception $e) {
        $io->error('Watch process could not be started: ' . $e->getMessage());
        return false;
    }

    // 2. Use existing tools
    try {
        exec('cd ' . $themePath . ' && npm run watch');
    } catch (\Exception $e) {
        $io->error('Watch process could not be started: ' . $e->getMessage());
        return false;
    }

    return true;
}
```

## Advanced Techniques

### Working with Multiple Themes Simultaneously

If you need to work with multiple themes simultaneously, you can create a specialized builder that supports this:

```php
public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    // Find all dependent themes
    $parentThemes = $this->findParentThemes($themePath);

    // Build parent themes first
    foreach ($parentThemes as $parentTheme) {
        $io->text('Building parent theme: ' . basename($parentTheme));
        // Call build logic for parent theme here
    }

    // Then build the current theme
    // ...

    return true;
}
```

### Integration with Build Tools

You can integrate your builder with popular build tools like Webpack, Gulp, or Vite:

```php
public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    // Dynamically adjust Webpack configuration
    $webpackConfig = $themePath . '/webpack.config.js';
    if (file_exists($webpackConfig)) {
        // Adjust configuration if necessary
        // ...
    }

    // Run Webpack
    try {
        $this->shell->execute('cd ' . $themePath . ' && npx webpack --mode production');
    } catch (\Exception $e) {
        $io->error('Webpack build failed: ' . $e->getMessage());
        return false;
    }

    return true;
}
```

## Examples of Specialized Builders

### SCSS Builder

```php
public function detect(string $themePath): bool
{
    return file_exists($themePath . '/web/scss/styles.scss');
}

public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    try {
        $this->shell->execute('sass ' . $themePath . '/web/scss:' . $themePath . '/web/css --style compressed');
        return true;
    } catch (\Exception $e) {
        $io->error('SCSS compilation failed: ' . $e->getMessage());
        return false;
    }
}
```

### ReactJS Theme Builder

```php
public function detect(string $themePath): bool
{
    return file_exists($themePath . '/package.json') &&
           strpos(file_get_contents($themePath . '/package.json'), '"react"') !== false;
}

public function build(string $themePath, SymfonyStyle $io, OutputInterface $output, bool $isVerbose): bool
{
    try {
        $this->shell->execute('cd ' . $themePath . ' && npm run build');
        return true;
    } catch (\Exception $e) {
        $io->error('React build failed: ' . $e->getMessage());
        return false;
    }
}
```

## Troubleshooting Tips

1. **Make sure your `detect()` method is specific enough**: If it's too general, it might conflict with other builders.
2. **Use the `isVerbose` flag**: Provide more information when this flag is set to facilitate debugging.
3. **Catch all exceptions**: Ensure your builders are robust and return meaningful messages when errors occur.
4. **Test your builder thoroughly**: Make sure it works in different environments and with various theme configurations.

## Conclusion

With custom ThemeBuilders, you can completely adapt MageForge's build process to your project requirements. The modular architecture allows you to develop specific solutions for different theme types without modifying the core of MageForge.

By implementing the BuilderInterface and registering your builder in the DI system, your solution will be seamlessly integrated into the existing infrastructure and can be used with the familiar CLI commands.
