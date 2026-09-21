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
        self::assertStringContainsString('new NotificationAgentInstaller()', $source);
        self::assertStringContainsString('new NotificationAgentIntervalValidator()', $source);
        self::assertStringContainsString('$agentInstaller->configure(', $source);
        self::assertStringNotContainsString('CAgent::', $source);
        self::assertStringContainsString("->lastExecution ?? '—'", $source);
        self::assertStringContainsString("->nextExecution ?? '—'", $source);
    }

    public function testAgentAndCliRemainIndependentAndSharedRunnerKeepsItsLock(): void
    {
        $root = dirname(__DIR__, 2);
        $agent = (string) file_get_contents($root . '/lib/Agent/NotificationAgent.php');
        $cli = (string) file_get_contents($root . '/bin/pricewatch-notify.php');
        $runner = (string) file_get_contents($root . '/lib/Notification/NotificationRunner.php');
        $factory = (string) file_get_contents($root . '/lib/Notification/NotificationRunnerFactory.php');
        self::assertStringContainsString('NotificationRunnerFactory::createDefault()', $agent);
        self::assertStringContainsString('NotificationRunnerFactory::createDefault()', $cli);
        self::assertStringNotContainsString('NotificationAgent', $cli);
        self::assertStringNotContainsString('NotificationAgent', $runner);
        self::assertStringContainsString('BitrixDbNotificationRunLock', $factory);
    }
}
