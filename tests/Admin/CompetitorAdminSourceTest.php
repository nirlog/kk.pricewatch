<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use PHPUnit\Framework\TestCase;

final class CompetitorAdminSourceTest extends TestCase
{
    private static function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }

    public function testStandardRightsAndEntryPointsAreDeclared(): void
    {
        $installer = self::source('install/index.php');
        self::assertStringContainsString("MODULE_GROUP_RIGHTS = 'Y'", $installer);
        self::assertStringContainsString("['D', 'R', 'W']", $installer);
        self::assertFileExists(dirname(__DIR__, 2) . '/install/admin/kk_pricewatch_competitors.php');
        self::assertFileExists(dirname(__DIR__, 2) . '/install/admin/kk_pricewatch_competitor_edit.php');
        self::assertFileExists(dirname(__DIR__, 2) . '/composer.lock');
    }

    public function testUninstallDoesNotTreatProxyCleanupAsBooleanResult(): void
    {
        $installer = self::source('install/index.php');
        self::assertDoesNotMatchRegularExpression('/if\s*\(\s*!\s*DeleteDirFiles\s*\(/', $installer);
        self::assertStringContainsString("DeleteDirFiles(__DIR__ . '/admin', \$adminDirectory);", $installer);
        self::assertStringContainsString("'kk_pricewatch_competitors.php'", $installer);
        self::assertStringContainsString("'kk_pricewatch_competitor_edit.php'", $installer);
        self::assertStringContainsString('is_file($proxyPath)', $installer);

        $cleanupPosition = strpos($installer, 'DeleteDirFiles(');
        $unregisterPosition = strpos($installer, 'ModuleManager::unRegisterModule(');
        self::assertNotFalse($cleanupPosition);
        self::assertNotFalse($unregisterPosition);
        self::assertGreaterThan($cleanupPosition, $unregisterPosition);
    }

    public function testMutationsArePermissionAndSessionProtected(): void
    {
        $edit = self::source('admin/competitor_edit.php');
        self::assertStringContainsString('Access::canWrite()', $edit);
        self::assertStringContainsString('check_bitrix_sessid()', $edit);
        self::assertStringContainsString('$request->isPost()', $edit);
        self::assertStringContainsString('CompetitorTable::add(', $edit);
        self::assertStringContainsString('CompetitorTable::update(', $edit);
        self::assertStringContainsString('ProductCompetitorTable::getList(', $edit);
        self::assertStringContainsString('CompetitorTable::delete(', $edit);
    }

    public function testListWhitelistsSortingAndEscapesValues(): void
    {
        $list = self::source('admin/competitors.php');
        self::assertStringContainsString('$sortFields = [', $list);
        self::assertStringContainsString('in_array($sortField, $sortFields, true)', $list);
        self::assertStringContainsString('htmlspecialcharsbx((string) $item[\'NAME\'])', $list);
        self::assertStringContainsString("getPageNavigation('nav-kk-pricewatch-competitors')", $list);
        self::assertStringContainsString('CompetitorTable::getCount($filter)', $list);
        self::assertStringContainsString("'limit' => \$navigation->getLimit()", $list);
        self::assertStringContainsString("'offset' => \$navigation->getOffset()", $list);
        self::assertStringContainsString('setNavigation($navigation,', $list);
        self::assertStringNotContainsString('NavStart()', $list);
    }
}
