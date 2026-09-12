<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Scheduling;

use PharData;
use PHPUnit\Framework\TestCase;

final class UpdatePackageTest extends TestCase
{
    public function testVersionUpdaterIsPlacedAtArchiveRoot(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir() . '/kk-pricewatch-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $archivePath = $directory . '/0.8.0.tar.gz';

        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/bin/build-update-package.php') . ' 0.8.0 '
            . escapeshellarg($archivePath),
            $output,
            $exitCode
        );

        try {
            self::assertSame(0, $exitCode, implode("\n", $output));
            $archive = new PharData($archivePath);
            self::assertTrue(isset($archive['updater.php']));
            self::assertFalse(isset($archive['install/updates/0.8.0/updater.php']));
            self::assertTrue(isset($archive['include.php']));
        } finally {
            @unlink($archivePath);
            @rmdir($directory);
        }
    }
}
