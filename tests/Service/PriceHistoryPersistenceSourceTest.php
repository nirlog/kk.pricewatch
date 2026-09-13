<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use PHPUnit\Framework\TestCase;

final class PriceHistoryPersistenceSourceTest extends TestCase
{
    public function testSuccessBoundaryIsShortAtomicAndPerLinkSerialized(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Service/OrmSuccessPersistence.php');
        self::assertStringContainsString('startTransaction()', $source);
        self::assertStringContainsString("WHERE ID = ' . (int) \$linkId . ' FOR UPDATE", $source);
        self::assertStringContainsString('commitTransaction()', $source);
        self::assertStringContainsString('rollbackTransaction()', $source);
        self::assertLessThan(strpos($source, 'ProductCompetitorTable::update'), strpos($source, 'PriceHistoryTable::add'));
        self::assertStringNotContainsString('CollectorInterface', $source);
        self::assertStringNotContainsString('sleep(', $source);
        self::assertStringNotContainsString('GET_LOCK', $source);
    }

    public function testLatestEffectiveIdentityControlsAppendOnlyInsertion(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Service/OrmSuccessPersistence.php');
        foreach (['=PRODUCT_COMPETITOR_ID', '=PRODUCT_ID', '=COMPETITOR_ID', '=URL', '=URL_HASH'] as $field) {
            self::assertStringContainsString("'$field'", $source);
        }
        self::assertStringContainsString("'order' => ['COLLECTED_AT' => 'DESC', 'ID' => 'DESC']", $source);
        self::assertStringContainsString('PriceHistoryDecision::shouldAppend(', $source);
        self::assertSame(1, substr_count($source, 'PriceHistoryTable::add('));
        self::assertStringNotContainsString('PriceHistoryTable::update', $source);
        self::assertStringNotContainsString('PriceHistoryTable::delete', $source);
    }

    public function testFactoryUsesHistoryForManualScheduledAndAgentSharedService(): void
    {
        $factory = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Service/PriceUpdateServiceFactory.php');
        self::assertStringContainsString('new OrmSuccessPersistence()', $factory);
        foreach (['admin/product_price_check.php', 'lib/Scheduling/ScheduledPriceUpdateRunnerFactory.php'] as $path) {
            self::assertStringContainsString('PriceUpdateServiceFactory::createDefault()', (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
        }
    }
}
