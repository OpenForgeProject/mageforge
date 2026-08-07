<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use OpenForgeProject\MageForge\Service\TemplateOverride\CommentStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommentStyleTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function filePathProvider(): array
    {
        return [
            'phtml file' => ['widget.phtml', CommentStyle::PHP_BLOCK],
            'php file' => ['helper.php', CommentStyle::PHP_BLOCK],
            'html file' => ['order/new.html', CommentStyle::HTML_BLOCK],
            'htm file' => ['order/new.htm', CommentStyle::HTML_BLOCK],
            'xml layout' => ['layout/default.xml', CommentStyle::XML_BLOCK],
            'svg icon' => ['images/icon.svg', CommentStyle::XML_BLOCK],
            'css file' => ['css/source/_module.css', CommentStyle::C_BLOCK],
            'less file' => ['css/source/_module.less', CommentStyle::C_BLOCK],
            'scss file' => ['css/source/_module.scss', CommentStyle::C_BLOCK],
            'sass file' => ['css/source/_module.sass', CommentStyle::C_BLOCK],
            'js file' => ['js/widget.js', CommentStyle::C_BLOCK],
            'ts file' => ['js/widget.ts', CommentStyle::C_BLOCK],
            'shell script' => ['setup.sh', CommentStyle::SHELL_LINE],
            'bash script' => ['setup.bash', CommentStyle::SHELL_LINE],
            'zsh script' => ['setup.zsh', CommentStyle::SHELL_LINE],
            'fish script' => ['setup.fish', CommentStyle::SHELL_LINE],
            'png image' => ['logo.png', CommentStyle::NONE],
            'woff font' => ['font.woff', CommentStyle::NONE],
            'no extension' => ['Makefile', CommentStyle::NONE],
        ];
    }

    #[DataProvider('filePathProvider')]
    public function testMapsFilePathsToCorrectStyle(string $path, string $expected): void
    {
        $style = (new CommentStyle(CommentStyle::NONE))->fromFilePath($path);

        $this->assertSame($expected, $this->styleName($style));
    }

    public function testPhpBlockWrapsWithOpenTagAndDocBlock(): void
    {
        $header = (new CommentStyle(CommentStyle::PHP_BLOCK))->wrap(['Line 1', 'Line 2']);

        $this->assertSame(
            "<?php\n/**\n * Line 1\n * Line 2\n */\n?>\n\n",
            $header,
        );
    }

    public function testHtmlBlockWrapsInHtmlComment(): void
    {
        $header = (new CommentStyle(CommentStyle::HTML_BLOCK))->wrap(['Line 1', 'Line 2']);

        $this->assertSame(
            "<!--\n  Line 1\n  Line 2\n-->\n\n",
            $header,
        );
    }

    public function testXmlBlockWrapsInXmlComment(): void
    {
        $header = (new CommentStyle(CommentStyle::XML_BLOCK))->wrap(['Line 1', 'Line 2']);

        $this->assertStringStartsWith('<!--', $header);
        $this->assertStringEndsWith("-->\n\n", $header);
    }

    public function testCBlockWrapsInDocBlock(): void
    {
        $header = (new CommentStyle(CommentStyle::C_BLOCK))->wrap(['Line 1', 'Line 2']);

        $this->assertSame(
            "/**\n * Line 1\n * Line 2\n */\n\n",
            $header,
        );
    }

    public function testShellBlockPrefixesLines(): void
    {
        $header = (new CommentStyle(CommentStyle::SHELL_LINE))->wrap(['Line 1', 'Line 2']);

        $this->assertSame(
            "# Line 1\n# Line 2\n\n",
            $header,
        );
    }

    public function testNoneReturnsEmptyString(): void
    {
        $this->assertSame('', (new CommentStyle(CommentStyle::NONE))->wrap(['Line 1']));
    }

    public function testUnsupportedStyleSkipsHeader(): void
    {
        $style = (new CommentStyle(CommentStyle::NONE))->fromFilePath('logo.png');

        $this->assertFalse($style->isSupported());
        $this->assertSame('', $style->wrap(['Line 1']));
    }

    private function styleName(CommentStyle $style): string
    {
        $styleValue = $this->extractStyleValue($style);
        $reflection = new \ReflectionClass($style);
        foreach ($reflection->getConstants() as $value) {
            if ($value === $styleValue) {
                return $value;
            }
        }

        return CommentStyle::NONE;
    }

    private function extractStyleValue(CommentStyle $style): string
    {
        $reflection = new \ReflectionClass($style);
        $property = $reflection->getProperty('style');
        $value = $property->getValue($style);

        return is_string($value) ? $value : CommentStyle::NONE;
    }
}
