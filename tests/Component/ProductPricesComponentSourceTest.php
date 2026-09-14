<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductPricesComponentSourceTest extends TestCase
{
    private string $component;
    private string $template;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2) . '/install/components/kk.pricewatch/product.prices';
        $this->component = (string) file_get_contents($root . '/class.php');
        $this->template = (string) file_get_contents($root . '/templates/.default/template.php');
    }

    public function testPermissionPrecedesReadAndCompositeFrameBelongsToTemplate(): void
    {
        $moduleLoad = strpos($this->component, "Loader::includeModule('kk.pricewatch')");
        $permission = strpos($this->component, 'Access::canRead()');
        $query = strpos($this->component, 'StaffProductPriceReadService())->read');
        self::assertNotFalse($moduleLoad);
        self::assertNotFalse($permission);
        self::assertNotFalse($query);
        self::assertLessThan($permission, $moduleLoad);
        self::assertLessThan($query, $permission);
        self::assertStringNotContainsString('createFrame', $this->component);
        self::assertStringContainsString("\$this->createFrame()->begin('')", $this->template);
        self::assertStringContainsString('setFrameMode(true)', $this->component);
        self::assertSame(1, substr_count($this->template, '$frame->end()'));
        self::assertStringNotContainsString('StartResultCache', $this->component);
    }

    public function testEveryValidProductRendersTemplateWithSafeDefaultResult(): void
    {
        $result = strpos($this->component, "'ACCESS_ALLOWED' => false");
        $moduleLoad = strpos($this->component, "Loader::includeModule('kk.pricewatch')");
        $permission = strpos($this->component, 'Access::canRead()');
        $query = strpos($this->component, 'StaffProductPriceReadService())->read');
        $template = strpos($this->component, '$this->includeComponentTemplate()');

        self::assertNotFalse($result);
        self::assertNotFalse($template);
        self::assertLessThan($moduleLoad, $result);
        self::assertLessThan($permission, $moduleLoad);
        self::assertLessThan($query, $permission);
        self::assertLessThan($template, $query);
        self::assertSame(1, substr_count($this->component, 'includeComponentTemplate'));
        self::assertStringContainsString("'ROWS' => []", $this->component);
        self::assertStringContainsString("Loader::includeModule('kk.pricewatch') && Access::canRead()", $this->component);
        self::assertStringContainsString(
            "if ((\$arResult['ACCESS_ALLOWED'] ?? false) === true && (\$arResult['ROWS'] ?? []) !== [])",
            $this->template
        );
    }

    public function testFrontendPathContainsNoCollectionHistoryOrWrites(): void
    {
        self::assertStringNotContainsString('PriceUpdateService', $this->component);
        self::assertStringNotContainsString('Collector', $this->component);
        self::assertStringNotContainsString('PriceHistoryTable', $this->component);
        self::assertDoesNotMatchRegularExpression('/::(add|update|delete)\s*\(/', $this->component);
    }

    public function testTemplateEscapesDataAndUsesSafeExactLink(): void
    {
        self::assertStringContainsString("HtmlFilter::encode(\$row['competitor_name'])", $this->template);
        self::assertStringContainsString("HtmlFilter::encode(\$row['exact_url'])", $this->template);
        self::assertStringContainsString("if (\$row['is_url_safe'])", $this->template);
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $this->template);
        self::assertStringNotContainsString('data-', $this->template);
        self::assertStringNotContainsString('json_encode', $this->template);
    }

    public function testDeniedTemplateRenderIsCompletelyEmptyAndDoesNotPoisonAuthorizedRender(): void
    {
        $url = 'https://secret.example.test/item?competitor=Hidden';
        self::assertSame('', $this->renderFixture($url, 'denied'));

        $authorized = $this->renderFixture($url, 'allowed');
        self::assertStringContainsString('Test', $authorized);
        self::assertStringContainsString('competitor=Hidden', $authorized);

        self::assertSame('', $this->renderFixture($url, 'denied'));
    }

    #[DataProvider('renderedUrlCases')]
    public function testOnlyAbsoluteHttpUrlsBecomeClickable(string $url, bool $clickable): void
    {
        $html = $this->renderFixture($url, 'allowed');

        self::assertSame($clickable, str_contains($html, '<a href='));
        if ($clickable) {
            self::assertStringContainsString('href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $html);
        } else {
            self::assertStringNotContainsString($url, $html);
        }
    }

    public static function renderedUrlCases(): array
    {
        return [
            'https exact query' => ['https://example.test/item?a=1&b=%2F', true],
            'http' => ['http://example.test/item', true],
            'javascript' => ['javascript:alert(1)', false],
            'data' => ['data:text/html,test', false],
        ];
    }

    private function renderFixture(string $url, string $access): string
    {
        $fixture = __DIR__ . '/fixtures/render_product_prices_template.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' '
            . escapeshellarg($url) . ' ' . escapeshellarg($access);
        exec($command, $lines, $exitCode);
        self::assertSame(0, $exitCode);

        return implode("\n", $lines);
    }
}
