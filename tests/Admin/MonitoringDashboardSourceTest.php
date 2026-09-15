<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use PHPUnit\Framework\TestCase;

final class MonitoringDashboardSourceTest extends TestCase
{
    public function testPagePermissionAndReadOnlyBoundaries(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2) . '/admin/monitoring.php');
        self::assertStringContainsString("Loader::includeModule('kk.pricewatch') || !Access::canRead()", $page);
        self::assertLessThan(strpos($page, 'new OrmMonitoringDashboardRepository'), strpos($page, 'Access::canRead()'));
        self::assertStringContainsString('if (Access::canWrite())', $page);
        self::assertStringContainsString('kk_pricewatch_product_price_check.php', $page);
        self::assertStringContainsString('&MODE=link&PRODUCT_ID=', $page);
        self::assertStringNotContainsString('ERROR_MESSAGE', $page);
        self::assertStringNotContainsString('PriceHistoryTable', $page);
        self::assertStringNotContainsString('PriceUpdateService', $page);
        self::assertStringNotContainsString('Collector', $page);
        self::assertStringContainsString('ProductUrl::isAcceptedHttpUrl', $page);
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $page);
        self::assertStringContainsString('catch (Throwable)', $page);
    }

    public function testRepositoryIsScopedPaginatedAndDoesNotReadHistory(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/Service/OrmMonitoringDashboardRepository.php');
        self::assertStringContainsString("'=ACTIVE' => 'Y'", $source);
        self::assertStringContainsString("'=COMPETITOR.ACTIVE' => 'Y'", $source);
        self::assertStringContainsString("'limit' => max(1, \$limit)", $source);
        self::assertStringContainsString("'offset' => max(0, \$offset)", $source);
        self::assertStringNotContainsString('PriceHistoryTable', $source);
    }

    public function testRepositoryAdaptsDomainCutoffAtBitrixOrmBoundary(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/Service/OrmMonitoringDashboardRepository.php');
        self::assertStringContainsString('use Bitrix\\Main\\Type\\DateTime as BitrixDateTime;', $source);
        self::assertStringContainsString('self::toBitrixDateTime($cutoff)', $source);
        self::assertStringContainsString('private static function toBitrixDateTime(DateTimeInterface $value): BitrixDateTime', $source);
        self::assertStringContainsString('$result->setTimestamp($value->getTimestamp());', $source);
        self::assertStringNotContainsString('MonitoringHealth::ormFilter($health, $cutoff)', $source);
    }

    public function testMenuProxyAndLocalizationExist(): void
    {
        $root = dirname(__DIR__, 2);
        $menu = file_get_contents($root . '/admin/menu.php');
        self::assertLessThan(strpos($menu, 'MENU_COMPETITORS'), strpos($menu, 'MENU_MONITORING'));
        self::assertFileExists($root . '/install/admin/kk_pricewatch_monitoring.php');
        foreach (['en', 'ru'] as $language) self::assertFileExists($root . '/lang/' . $language . '/admin/monitoring.php');
    }
}
