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

    public function testAnalyticsUsesCurrentIdentityWhileTheImmutableTableKeepsItsBroaderFilter(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string) file_get_contents($root . '/admin/price_history.php');
        $repository = (string) file_get_contents($root . '/lib/Service/OrmPriceHistoryAnalyticsRepository.php');

        // Exact URL hash and current competitor are mandatory for analytics, excluding both old identities.
        self::assertStringContainsString("'=COMPETITOR_ID' => \$competitorId", $repository);
        self::assertStringContainsString("'=URL_HASH' => \$urlHash", $repository);

        // The authoritative table intentionally remains link/product filtered and can show old identities.
        self::assertStringContainsString("if (\$productId > 0) \$filter['=PRODUCT_ID'] = \$productId;", $page);
        self::assertStringContainsString("if (\$linkId > 0) \$filter['=PRODUCT_COMPETITOR_ID'] = \$linkId;", $page);
        self::assertStringNotContainsString("\$filter['=URL_HASH']", $page);
        self::assertStringNotContainsString("\$filter['=COMPETITOR_ID']", $page);
    }

    public function testDeletedAndAggregateContextsNeverInventAnalytics(): void
    {
        $root = dirname(__DIR__, 2);
        $page = (string) file_get_contents($root . '/admin/price_history.php');

        // Missing live link gets a message; the table is still rendered afterwards.
        self::assertStringContainsString('$currentLink === null || $currentLink === false', $page);
        self::assertStringContainsString("Loc::getMessage('KK_PRICEWATCH_ANALYTICS_DELETED_LINK')", $page);
        self::assertMatchesRegularExpression('/KK_PRICEWATCH_ANALYTICS_DELETED_LINK[\s\S]+\$list->DisplayList\(\)/', $page);

        // Product-only/global requests enter the explanatory branch, never the chart branch.
        self::assertStringContainsString('if ($linkId <= 0 || $productId <= 0)', $page);
        self::assertMatchesRegularExpression('/if \(\$linkId <= 0 \|\| \$productId <= 0\)[\s\S]+elseif \(\$currentLink[\s\S]+else \{[\s\S]+PriceHistoryChartRenderer::render/', $page);
    }
}
