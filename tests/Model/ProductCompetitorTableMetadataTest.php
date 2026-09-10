<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use PHPUnit\Framework\TestCase;

final class ProductCompetitorTableMetadataTest extends TestCase
{
    public function testSourceUsesSupportedValidationApi(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/ProductCompetitorTable.php');

        self::assertIsString($source);
        self::assertStringNotContainsString('configureValidation(', $source);
        self::assertStringContainsString('addValidator(', $source);
    }

    public function testSourceDeclaresFixedPrecisionAndDerivedHashContract(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/ProductCompetitorTable.php');
        $installer = file_get_contents(__DIR__ . '/../../lib/Installer/SchemaInstaller.php');

        self::assertIsString($source);
        self::assertStringContainsString("new DecimalField('CURRENT_PRICE')", $source);
        self::assertMatchesRegularExpression('/configurePrecision\(18\).*?configureScale\(2\)/s', $source);
        self::assertStringContainsString("ProductUrl::hash((string) \$fields['URL'])", $source);
        self::assertIsString($installer);
        self::assertStringContainsString("['PRODUCT_ID', 'COMPETITOR_ID', 'URL_HASH']", $installer);
    }

    public function testProductCompetitorUsesRealBitrixOrmNamespaces(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/ProductCompetitorTable.php');

        self::assertIsString($source);
        self::assertStringNotContainsString('Bitrix\\MainORM\\', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\Data\\DataManager', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\Event', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\EventResult', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\Fields\\', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\Fields\\Relations\\Reference', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\Fields\\Validators\\LengthValidator', $source);
        self::assertStringContainsString('Bitrix\\Main\\ORM\\Query\\Join', $source);
    }

    public function testCurrencyAndStatusDeclarePhysicalLengthsAndSemanticValidators(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/ProductCompetitorTable.php');

        self::assertIsString($source);
        $currencyStart = strpos($source, "new StringField('CURRENCY')");
        $statusStart = strpos($source, "new StringField('STATUS')");
        $errorCodeStart = strpos($source, "new StringField('ERROR_CODE')");

        self::assertIsInt($currencyStart);
        self::assertIsInt($statusStart);
        self::assertIsInt($errorCodeStart);

        $currencyDeclaration = substr($source, $currencyStart, $statusStart - $currencyStart);
        $statusDeclaration = substr($source, $statusStart, $errorCodeStart - $statusStart);

        self::assertStringContainsString('configureSize(3)', $currencyDeclaration);
        self::assertStringContainsString('new LengthValidator(3, 3)', $currencyDeclaration);
        self::assertStringContainsString('Money::assertCurrency($value)', $currencyDeclaration);
        self::assertStringContainsString('configureSize(16)', $statusDeclaration);
        self::assertStringContainsString('new LengthValidator(null, 16)', $statusDeclaration);
        self::assertStringContainsString('CollectionStatus::isValid($value)', $statusDeclaration);
    }

    public function testInstallerUsesConnectionApiForUniqueIndex(): void
    {
        $installer = file_get_contents(__DIR__ . '/../../lib/Installer/SchemaInstaller.php');

        self::assertIsString($installer);
        self::assertStringNotContainsString('getCreateIndexSql', $installer);
        self::assertStringContainsString('Bitrix\\Main\\DB\\Connection', $installer);
        self::assertStringContainsString('Connection::INDEX_UNIQUE', $installer);
        self::assertMatchesRegularExpression(
            '/createIndex\(\$table, \$name, \$columns, null, \$type\)/',
            $installer
        );
    }
}
