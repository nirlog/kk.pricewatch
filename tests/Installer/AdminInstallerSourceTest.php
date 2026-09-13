<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Installer;

use PHPUnit\Framework\TestCase;

final class AdminInstallerSourceTest extends TestCase
{
    public function testUpdateInstallsAdminProxiesWithoutChangingRegistrationOrAgents(): void
    {
        $root = dirname(__DIR__, 2);
        $updater = (string) file_get_contents($root . '/install/updates/0.10.0/updater.php');
        self::assertStringContainsString("(new AdminInstaller())->install(\$moduleRoot . '/install', \$_SERVER['DOCUMENT_ROOT'])", $updater);
        self::assertStringNotContainsString('ModuleManager', $updater);
        self::assertStringNotContainsString('ScheduledAgentInstaller', $updater);
        self::assertStringNotContainsString('CAgent', $updater);

        $installer = (string) file_get_contents($root . '/lib/Installer/AdminInstaller.php');
        self::assertStringContainsString('CopyDirFiles(', $installer);
        self::assertStringContainsString("'/bitrix/admin'", $installer);
        self::assertFileExists($root . '/install/admin/kk_pricewatch_price_history.php');
    }
}
