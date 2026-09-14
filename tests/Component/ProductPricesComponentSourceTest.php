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

    public function testPermissionPrecedesReadAndThereIsNoSharedOrCompositeCache(): void
    {
        $moduleLoad = strpos($this->component, "Loader::includeModule('kk.pricewatch')");
        $permission = strpos($this->component, 'Access::canRead()');
        $query = strpos($this->component, 'StaffProductPriceReadService())->read');
        self::assertNotFalse($moduleLoad);
        self::assertNotFalse($permission);
        self::assertNotFalse($query);
        self::assertLessThan($permission, $moduleLoad);
        self::assertLessThan($query, $permission);
        $frame = strpos($this->component, "createFrame()->begin('')");
        self::assertNotFalse($frame);
        self::assertLessThan($permission, $frame);
        self::assertStringContainsString('setFrameMode(true)', $this->component);
        self::assertGreaterThanOrEqual(3, substr_count($this->component, '$frame->end()'));
        self::assertStringNotContainsString('StartResultCache', $this->component);
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

    #[DataProvider('renderedUrlCases')]
    public function testOnlyAbsoluteHttpUrlsBecomeClickable(string $url, bool $clickable): void
    {
        $fixture = __DIR__ . '/fixtures/render_product_prices_template.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' ' . escapeshellarg($url);
        exec($command, $lines, $exitCode);
        self::assertSame(0, $exitCode);
        $html = implode("\n", $lines);

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
}
