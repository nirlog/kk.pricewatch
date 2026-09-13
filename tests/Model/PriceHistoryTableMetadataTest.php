<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use PHPUnit\Framework\TestCase;

final class PriceHistoryTableMetadataTest extends TestCase
{
    public function testRequiredSnapshotAndMoneyMetadata(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Model/PriceHistoryTable.php');
        self::assertStringContainsString("return 'b_kk_pricewatch_price_history'", $source);
        foreach (['PRODUCT_COMPETITOR_ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'URL', 'URL_HASH', 'PRICE', 'CURRENCY', 'COLLECTED_AT'] as $field) {
            self::assertStringContainsString("('$field')", $source);
        }
        self::assertMatchesRegularExpression("/DecimalField\('PRICE'\).*?configureRequired\(\).*?configurePrecision\(18\).*?configureScale\(2\)/s", $source);
        self::assertMatchesRegularExpression("/StringField\('URL_HASH'\).*?configureSize\(64\).*?LengthValidator\(64, 64\)/s", $source);
        self::assertMatchesRegularExpression("/StringField\('CURRENCY'\).*?configureSize\(3\).*?LengthValidator\(3, 3\)/s", $source);
        self::assertStringNotContainsString('onBeforeUpdate', $source);
    }

    public function testFreshInstallerEnsuresAllReadIndexes(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Installer/SchemaInstaller.php');
        self::assertStringContainsString('PriceHistoryTable::getEntity()->createDbTable()', $source);
        foreach ([
            "['PRODUCT_COMPETITOR_ID', 'COLLECTED_AT']",
            "['PRODUCT_ID', 'COLLECTED_AT']",
            "['COMPETITOR_ID', 'COLLECTED_AT']",
            "['PRODUCT_COMPETITOR_ID', 'URL_HASH', 'COLLECTED_AT']",
        ] as $columns) self::assertStringContainsString($columns, $source);
        self::assertStringContainsString('isTableExists($historyTable)', $source);
        self::assertStringContainsString('isIndexExists($table, $columns)', $source);
    }
}
