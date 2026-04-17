<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap file for MageForge unit tests.
 *
 * Loads the PHPUnit/Symfony autoloader, registers Magento stub classes,
 * and sets up PSR-4 autoloading for the project source.
 */

// Load PHPUnit and Symfony Console autoloader.
// In a full Magento dev environment this would come from vendor/autoload.php.
// For isolated unit testing we use a standalone installation.
$autoloaderCandidates = [
    // Project vendor (available when composer install --dev has run)
    __DIR__ . '/../vendor/autoload.php',
    // Standalone PHPUnit installation used in CI / sandbox
    '/tmp/phpunit-installer/vendor/autoload.php',
];

$autoloaderLoaded = false;
foreach ($autoloaderCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $autoloaderLoaded = true;
        break;
    }
}

if (!$autoloaderLoaded) {
    echo "ERROR: Could not find a PHPUnit autoloader. Run composer install --dev.\n";
    exit(1);
}

// Autoloader for Magento Framework stubs.
spl_autoload_register(function (string $class): void {
    $stubsDir = __DIR__ . '/Stubs/';
    $file = $stubsDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// PSR-4 autoloader for the project source (OpenForgeProject\MageForge\).
spl_autoload_register(function (string $class): void {
    $prefix = 'OpenForgeProject\\MageForge\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
