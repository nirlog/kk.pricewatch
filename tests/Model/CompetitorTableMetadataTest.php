<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use PHPUnit\Framework\TestCase;

final class CompetitorTableMetadataTest extends TestCase
{
    public function testSourceUsesSupportedValidationApi(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/CompetitorTable.php');

        self::assertIsString($source);
        self::assertStringNotContainsString('configureValidation(', $source);
        self::assertStringContainsString('addValidator(', $source);
    }

    public function testCollectorHandlerDeclaresMatchingPhysicalAndValidationLengths(): void
    {
        $source = file_get_contents(__DIR__ . '/../../lib/Model/CompetitorTable.php');

        self::assertIsString($source);
        self::assertMatchesRegularExpression(
            "/new StringField\\('COLLECTOR_HANDLER'\\).*?configureSize\\(512\\).*?new LengthValidator\\(null, 512\\)/s",
            $source,
        );
    }
}
