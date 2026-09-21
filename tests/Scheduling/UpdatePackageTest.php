<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Scheduling;

use PharData;
use PHPUnit\Framework\TestCase;

final class UpdatePackageTest extends TestCase
{
    public function testVersionMetadataIsPlacedAtArchiveRoot(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir() . '/kk-pricewatch-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $archivePath = $directory . '/0.17.0.tar.gz';

        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/bin/build-update-package.php') . ' 0.17.0 '
            . escapeshellarg($archivePath),
            $output,
            $exitCode
        );

        try {
            self::assertSame(0, $exitCode, implode("\n", $output));
            $archive = new PharData($archivePath);
            self::assertTrue(isset($archive['updater.php']));
            self::assertTrue(isset($archive['description.ru']));
            self::assertTrue(isset($archive['description.en']));
            self::assertTrue(isset($archive['install/version.php']));
            self::assertTrue(isset($archive['install/admin/kk_pricewatch_price_history.php']));
            self::assertTrue(isset($archive['install/admin/kk_pricewatch_monitoring.php']));
            self::assertTrue(isset($archive['install/admin/kk_pricewatch_notification_settings.php']));
            self::assertTrue(isset($archive['install/admin/kk_pricewatch_external_collector.php']));
            self::assertTrue(isset($archive['lib/Collector/External/ExternalCollector.php']));
            self::assertTrue(isset($archive['admin/external_collector.php']));
            self::assertTrue(isset($archive['lang/ru/admin/external_collector.php']));
            self::assertTrue(isset($archive['lang/en/admin/external_collector.php']));
            self::assertTrue(isset($archive['bin/pricewatch-notify.php']));
            self::assertTrue(isset($archive['lib/Notification/NotificationRunner.php']));
            self::assertTrue(isset($archive['lib/Installer/NotificationAgentInstaller.php']));
            self::assertTrue(isset($archive['lib/Installer/NotificationAgentState.php']));
            self::assertTrue(isset($archive['lib/Notification/NotificationAgentIntervalValidator.php']));
            self::assertTrue(isset($archive['admin/notification_settings.php']));
            self::assertTrue(isset($archive['lang/ru/admin/notification_settings.php']));
            self::assertTrue(isset($archive['lang/en/admin/notification_settings.php']));
            self::assertTrue(isset($archive['admin/monitoring.php']));
            self::assertTrue(isset($archive['lib/Service/MonitoringDashboardService.php']));
            self::assertTrue(isset($archive['install/components/kk.pricewatch/product.prices/class.php']));

            foreach ([
                'lang/ru/lib/Installer/NotificationMailInstaller.php',
                'lang/en/lib/Installer/NotificationMailInstaller.php',
                'lang/ru/lib/Notification/BitrixMailNotificationTransport.php',
                'lang/en/lib/Notification/BitrixMailNotificationTransport.php',
            ] as $localizedFile) {
                self::assertTrue(isset($archive[$localizedFile]), $localizedFile);
            }
            foreach ([
                'lang/ru/lib/installer/notificationmailinstaller.php',
                'lang/en/lib/installer/notificationmailinstaller.php',
                'lang/ru/lib/notification/bitrixmailnotificationtransport.php',
                'lang/en/lib/notification/bitrixmailnotificationtransport.php',
            ] as $obsoleteLocalizedFile) {
                self::assertFalse(isset($archive[$obsoleteLocalizedFile]), $obsoleteLocalizedFile);
            }

            foreach (new \RecursiveIteratorIterator($archive) as $file) {
                self::assertStringNotContainsString(
                    '/install/updates/0.17.0/',
                    str_replace('\\', '/', $file->getPathname())
                );
            }
        } finally {
            @unlink($archivePath);
            @rmdir($directory);
        }
    }

    public function testExternalCollectorUpdaterHasNoNetworkOrBusinessActions(): void
    {
        $updater = (string) file_get_contents(dirname(__DIR__, 2) . '/install/updates/0.17.0/updater.php');
        self::assertStringNotContainsString('HttpClient', $updater);
        self::assertStringNotContainsString('collect(', $updater);
        self::assertStringNotContainsString('Table::', $updater);
        self::assertStringNotContainsString('Mail', $updater);
    }

    public function testNotificationAgentUpdaterOnlyRepairsAgentRegistration(): void
    {
        $root = dirname(__DIR__, 2);
        $updater = (string) file_get_contents($root . '/install/updates/0.16.0/updater.php');
        self::assertStringContainsString('NotificationAgentInstaller', $updater);
        self::assertStringNotContainsString('NotificationRunner', $updater);
        self::assertStringNotContainsString('Collector', $updater);
        self::assertStringNotContainsString('Mail', $updater);
        self::assertStringNotContainsString('Table::', $updater);
    }

    public function testRequestedVersionMustMatchModuleVersion(): void
    {
        $root = dirname(__DIR__, 2);
        $archivePath = sys_get_temp_dir() . '/kk-pricewatch-' . bin2hex(random_bytes(8)) . '.tar.gz';

        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/bin/build-update-package.php') . ' 0.7.0 '
            . escapeshellarg($archivePath) . ' 2>&1',
            $output,
            $exitCode
        );

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('does not match install/version.php', implode("\n", $output));
        self::assertFileDoesNotExist($archivePath);
    }

    public function testMonitoringRuntimeHotfixPerformsNoInstallerOrDataOperations(): void
    {
        $root = dirname(__DIR__, 2);
        $updater = (string) file_get_contents($root . '/install/updates/0.14.5/updater.php');
        self::assertStringNotContainsString('AdminInstaller', $updater);
        self::assertStringNotContainsString('SchemaInstaller', $updater);
        self::assertStringNotContainsString('ComponentInstaller', $updater);
        self::assertStringNotContainsString('Table::', $updater);
    }
}
