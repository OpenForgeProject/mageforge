<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Tests\Unit\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\IncompatibilityDetector;
use PHPUnit\Framework\TestCase;

class IncompatibilityDetectorTest extends TestCase
{
    private File $fileDriver;
    private IncompatibilityDetector $detector;

    protected function setUp(): void
    {
        $this->fileDriver = $this->createMock(File::class);
        $this->detector = new IncompatibilityDetector($this->fileDriver);
    }

    // ---- detectInFile: edge cases ----

    public function testDetectInFileReturnsEmptyArrayForNonExistentFile(): void
    {
        $this->fileDriver->method('isExists')->willReturn(false);

        $result = $this->detector->detectInFile('/nonexistent/file.js');

        $this->assertSame([], $result);
    }

    public function testDetectInFileReturnsEmptyArrayForUnsupportedExtension(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('some content');

        $result = $this->detector->detectInFile('/some/file.css');

        $this->assertSame([], $result);
    }

    public function testDetectInFileReturnsEmptyArrayForFileWithNoExtension(): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn('#!/bin/bash');

        $result = $this->detector->detectInFile('/some/Makefile');

        $this->assertSame([], $result);
    }

    // ---- JavaScript pattern detection ----

    public function testDetectInFileDetectsRequireJsDefineInJsFile(): void
    {
        $content = "define(['jquery', 'underscore'], function($, _) { });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/web/js/widget.js');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'RequireJS define() usage', 'critical');
    }

    public function testDetectInFileDetectsRequireJsRequireInJsFile(): void
    {
        $content = "require(['jquery'], function($) { $('body').addClass('active'); });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/web/js/init.js');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'RequireJS require() usage', 'critical');
    }

    public function testDetectInFileDetectsKnockoutJsUsageInJsFile(): void
    {
        $content = "var items = ko.observableArray([]);";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/web/js/model.js');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'Knockout.js usage', 'critical');
    }

    public function testDetectInFileDetectsKnockoutObservableInJsFile(): void
    {
        $content = "var name = ko.observable('test');";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/script.js');

        $this->assertIssueExists($result, 'Knockout.js usage', 'critical');
    }

    public function testDetectInFileDetectsKnockoutComputedInJsFile(): void
    {
        $content = "var full = ko.computed(function() { return first() + ' ' + last(); });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/script.js');

        $this->assertIssueExists($result, 'Knockout.js usage', 'critical');
    }

    public function testDetectInFileDetectsJqueryAjaxAsWarning(): void
    {
        $content = "$.ajax({ url: '/api/endpoint', method: 'POST' });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/web/js/ajax.js');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'jQuery AJAX direct usage', 'warning');
    }

    public function testDetectInFileDetectsJqueryAjaxWithJQueryNamespaceAsWarning(): void
    {
        $content = "jQuery.ajax({ url: '/endpoint' });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/script.js');

        $this->assertIssueExists($result, 'jQuery AJAX direct usage', 'warning');
    }

    public function testDetectInFileDetectsMageModuleReferenceInJsFile(): void
    {
        $content = "require(['mage/storage'], function(storage) { storage.post('/api'); });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/script.js');

        $this->assertIssueExists($result, 'Magento RequireJS module reference', 'critical');
    }

    public function testDetectInFileReturnsEmptyForCleanJsFile(): void
    {
        $content = "const greeting = 'hello';\nconst add = (a, b) => a + b;";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/utils.js');

        $this->assertSame([], $result);
    }

    // ---- XML pattern detection ----

    public function testDetectInFileDetectsUiComponentInXmlFile(): void
    {
        $content = '<uiComponent name="customer_account_navigation"/>';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/layout/default.xml');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'UI Component usage', 'critical');
    }

    public function testDetectInFileDetectsUiComponentReferenceInXmlFile(): void
    {
        $content = '<block component="uiComponent" name="checkout"/>';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/layout.xml');

        $this->assertIssueExists($result, 'uiComponent reference', 'critical');
    }

    public function testDetectInFileDetectsMagentoUiJsComponentInXmlFile(): void
    {
        $content = '<item name="component" xsi:type="string">component="Magento_Ui/js/form/form"</item>';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/layout.xml');

        $this->assertIssueExists($result, 'Magento UI JS component', 'critical');
    }

    public function testDetectInFileDetectsBlockRemovalAsWarning(): void
    {
        $content = '<referenceBlock name="product.info.price" remove="true">';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/layout/catalog_product_view.xml');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'Block removal (review for Hyvä compatibility)', 'warning');
    }

    public function testDetectInFileReturnsEmptyForCleanXmlFile(): void
    {
        $content = '<layout xmlns:xsi="..."><referenceContainer name="content"><block name="test"/></referenceContainer></layout>';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/layout.xml');

        $this->assertSame([], $result);
    }

    // ---- PHTML pattern detection ----

    public function testDetectInFileDetectsDataMageInitInPhtmlFile(): void
    {
        $content = '<div data-mage-init=\'{"Magento_Ui/js/modal/modal": {}}\'>Content</div>';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/templates/widget.phtml');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'data-mage-init JavaScript initialization', 'critical');
    }

    public function testDetectInFileDetectsXMagentoInitInPhtmlFile(): void
    {
        $content = '<script type="text/x-magento-init">{"*":{"Magento_Ui/js/core/app":{}}}</script>';
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/view/frontend/templates/init.phtml');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'x-magento-init JavaScript initialization', 'critical');
    }

    public function testDetectInFileDetectsJqueryDomManipulationInPhtmlAsWarning(): void
    {
        $content = "<?php \$this->getBlockHtml('header') ?>\n<script>$('#id').addClass('active');</script>";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/templates/block.phtml');

        $this->assertNotEmpty($result);
        $this->assertIssueExists($result, 'jQuery DOM manipulation', 'warning');
    }

    public function testDetectInFileDetectsRequireJsInPhtmlFile(): void
    {
        $content = "require(['jquery', 'mage/loader'], function($) { /* ... */ });";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/templates/template.phtml');

        $this->assertIssueExists($result, 'RequireJS in template', 'critical');
    }

    public function testDetectInFileReturnsEmptyForCleanPhtmlFile(): void
    {
        $content = "<?php /** @var \$block \\Magento\\Framework\\View\\Element\\Template */ ?>\n<div><?= \$block->escapeHtml(\$name) ?></div>";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/module/templates/clean.phtml');

        $this->assertSame([], $result);
    }

    // ---- Line number reporting ----

    public function testDetectInFileReportsCorrectLineNumbers(): void
    {
        $content = "// Line 1\n// Line 2\ndefine(['jquery'], function($) {}); // Line 3";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/widget.js');

        $this->assertNotEmpty($result);
        $foundLine3 = false;
        foreach ($result as $issue) {
            if ($issue['line'] === 3) {
                $foundLine3 = true;
            }
        }
        $this->assertTrue($foundLine3, 'Expected issue on line 3');
    }

    public function testDetectInFileReportsMultipleIssuesFromSameFile(): void
    {
        $content = "define(['jquery'], function($) {\n  var obs = ko.observable();\n});";
        $this->configureFileWithContent($content);

        $result = $this->detector->detectInFile('/path/complex.js');

        $this->assertGreaterThanOrEqual(2, count($result));
    }

    // ---- getSeverityColor ----

    public function testGetSeverityColorReturnsCriticalColor(): void
    {
        $this->assertSame('red', $this->detector->getSeverityColor('critical'));
    }

    public function testGetSeverityColorReturnsWarningColor(): void
    {
        $this->assertSame('yellow', $this->detector->getSeverityColor('warning'));
    }

    public function testGetSeverityColorReturnsDefaultColorForUnknownSeverity(): void
    {
        $this->assertSame('white', $this->detector->getSeverityColor('info'));
        $this->assertSame('white', $this->detector->getSeverityColor(''));
    }

    // ---- getSeveritySymbol ----

    public function testGetSeveritySymbolReturnsCriticalSymbol(): void
    {
        $this->assertSame('✗', $this->detector->getSeveritySymbol('critical'));
    }

    public function testGetSeveritySymbolReturnsWarningSymbol(): void
    {
        $this->assertSame('⚠', $this->detector->getSeveritySymbol('warning'));
    }

    public function testGetSeveritySymbolReturnsDefaultSymbolForUnknownSeverity(): void
    {
        $this->assertSame('ℹ', $this->detector->getSeveritySymbol('info'));
        $this->assertSame('ℹ', $this->detector->getSeveritySymbol(''));
    }

    // ---- Helper methods ----

    private function configureFileWithContent(string $content): void
    {
        $this->fileDriver->method('isExists')->willReturn(true);
        $this->fileDriver->method('fileGetContents')->willReturn($content);
    }

    /**
     * @param array<int, array<string, mixed>> $issues
     */
    private function assertIssueExists(array $issues, string $description, string $severity): void
    {
        foreach ($issues as $issue) {
            if ($issue['description'] === $description && $issue['severity'] === $severity) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail(
            sprintf('Expected issue "%s" with severity "%s" not found.', $description, $severity)
        );
    }
}
