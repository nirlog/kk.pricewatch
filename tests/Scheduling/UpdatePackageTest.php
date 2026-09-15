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
        $archivePath = $directory . '/0.14.1.tar.gz';

        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/bin/build-update-package.php') . ' 0.14.1 '
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
            self::assertTrue(isset($archive['admin/monitoring.php']));
            self::assertTrue(isset($archive['lib/Service/MonitoringDashboardService.php']));
            self::assertTrue(isset($archive['install/components/kk.pricewatch/product.prices/class.php']));

            foreach (new \RecursiveIteratorIterator($archive) as $file) {
                self::assertStringNotContainsString(
                    '/install/updates/0.14.1/',
                    str_replace('\\', '/', $file->getPathname())
                );
            }
        } finally {
            @unlink($archivePath);
            @rmdir($directory);
        }
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
        $updater = (string) file_get_contents($root . '/install/updates/0.14.1/updater.php');
        self::assertStringNotContainsString('AdminInstaller', $updater);
        self::assertStringNotContainsString('SchemaInstaller', $updater);
        self::assertStringNotContainsString('ComponentInstaller', $updater);
        self::assertStringNotContainsString('Table::', $updater);
    }
}
