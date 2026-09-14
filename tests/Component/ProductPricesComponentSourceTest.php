<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Component;

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
        $permission = strpos($this->component, 'Access::canRead()');
        $query = strpos($this->component, 'StaffProductPriceReadService())->read');
        self::assertNotFalse($permission);
        self::assertNotFalse($query);
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
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $this->template);
        self::assertStringNotContainsString('data-', $this->template);
        self::assertStringNotContainsString('json_encode', $this->template);
    }
}
