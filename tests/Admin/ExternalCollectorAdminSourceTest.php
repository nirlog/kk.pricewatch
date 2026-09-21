<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Admin;
use PHPUnit\Framework\TestCase;
final class ExternalCollectorAdminSourceTest extends TestCase
{
    public function testSettingsPageIsWriteAndCsrfProtectedAndNeverRendersToken(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/admin/external_collector.php');
        self::assertStringContainsString('Access::canWrite()', $source);
        self::assertStringContainsString('check_bitrix_sessid()', $source);
        self::assertStringContainsString('type="password"', $source);
        self::assertStringContainsString('value=""', $source);
        self::assertStringNotContainsString("htmlspecialcharsbx(\$storedToken)", $source);
        self::assertStringContainsString("\$newToken!==''?\$newToken:\$storedToken", $source);
    }

    public function testExternalCompetitorHandlerIsValidated(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/admin/competitor_edit.php');
        self::assertStringContainsString('ExternalCollectorEndpoint::validateHandler', $source);
    }
}
