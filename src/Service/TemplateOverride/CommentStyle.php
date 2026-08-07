<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

/**
 * Selects the right comment syntax for a source-information header
 *
 * Different override targets need different comment styles so the header does
 * not leak into output (PHP/HTML) or break the file syntax (CSS/JS/XML).
 */
class CommentStyle
{
    public const PHP_BLOCK = 'php_block';
    public const HTML_BLOCK = 'html_block';
    public const C_BLOCK = 'c_block';
    public const XML_BLOCK = 'xml_block';
    public const SHELL_LINE = 'shell_line';
    public const NONE = 'none';

    /**
     * @param string $style One of the class constants
     */
    public function __construct(
        private readonly string $style,
    ) {
    }

    /**
     * Determine the safest comment style for a file path
     *
     * Returns a NONE style for binary or otherwise unsupported file types, which
     * tells the caller to skip the header entirely.
     *
     * @param string $filePath
     * @return self
     */
    public function fromFilePath(string $filePath): self
    {
        $extension = strtolower($this->extension($filePath));

        return new self(match ($extension) {
            'phtml', 'php' => self::PHP_BLOCK,
            'html', 'htm' => self::HTML_BLOCK,
            'xml', 'xhtml', 'svg' => self::XML_BLOCK,
            'css', 'js', 'less', 'scss', 'sass', 'ts' => self::C_BLOCK,
            'sh', 'bash', 'zsh', 'fish' => self::SHELL_LINE,
            default => self::NONE,
        });
    }

    /**
     * Check whether a comment style is available for the current file
     *
     * @return bool
     */
    public function isSupported(): bool
    {
        return $this->style !== self::NONE;
    }

    /**
     * Check whether this style is the PHP block style
     *
     * @return bool
     */
    public function isPhpBlock(): bool
    {
        return $this->style === self::PHP_BLOCK;
    }

    /**
     * Build the header using the style's comment syntax
     *
     * @param string[] $lines
     * @return string
     */
    public function wrap(array $lines): string
    {
        return match ($this->style) {
            self::PHP_BLOCK => $this->wrapPhpBlock($lines),
            self::HTML_BLOCK => $this->wrapHtmlBlock($lines),
            self::XML_BLOCK => $this->wrapXmlBlock($lines),
            self::C_BLOCK => $this->wrapCBlock($lines),
            self::SHELL_LINE => $this->wrapShellLines($lines),
            self::NONE => '',
            default => '',
        };
    }

    /**
     * Build the header as a full PHP open tag + PHPDoc block
     *
     * Exposed for callers that need the PHP wrapper even when injecting inside
     * an existing PHP file is not appropriate.
     *
     * @param string[] $lines
     * @return string
     */
    public function wrapPhpBlock(array $lines): string
    {
        if ($this->style !== self::PHP_BLOCK) {
            return $this->wrap($lines);
        }

        return "<?php\n/**\n" . $this->formatPhpDocLines($lines) . "\n */\n?>\n\n";
    }

    /**
     * Build the header without surrounding PHP tags when already inside PHP code
     *
     * For the PHP block style this returns only the PHPDoc comment; for all
     * other styles it falls back to wrap() so callers do not need to branch.
     *
     * @param string[] $lines
     * @return string
     */
    public function wrapPhpDoc(array $lines): string
    {
        if ($this->style !== self::PHP_BLOCK) {
            return $this->wrap($lines);
        }

        return "/**\n" . $this->formatPhpDocLines($lines) . "\n */\n\n";
    }

    /**
     * Prefix each PHPDoc line with " * " and keep blank lines as " *"
     *
     * @param string[] $lines
     * @return string
     */
    private function formatPhpDocLines(array $lines): string
    {
        $commented = array_map(
            static fn(string $line): string => $line === '' ? ' *' : ' * ' . $line,
            $lines,
        );

        return implode("\n", $commented);
    }

    /**
     * Wrap header lines in an HTML comment block
     *
     * @param string[] $lines
     * @return string
     */
    private function wrapHtmlBlock(array $lines): string
    {
        $commented = array_map(static fn(string $line): string => '  ' . $line, $lines);

        return "<!--\n" . implode("\n", $commented) . "\n-->\n\n";
    }

    /**
     * Wrap header lines in an XML comment block
     *
     * @param string[] $lines
     * @return string
     */
    private function wrapXmlBlock(array $lines): string
    {
        $commented = array_map(static fn(string $line): string => '  ' . $line, $lines);

        return "<!--\n" . implode("\n", $commented) . "\n-->\n\n";
    }

    /**
     * Wrap header lines in a C-style block comment
     *
     * @param string[] $lines
     * @return string
     */
    private function wrapCBlock(array $lines): string
    {
        $commented = array_map(static fn(string $line): string => ' * ' . $line, $lines);

        return "/**\n" . implode("\n", $commented) . "\n */\n\n";
    }

    /**
     * Prefix every header line with a shell-style "#" comment
     *
     * @param string[] $lines
     * @return string
     */
    private function wrapShellLines(array $lines): string
    {
        $commented = array_map(static fn(string $line): string => '# ' . $line, $lines);

        return implode("\n", $commented) . "\n\n";
    }

    /**
     * Extract the lower-cased file extension from a path
     *
     * @param string $filePath
     * @return string
     */
    private function extension(string $filePath): string
    {
        $lastDot = strrpos($filePath, '.');

        return $lastDot === false ? '' : substr($filePath, $lastDot + 1);
    }
}
