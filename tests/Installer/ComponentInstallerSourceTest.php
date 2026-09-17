<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Installer;

use PHPUnit\Framework\TestCase;

final class ComponentInstallerSourceTest extends TestCase
{
    public function testFreshInstallAndUninstallOwnTheComponentWithoutSchemaDeletion(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/install/index.php');
        self::assertSame(2, substr_count($source, 'new ComponentInstaller()'));
        self::assertStringContainsString('->install(__DIR__', $source);
        self::assertStringContainsString('->uninstall($_SERVER', $source);
        self::assertStringContainsString('data is intentionally preserved', $source);
        self::assertStringNotContainsString('uninstallDatabase', $source);
    }

    public function testVersionAndUpdateInstallAdditiveSchemaAndComponent(): void
    {
        $root = dirname(__DIR__, 2);
        $version = (string) file_get_contents($root . '/install/version.php');
        $updater = (string) file_get_contents($root . '/install/updates/0.13.0/updater.php');
        self::assertStringContainsString("'VERSION' => '0.14.4'", $version);
        self::assertStringContainsString('ComponentInstaller', $updater);
        self::assertStringContainsString('SchemaInstaller', $updater);
        self::assertStringNotContainsString('Table::', $updater);
    }
}
