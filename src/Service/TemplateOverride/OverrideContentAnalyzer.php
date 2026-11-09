<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Service\TemplateOverride;

/**
 * Compares override files with their originals and reads the header written by TemplateCopier
 */
class OverrideContentAnalyzer
{
    private const PHP_BLOCK_PREFIX = '#^<\?php\n/\*\*(?:(?!\*/).)*?@mageforge-template-override.*?\*/\n\?>\n\n#s';
    private const PHP_DOC_INLINE = '#/\*\*(?:(?!\*/).)*?@mageforge-template-override.*?\*/\n\n#s';
    private const MARKUP_COMMENT = '#^<!--\n\s*MageForge Template Override.*?-->\n\n#s';
    private const C_BLOCK = '#^/\*\*\n \* MageForge Template Override.*?\*/\n\n#s';
    private const SHELL_LINES = '/^# MageForge Template Override\n(?:# [^\n]*\n)*\n/';

    /**
     * Check whether two files have the same content, ignoring the MageForge header and line endings
     *
     * @param string $override
     * @param string $original
     * @return bool
     */
    public function isIdentical(string $override, string $original): bool
    {
        return $this->normalize($this->stripHeader($override)) === $this->normalize($original);
    }

    /**
     * Remove the source header added by TemplateCopier
     *
     * @param string $content
     * @return string
     */
    public function stripHeader(string $content): string
    {
        $content = str_replace("\r\n", "\n", $content);

        foreach ([self::PHP_BLOCK_PREFIX, self::MARKUP_COMMENT, self::C_BLOCK, self::SHELL_LINES] as $pattern) {
            $stripped = preg_replace($pattern, '', $content, 1, $count);
            if ($count === 1 && is_string($stripped)) {
                return $stripped;
            }
        }

        $stripped = preg_replace(self::PHP_DOC_INLINE, '', $content, 1, $count);

        return $count === 1 && is_string($stripped) ? $stripped : $content;
    }

    /**
     * Read the source information from the header of an override file
     *
     * @param string $content
     * @return array{source: string|null, module: string|null, version: string|null}
     */
    public function parseHeader(string $content): array
    {
        $header = ['source' => null, 'module' => null, 'version' => null];

        $isMarked = str_contains($content, '@mageforge-template-override')
            || str_contains($content, 'MageForge Template Override');
        if (!$isMarked) {
            return $header;
        }

        $patterns = [
            'source' => '/(?:@source|Source:)[ \t]+(\S+)/',
            'module' => '/(?:@module|Source Module:|Override For:)[ \t]+([A-Za-z0-9]+_[A-Za-z0-9]+)/',
            'version' => '/(?:@module-version|Module-Version:)[ \t]+(\S+)/',
        ];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches) === 1) {
                $header[$key] = $matches[1];
            }
        }

        return $header;
    }

    /**
     * Normalise line endings and surrounding whitespace for comparison
     *
     * @param string $content
     * @return string
     */
    private function normalize(string $content): string
    {
        return trim(str_replace("\r\n", "\n", $content));
    }
}
