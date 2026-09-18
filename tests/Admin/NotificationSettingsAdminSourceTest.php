<?php
declare(strict_types=1);
namespace KK\PriceWatch\Tests\Admin;

use PHPUnit\Framework\TestCase;

final class NotificationSettingsAdminSourceTest extends TestCase
{
    public function testWriteBoundaryValidationAndSubmittedValuesArePreserved(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root . '/admin/notification_settings.php');
        self::assertStringContainsString('Access::canWrite()', $source);
        self::assertStringContainsString('check_bitrix_sessid()', $source);
        self::assertStringContainsString('new NotificationSettingsValidator()', $source);
        self::assertStringContainsString('new BitrixNotificationMailTemplateChecker()', $source);
        self::assertStringContainsString("'emails' => \$rawEmails", $source);
        self::assertStringContainsString("'site' => \$siteId", $source);
        self::assertStringContainsString('KK_PRICEWATCH_NOTIFY_EXECUTION_NOTE', $source);
    }
}
