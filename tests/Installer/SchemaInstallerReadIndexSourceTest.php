<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Installer;

use PHPUnit\Framework\TestCase;

final class SchemaInstallerReadIndexSourceTest extends TestCase
{
    public function testReadIndexIsAddedIdempotentlyWithoutDroppingExistingIndexes(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/lib/Installer/SchemaInstaller.php');

        self::assertStringContainsString("'ix_kk_pw_pc_product_active_competitor'", $source);
        self::assertStringContainsString("['PRODUCT_ID', 'ACTIVE', 'COMPETITOR_ID']", $source);
        self::assertStringContainsString("'ux_kk_pw_pc_identity'", $source);
        self::assertStringContainsString("'ix_kk_pw_pc_product'", $source);
        self::assertStringContainsString('isIndexExists', $source);
        self::assertStringNotContainsString('dropIndex', $source);
    }
}
