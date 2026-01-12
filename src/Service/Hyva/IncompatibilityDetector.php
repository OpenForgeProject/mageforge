<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;

/**
 * Service that detects Hyvä incompatibility patterns in JavaScript, XML, and PHTML files
 *
 * Uses pattern matching to identify RequireJS, Knockout.js, jQuery, and UI Components
 * usage that would be problematic in a Hyvä environment.
 */
class IncompatibilityDetector
{
    private const SEVERITY_CRITICAL = 'critical';
    private const SEVERITY_WARNING = 'warning';

    /**
     * Pattern definitions for detecting incompatibilities
     */
    private const INCOMPATIBLE_PATTERNS = [
        'js' => [
            [
                'pattern' => '/define\s*\(\s*\[/',
                'description' => 'RequireJS define() usage',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/require\s*\(\s*\[/',
                'description' => 'RequireJS require() usage',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/ko\.observable|ko\.observableArray|ko\.computed/',
                'description' => 'Knockout.js usage',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/\$\.ajax|jQuery\.ajax/',
                'description' => 'jQuery AJAX direct usage',
                'severity' => self::SEVERITY_WARNING,
            ],
            [
                'pattern' => '/(?:define|require)\s*\(\s*\[[^\]]*["\']mage\/[^"\']*["\']\s*[^\]]*\]/',
                'description' => 'Magento RequireJS module reference',
                'severity' => self::SEVERITY_CRITICAL,
            ],
        ],
        'xml' => [
            [
                'pattern' => '/<uiComponent/',
                'description' => 'UI Component usage',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/component="uiComponent"/',
                'description' => 'uiComponent reference',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/component="Magento_Ui\/js\//',
                'description' => 'Magento UI JS component',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/<referenceBlock\b[^>]*\bremove\s*=\s*"true"[^>]*>/s',
                'description' => 'Block removal (review for Hyvä compatibility)',
                'severity' => self::SEVERITY_WARNING,
            ],
        ],
        'phtml' => [
            [
                'pattern' => '/data-mage-init\s*=/',
                'description' => 'data-mage-init JavaScript initialization',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/x-magento-init/',
                'description' => 'x-magento-init JavaScript initialization',
                'severity' => self::SEVERITY_CRITICAL,
            ],
            [
                'pattern' => '/\$\([^)]*\)\s*\.(on|click|ready|change|keyup|keydown|submit|ajax|each|css|hide|show|addClass|removeClass|toggleClass|append|prepend|html|text|val|attr|prop|data|trigger|find|parent|children)\s*\(/',
                'description' => 'jQuery DOM manipulation',
                'severity' => self::SEVERITY_WARNING,
            ],
            [
                'pattern' => '/require\s*\(\s*\[/',
                'description' => 'RequireJS in template',
                'severity' => self::SEVERITY_CRITICAL,
            ],
        ],
    ];

    public function __construct(
        private readonly File $fileDriver
    ) {
    }

    /**
     * Detect incompatibilities in a file
     *
     * @return array Array of issues with keys: pattern, description, severity, line
     */
    public function detectInFile(string $filePath): array
    {
        if (!$this->fileDriver->isExists($filePath)) {
            return [];
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileType = $this->mapExtensionToType($extension);

        if (!isset(self::INCOMPATIBLE_PATTERNS[$fileType])) {
            return [];
        }

        try {
            $content = $this->fileDriver->fileGetContents($filePath);
            $lines = explode("\n", $content);

            return $this->scanContentForPatterns($lines, self::INCOMPATIBLE_PATTERNS[$fileType]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Map file extension to pattern type
     */
    private function mapExtensionToType(string $extension): string
    {
        return match ($extension) {
            'js' => 'js',
            'xml' => 'xml',
            'phtml' => 'phtml',
            default => 'unknown',
        };
    }

    /**
     * Scan content lines for pattern matches
     */
    private function scanContentForPatterns(array $lines, array $patterns): array
    {
        $issues = [];

        foreach ($patterns as $patternConfig) {
            foreach ($lines as $lineNumber => $lineContent) {
                if (preg_match($patternConfig['pattern'], $lineContent)) {
                    $issues[] = [
                        'description' => $patternConfig['description'],
                        'severity' => $patternConfig['severity'],
                        'line' => $lineNumber + 1, // Convert to 1-based line numbers
                        'pattern' => $patternConfig['pattern'],
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Get severity color for console output
     */
    public function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => 'red',
            self::SEVERITY_WARNING => 'yellow',
            default => 'white',
        };
    }

    /**
     * Get severity symbol
     */
    public function getSeveritySymbol(string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_CRITICAL => '✗',
            self::SEVERITY_WARNING => '⚠',
            default => 'ℹ',
        };
    }
}
