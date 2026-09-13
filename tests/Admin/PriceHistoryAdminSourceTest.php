<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use PHPUnit\Framework\TestCase;

final class PriceHistoryAdminSourceTest extends TestCase
{
    public function testHistoryPageIsReadOnlyFilteredEscapedAndPaginated(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root . '/admin/price_history.php');
        self::assertStringContainsString('Access::canRead()', $source);
        self::assertStringNotContainsString('Access::canWrite()', $source);
        self::assertDoesNotMatchRegularExpression('/::(?:add|update|delete)\s*\(/i', $source);
        self::assertStringContainsString("get('PRODUCT_ID')", $source);
        self::assertStringContainsString("get('PRODUCT_COMPETITOR_ID')", $source);
        self::assertStringContainsString("'order' => ['COLLECTED_AT' => 'DESC', 'ID' => 'DESC']", $source);
        self::assertStringContainsString('getPageNavigation(', $source);
        self::assertStringContainsString('htmlspecialcharsbx(', $source);
        self::assertStringContainsString('PriceHistoryAnalyticsService', $source);
        self::assertStringContainsString('PriceHistoryChartRenderer::render', $source);
        $repository = (string) file_get_contents($root . '/lib/Service/OrmPriceHistoryAnalyticsRepository.php');
        foreach (["'=PRODUCT_COMPETITOR_ID' => \$linkId", "'=PRODUCT_ID' => \$productId", "'=COMPETITOR_ID' => \$competitorId", "'=URL_HASH' => \$urlHash"] as $identityFilter) {
            self::assertStringContainsString($identityFilter, $repository);
        }
        self::assertStringContainsString("'order' => ['ID' => 'ASC']", $repository);
        self::assertStringContainsString('The monitored link no longer exists', (string) file_get_contents($root . '/lang/en/admin/price_history.php'));
        self::assertFileExists($root . '/install/admin/kk_pricewatch_price_history.php');
        self::assertStringContainsString('kk_pricewatch_price_history.php', (string) file_get_contents($root . '/admin/product_competitors.php'));
        self::assertStringContainsString('kk_pricewatch_price_history.php', (string) file_get_contents($root . '/install/index.php'));
    }
}
