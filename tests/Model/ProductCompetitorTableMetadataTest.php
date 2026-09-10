<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use PHPUnit\Framework\TestCase;

final class ProductCompetitorTableMetadataTest extends TestCase
{
    public function testSourceDeclaresFixedPrecisionAndDerivedHashContract(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/ProductCompetitorTable.php');
        $installer = file_get_contents(__DIR__ . '/../../lib/Installer/SchemaInstaller.php');

        self::assertIsString($source);
        self::assertStringContainsString("new DecimalField('CURRENT_PRICE')", $source);
        self::assertMatchesRegularExpression('/configurePrecision\(18\).*?configureScale\(2\)/s', $source);
        self::assertStringContainsString("ProductUrl::hash((string) \$fields['URL'])", $source);
        self::assertIsString($installer);
        self::assertStringContainsString("['PRODUCT_ID', 'COMPETITOR_ID', 'URL_HASH'], true", $installer);
    }
}
