<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use PHPUnit\Framework\TestCase;

final class StaffProductPriceRepositorySourceTest extends TestCase
{
    public function testQueryUsesExactIdentityActiveRowsAndDeterministicOrdering(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Service/OrmStaffProductPriceRepository.php');

        self::assertStringContainsString("'=PRODUCT_ID' => \$productId", $source);
        self::assertStringContainsString("'=ACTIVE' => 'Y'", $source);
        self::assertStringContainsString("'=COMPETITOR.ACTIVE' => 'Y'", $source);
        self::assertStringContainsString("'COMPETITOR_SORT' => 'ASC'", $source);
        self::assertStringContainsString("'COMPETITOR_NAME' => 'ASC'", $source);
        self::assertStringContainsString("'ID' => 'ASC'", $source);
        self::assertStringNotContainsString('PriceHistoryTable', $source);
        self::assertStringNotContainsString('PARENT', $source);
        self::assertStringNotContainsString('OFFERS', $source);
    }
}
