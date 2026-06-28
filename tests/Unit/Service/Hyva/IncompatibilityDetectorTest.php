<?php

declare(strict_types=1);

namespace OpenForgeProject\MageForge\Test\Unit\Service\Hyva;

use Magento\Framework\Filesystem\Driver\File;
use OpenForgeProject\MageForge\Service\Hyva\IncompatibilityDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IncompatibilityDetectorTest extends TestCase
{
    private File&MockObject $fileMock;
    private IncompatibilityDetector $detector;

    protected function setUp(): void
    {
        $this->fileMock = $this->createMock(File::class);
        $this->fileMock->method('isExists')->willReturn(true);
        $this->detector = new IncompatibilityDetector($this->fileMock);
    }

    // -------------------------------------------------------------------------
    // JS patterns
    // -------------------------------------------------------------------------

    #[DataProvider('incompatibleJsProvider')]
    public function testDetectsIncompatibleJsPattern(
        string $content,
        string $expectedDescription,
        string $expectedSeverity,
    ): void {
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('test.js');
        $this->assertIssueFound($issues, $expectedDescription, $expectedSeverity);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function incompatibleJsProvider(): array
    {
        return [
            'requirejs define' => [
                "define(['jquery', 'ko'], function(\$, ko) { return {}; });",
                'RequireJS define() usage',
                'critical',
            ],
            'requirejs require' => [
                "require(['Magento_Ui/js/modal/modal'], function(modal) { modal.init(); });",
                'RequireJS require() usage',
                'critical',
            ],
            'ko observable' => [
                'var qty = ko.observable(1);',
                'Knockout.js usage',
                'critical',
            ],
            'ko observableArray' => [
                'this.items = ko.observableArray([]);',
                'Knockout.js usage',
                'critical',
            ],
            'ko computed' => [
                'this.total = ko.computed(function() { return this.qty() * this.price(); }, this);',
                'Knockout.js usage',
                'critical',
            ],
            'ko pureComputed' => [
                'this.label = ko.pureComputed(() => this.qty() + " items");',
                'Knockout.js usage',
                'critical',
            ],
            'ko applyBindings' => [
                'ko.applyBindings(new ViewModel(), document.getElementById("app"));',
                'Knockout.js usage',
                'critical',
            ],
            'ko components register' => [
                'ko.components.register("my-widget", { viewModel: ViewModel, template: tmpl });',
                'Knockout.js usage',
                'critical',
            ],
            'ko bindingHandlers' => [
                'ko.bindingHandlers.myBinding = { init: function(element) {} };',
                'Knockout.js usage',
                'critical',
            ],
            'jquery ajax shorthand' => [
                '$.ajax({ url: "/rest/V1/products", method: "GET" });',
                'jQuery AJAX direct usage',
                'warning',
            ],
            'jquery ajax full name' => [
                'jQuery.ajax({ url: "/endpoint", data: payload });',
                'jQuery AJAX direct usage',
                'warning',
            ],
            'mage requirejs module' => [
                "define(['mage/url', 'jquery'], function(urlBuilder, \$) { return {}; });",
                'Magento RequireJS module reference',
                'critical',
            ],
        ];
    }

    public function testDoesNotFlagCleanAlpineJs(): void
    {
        $cleanJs = <<<'JS'
        export default function cartComponent() {
            return {
                count: 0,
                async addToCart(productId) {
                    const response = await fetch('/checkout/cart/add', {
                        method: 'POST',
                        body: JSON.stringify({ product: productId }),
                    });
                    this.count = await response.json();
                },
            };
        }
        JS;

        $this->fileMock->method('fileGetContents')->willReturn($cleanJs);
        $issues = $this->detector->detectInFile('alpine-component.js');
        $this->assertEmpty($issues, 'Clean Alpine.js / fetch code must not trigger any issues');
    }

    public function testDoesNotFlagWordBoundaryFalsePositive(): void
    {
        $content = 'var mykoObserver = function() { return true; };';
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('test.js');
        $this->assertEmpty($issues, '"myko" prefix must not match the Knockout.js word-boundary pattern');
    }

    public function testDoesNotFlagTrailingWordBoundaryFalsePositives(): void
    {
        // ko.computedSomething / ko.componentsX must NOT match (trailing boundary)
        $content = "var a = ko.computedSomething();\nvar b = ko.componentsX.register();\n";
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('test.js');
        $this->assertEmpty($issues, 'ko.computedSomething and ko.componentsX must not trigger KO pattern');
    }

    public function testDoesNotFlagDataBindingsFalsePositive(): void
    {
        // data-bindings= must NOT match, only data-bind= should
        $content = '<div data-bindings="text: name">';
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('template.phtml');
        $this->assertEmpty($issues, 'data-bindings= must not match the data-bind pattern');
    }

    // -------------------------------------------------------------------------
    // XML patterns
    // -------------------------------------------------------------------------

    #[DataProvider('incompatibleXmlProvider')]
    public function testDetectsIncompatibleXmlPattern(
        string $content,
        string $expectedDescription,
        string $expectedSeverity,
    ): void {
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('default.xml');
        $this->assertIssueFound($issues, $expectedDescription, $expectedSeverity);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function incompatibleXmlProvider(): array
    {
        return [
            'uiComponent tag' => [
                '<uiComponent name="product_listing"/>',
                'UI Component usage',
                'critical',
            ],
            'component uiComponent attribute' => [
                '<block name="block" component="uiComponent" template="template.html">',
                'uiComponent reference',
                'critical',
            ],
            'magento ui js component' => [
                'component="Magento_Ui/js/form/form"',
                'Magento UI JS component',
                'critical',
            ],
            'referenceBlock remove' => [
                '<referenceBlock name="catalog.product.related" remove="true">',
                'Block removal (review for Hyvä compatibility)',
                'warning',
            ],
        ];
    }

    public function testDoesNotFlagCleanLayoutXml(): void
    {
        $cleanXml = <<<'XML'
        <?xml version="1.0"?>
        <page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
              xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
            <body>
                <referenceContainer name="content">
                    <block class="Magento\Catalog\Block\Product\View"
                           name="product.info.main"
                           template="Magento_Catalog::product/view/form.phtml"/>
                </referenceContainer>
            </body>
        </page>
        XML;

        $this->fileMock->method('fileGetContents')->willReturn($cleanXml);
        $issues = $this->detector->detectInFile('catalog_product_view.xml');
        $this->assertEmpty($issues, 'Clean layout XML must not trigger issues');
    }

    // -------------------------------------------------------------------------
    // PHTML patterns
    // -------------------------------------------------------------------------

    #[DataProvider('incompatiblePhtmlProvider')]
    public function testDetectsIncompatiblePhtmlPattern(
        string $content,
        string $expectedDescription,
        string $expectedSeverity,
    ): void {
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('template.phtml');
        $this->assertIssueFound($issues, $expectedDescription, $expectedSeverity);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function incompatiblePhtmlProvider(): array
    {
        return [
            'data-mage-init' => [
                '<div data-mage-init=\'{"slider": {"autoplay": true}}\'>',
                'data-mage-init JavaScript initialization',
                'critical',
            ],
            'data-bind' => [
                '<span data-bind="text: productName, visible: isAvailable">',
                'Knockout.js data-bind attribute',
                'critical',
            ],
            'x-magento-init' => [
                '<script type="text/x-magento-init">{"*":{"Vendor_Module/js/widget":{}}}</script>',
                'x-magento-init JavaScript initialization',
                'critical',
            ],
            'jquery dom manipulation' => [
                "$('.price-box').show();",
                'jQuery DOM manipulation',
                'warning',
            ],
            'requirejs in template' => [
                "require(['jquery'], function(\$) { \$('.widget').init(); });",
                'RequireJS in template',
                'critical',
            ],
            'ko virtual element' => [
                "<!-- ko foreach: { data: items, as: 'item' } -->",
                'Knockout.js virtual element binding',
                'critical',
            ],
            'ko virtual element no space' => [
                '<!--ko if: isVisible -->',
                'Knockout.js virtual element binding',
                'critical',
            ],
        ];
    }

    public function testDoesNotFlagCleanHyvaPhtml(): void
    {
        $cleanPhtml = <<<'PHTML'
        <?php /** @var \Magento\Catalog\Block\Product\View $block */ ?>
        <div x-data="productView()" x-init="init()">
            <span x-text="productName"></span>
            <button @click="addToCart()" :disabled="loading">
                <?= $block->escapeHtml(__('Add to Cart')) ?>
            </button>
        </div>
        <script>
        function productView() {
            return {
                productName: '<?= $block->escapeJs($block->getProduct()->getName()) ?>',
                loading: false,
                async addToCart() {
                    this.loading = true;
                    await fetch('/checkout/cart/add', { method: 'POST' });
                    this.loading = false;
                },
            };
        }
        </script>
        PHTML;

        $this->fileMock->method('fileGetContents')->willReturn($cleanPhtml);
        $issues = $this->detector->detectInFile('product-view.phtml');
        $this->assertEmpty($issues, 'Clean Hyvä/Alpine.js template must not trigger any issues');
    }

    // -------------------------------------------------------------------------
    // HTML template patterns (Knockout component templates)
    // -------------------------------------------------------------------------

    #[DataProvider('incompatibleHtmlProvider')]
    public function testDetectsIncompatibleHtmlPattern(
        string $content,
        string $expectedDescription,
    ): void {
        $this->fileMock->method('fileGetContents')->willReturn($content);
        $issues = $this->detector->detectInFile('view/frontend/web/template/listing.html');
        $this->assertIssueFound($issues, $expectedDescription, 'critical');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function incompatibleHtmlProvider(): array
    {
        return [
            'data-bind in html template' => [
                '<div data-bind="foreach: items"><span data-bind="text: name"></span></div>',
                'Knockout.js data-bind attribute',
            ],
            'ko virtual element in html' => [
                '<!-- ko if: isVisible --><p>Hello</p><!-- /ko -->',
                'Knockout.js virtual element binding',
            ],
            'x-magento-init in html' => [
                '<script type="text/x-magento-init">{"*":{}}</script>',
                'x-magento-init JavaScript initialization',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function testReturnsEmptyArrayWhenFileNotExists(): void
    {
        $this->fileMock->method('isExists')->willReturn(false);
        $issues = $this->detector->detectInFile('nonexistent.js');
        $this->assertEmpty($issues);
    }

    public function testReturnsEmptyArrayForUnknownExtension(): void
    {
        $this->fileMock->method('fileGetContents')->willReturn("define(['jquery'], function() {});");
        $issues = $this->detector->detectInFile('script.coffee');
        $this->assertEmpty($issues, 'Unknown file extensions must be ignored');
    }

    public function testReturnsEmptyArrayOnFileReadError(): void
    {
        $this->fileMock->method('fileGetContents')->willThrowException(new \RuntimeException('Read error'));
        $issues = $this->detector->detectInFile('test.js');
        $this->assertEmpty($issues, 'File read errors must be handled gracefully');
    }

    public function testReportsCorrectLineNumbers(): void
    {
        $content = "const x = 1;\nconst y = 2;\nko.applyBindings(viewModel);\nconst z = 3;";
        $this->fileMock->method('fileGetContents')->willReturn($content);

        $issues = $this->detector->detectInFile('test.js');

        $this->assertNotEmpty($issues);
        $this->assertSame(3, $issues[0]['line'], 'Line number must be 1-based');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $issues
     */
    private function assertIssueFound(array $issues, string $description, string $severity): void
    {
        $this->assertNotEmpty(
            $issues,
            sprintf('Expected issue "%s" but no issues were detected at all', $description),
        );

        $found = array_filter(
            $issues,
            static fn(array $issue): bool => $issue['description'] === $description,
        );

        $this->assertNotEmpty(
            $found,
            sprintf(
                'Expected issue "%s" not found. Detected: %s',
                $description,
                implode(', ', array_map('strval', array_column($issues, 'description'))),
            ),
        );

        $issue = array_values($found)[0];
        $this->assertSame(
            $severity,
            (string) $issue['severity'],
            sprintf('Issue "%s" expected severity "%s" but got "%s"', $description, $severity, $issue['severity']),
        );
    }
}
