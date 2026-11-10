<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\TemplateOverride;

use OpenForgeProject\MageForge\Service\TemplateOverride\OverrideContentAnalyzer;
use PHPUnit\Framework\TestCase;

class OverrideContentAnalyzerTest extends TestCase
{
    /**
     * @var OverrideContentAnalyzer
     */
    private OverrideContentAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new OverrideContentAnalyzer();
    }

    public function testPhpBlockHeaderIsIgnoredWhenComparing(): void
    {
        $original = "<div>Hello</div>\n";
        $override = "<?php\n/**\n * @mageforge-template-override\n * @source vendor/a/b.phtml\n */\n?>\n\n" . $original;

        $this->assertTrue($this->analyzer->isIdentical($override, $original));
    }

    public function testInjectedPhpDocHeaderIsIgnoredWhenComparing(): void
    {
        $override = "<?php\n/**\n * @mageforge-template-override\n */\n\ndeclare(strict_types=1);\n";

        $this->assertTrue($this->analyzer->isIdentical($override, "<?php\ndeclare(strict_types=1);\n"));
    }

    public function testHtmlHeaderAndLineEndingsAreIgnored(): void
    {
        $override = "<!--\n  MageForge Template Override\n  Source: a/b.html\n-->\n\n<p>x</p>\n";

        $this->assertTrue($this->analyzer->isIdentical($override, "<p>x</p>\r\n"));
    }

    public function testTrailingWhitespaceIsSignificant(): void
    {
        $this->assertFalse($this->analyzer->isIdentical("<?= \$a ?> ", '<?= $a ?>'));
        $this->assertFalse($this->analyzer->isIdentical('<i>a</i> <?= $b ?>', '<i>a</i><?= $b ?>'));
    }

    public function testHeaderMarkerDoesNotConsumeContentBeyondItsClosingDelimiter(): void
    {
        $override = "<!--\n  MageForge Template Override\n<p>custom</p>\n-->\n\nrest";

        $this->assertSame($override, $this->analyzer->stripHeader($override));
        $this->assertFalse($this->analyzer->isIdentical($override, 'rest'));
    }

    public function testChangedContentIsNotIdentical(): void
    {
        $override = "<?php\n/**\n * @mageforge-template-override\n */\n?>\n\n<div>Changed</div>";

        $this->assertFalse($this->analyzer->isIdentical($override, '<div>Hello</div>'));
    }

    public function testParseHeaderReadsSourceModuleAndVersion(): void
    {
        $content = "<?php\n/**\n * @mageforge-template-override\n * @source vendor/m/a.phtml\n"
            . " * @module Magento_Catalog\n * @module-version 104.0.1\n */\n?>\n";

        $this->assertSame(
            ['source' => 'vendor/m/a.phtml', 'module' => 'Magento_Catalog', 'version' => '104.0.1'],
            $this->analyzer->parseHeader($content),
        );
    }

    public function testParseHeaderSupportsPlainTextStyle(): void
    {
        $content = "<!--\n  MageForge Template Override\n  Source: vendor/m/a.html\n"
            . "  Source Module: Magento_Email\n  Source Module-Version: 2.0.0\n-->\n";

        $this->assertSame(
            ['source' => 'vendor/m/a.html', 'module' => 'Magento_Email', 'version' => '2.0.0'],
            $this->analyzer->parseHeader($content),
        );
    }

    public function testParseHeaderWithoutMarkerReturnsNulls(): void
    {
        $this->assertSame(
            ['source' => null, 'module' => null, 'version' => null],
            $this->analyzer->parseHeader('<p>@source nothing</p>'),
        );
    }

    public function testIgnoresTheHeaderOfTheOriginalToo(): void
    {
        $analyzer = new OverrideContentAnalyzer();
        $header = "<!--\n  MageForge Template Override\n  Source: a\n-->\n\n";

        $this->assertTrue($analyzer->isIdentical($header . '<p>x</p>', $header . '<p>x</p>'));
        $this->assertTrue($analyzer->isIdentical('<p>x</p>', $header . '<p>x</p>'));
    }

    public function testComparesBinaryContentByteForByte(): void
    {
        $analyzer = new OverrideContentAnalyzer();

        $this->assertTrue($analyzer->isIdentical("BM\0\x0a\0", "BM\0\x0a\0"));
        $this->assertFalse($analyzer->isIdentical("BM\0\x0a\0", "BM\0\x0d\0"));
        $this->assertFalse($analyzer->isIdentical("BM\0\x0a\0\n", "BM\0\x0a\0"));
        $this->assertFalse($analyzer->isIdentical("BM\0\r\n", "BM\0\n"));
    }
}
