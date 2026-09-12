<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use PHPUnit\Framework\TestCase;

final class ProductCompetitorAdminSourceTest extends TestCase
{
    private static function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testLifecycleAndProxiesAreRegistered(): void
    {
        $installer = self::source('install/index.php');
        self::assertStringContainsString("registerEventHandler('main', 'OnAdminTabControlBegin'", $installer);
        self::assertStringContainsString("unRegisterEventHandler('main', 'OnAdminTabControlBegin'", $installer);
        self::assertStringContainsString('kk_pricewatch_product_competitors.php', $installer);
        self::assertStringContainsString('kk_pricewatch_product_competitor_edit.php', $installer);
        self::assertStringNotContainsString('if (!DeleteDirFiles', $installer);
        self::assertFileExists(dirname(__DIR__, 2) . '/install/admin/kk_pricewatch_product_competitors.php');
        self::assertFileExists(dirname(__DIR__, 2) . '/install/admin/kk_pricewatch_product_competitor_edit.php');
        self::assertStringContainsString("'VERSION' => '0.5.0'", self::source('install/version.php'));
    }

    public function testTabIsReadOnlyAndRestricted(): void
    {
        $handler = self::source('lib/Admin/ProductEditTabHandler.php');
        self::assertStringContainsString('Access::canRead()', $handler);
        self::assertStringContainsString('SUPPORTED_SCRIPTS', $handler);
        self::assertStringContainsString('ProductContext::findCatalogProduct', $handler);
        self::assertStringContainsString("'CONTENT'", $handler);
        self::assertStringNotContainsString('<form', $handler);
        self::assertStringContainsString('htmlspecialcharsbx', $handler);
        self::assertStringNotContainsString('->AddTabs(', $handler);
        self::assertStringContainsString('$tabControl->tabs[] = $tab', $handler);
        self::assertStringContainsString("private const TAB_ID = 'kk_pricewatch_product_competitors'", $handler);
        self::assertStringContainsString("(\$existingTab['DIV'] ?? null) === self::TAB_ID", $handler);
        self::assertMatchesRegularExpression('/<tr>\s*<td colspan="2">/', $handler);
        self::assertDoesNotMatchRegularExpression('/<div[^>]*>\s*<tr>/', $handler);
    }

    public function testCatalogProductContextIsRequiredAtEveryAdminBoundary(): void
    {
        $context = self::source('lib/Admin/ProductContext.php');
        self::assertStringContainsString('Bitrix\\Catalog\\ProductTable', $context);
        self::assertStringContainsString("Loader::includeModule('catalog')", $context);
        self::assertStringContainsString('ProductTable::getByPrimary($productId', $context);

        foreach ([
            'lib/Admin/ProductEditTabHandler.php',
            'lib/Admin/ProductCompetitorLinkService.php',
            'admin/product_competitors.php',
            'admin/product_competitor_edit.php',
        ] as $path) {
            self::assertStringContainsString('ProductContext::findCatalogProduct($productId)', self::source($path), $path);
        }
    }

    public function testMutationBoundaryIsProtectedAndExact(): void
    {
        $page = self::source('admin/product_competitor_edit.php');
        $service = self::source('lib/Admin/ProductCompetitorLinkService.php');
        self::assertStringContainsString('Access::canWrite()', $page);
        self::assertStringContainsString('check_bitrix_sessid()', $page);
        self::assertStringContainsString("getPost('URL')", $page);
        self::assertStringNotContainsString("getPost('URL_HASH')", $page);
        self::assertStringContainsString("'CURRENT_PRICE' => null", $service);
        self::assertStringContainsString("'LAST_SUCCESS_AT' => null", $service);
        self::assertStringContainsString('ProductUrl::hash($exactUrl)', $service);
        self::assertStringContainsString('ProductContext::findCatalogProduct($productId)', $service);
        self::assertStringContainsString('CompetitorTable::getByPrimary', $service);
    }
}
