<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Admin;

use PHPUnit\Framework\TestCase;

final class ManualPriceCheckAdminSourceTest extends TestCase
{
    private static function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testExecutionBoundaryRequiresWritePostAndCsrf(): void
    {
        $page = self::source('admin/product_price_check.php');
        self::assertStringContainsString('$request->isPost()', $page);
        self::assertStringContainsString('Access::canWrite()', $page);
        self::assertStringContainsString('check_bitrix_sessid()', $page);
        self::assertStringContainsString('PriceUpdateServiceFactory::createDefault()->updateLinks($linkIds)', $page);
        self::assertStringNotContainsString('MockCollector', $page);
        self::assertStringNotContainsString('COLLECTOR_TYPE', $page);
        self::assertStringNotContainsString("getPost('URL')", $page);
        self::assertDoesNotMatchRegularExpression('/ProductCompetitorTable::update|CURRENT_PRICE\s*=>|LAST_CHECK_AT\s*=>/', $page);
    }

    public function testProductAndLinkScopesAreConstrained(): void
    {
        $page = self::source('admin/product_price_check.php');
        self::assertStringContainsString("(int) \$link['PRODUCT_ID'] !== \$productId", $page);
        self::assertStringContainsString("'filter' => ['=PRODUCT_ID' => \$productId, '=ACTIVE' => 'Y']", $page);
        self::assertStringContainsString("'order' => ['ID' => 'ASC']", $page);
        self::assertStringContainsString("\$links = [\$link]", $page);
        self::assertStringContainsString('if ($request->isPost() && $error === \'\')', $page);
        self::assertStringContainsString("elseif (\$links === [])", $page);
        self::assertSame(1, substr_count($page, '->updateLinks('));
    }

    public function testPrgEscapingReadOnlyUiAndLifecycle(): void
    {
        $page = self::source('admin/product_price_check.php');
        self::assertStringContainsString('LocalRedirect($redirect)', $page);
        self::assertStringContainsString("\$_SESSION['KK_PRICEWATCH_PRICE_CHECK']", $page);
        self::assertStringContainsString("htmlspecialcharsbx((string) (\$outcome['message'] ?? ''))", $page);
        self::assertStringContainsString('catch (Throwable)', $page);
        self::assertStringNotContainsString('getMessage()', $page);

        $list = self::source('admin/product_competitors.php');
        $tab = self::source('lib/Admin/ProductEditTabHandler.php');
        self::assertStringContainsString('if (Access::canWrite())', $list);
        self::assertStringContainsString('kk_pricewatch_product_price_check.php', $list);
        self::assertStringContainsString('kk_pricewatch_product_price_check.php', $tab);
        self::assertStringNotContainsString('<form', $tab);

        $installer = self::source('install/index.php');
        self::assertStringContainsString('kk_pricewatch_product_price_check.php', $installer);
        self::assertFileExists(dirname(__DIR__, 2) . '/install/admin/kk_pricewatch_product_price_check.php');
        self::assertStringContainsString("'VERSION' => '0.7.0'", self::source('install/version.php'));
    }
}
