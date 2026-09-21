<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Scheduling;

use KK\PriceWatch\Agent\PriceUpdateAgent;
use PHPUnit\Framework\TestCase;

final class SchedulingSourceTest extends TestCase
{
    public function testAgentInvocationUsesExactlyOneLeadingNamespaceSeparator(): void
    {
        self::assertSame('\\' . PriceUpdateAgent::class . '::run();', PriceUpdateAgent::INVOCATION);
    }

    public function testOrmSelectionAndAdaptersRemainNarrow(): void
    {
        $root = dirname(__DIR__, 2);
        $repository = file_get_contents($root . '/lib/Scheduling/OrmActiveLinkRepository.php');
        self::assertStringContainsString("'>ID' => \$lastId", $repository);
        self::assertStringContainsString("'<=ID' => \$maximumId", $repository);
        self::assertStringNotContainsString('offset', strtolower($repository));

        foreach (['lib/Scheduling/ScheduledPriceUpdateRunner.php', 'lib/Agent/PriceUpdateAgent.php', 'bin/pricewatch-run.php'] as $file) {
            $source = file_get_contents($root . '/' . $file);
            self::assertStringNotContainsString('MockCollector', $source);
            self::assertStringNotContainsString('COLLECTOR_TYPE', $source);
            self::assertStringNotContainsString('CURRENT_PRICE', $source);
        }
    }

    public function testAgentLifecycleIsExactAndInactive(): void
    {
        $root = dirname(__DIR__, 2);
        $installer = file_get_contents($root . '/install/index.php');
        $lifecycle = file_get_contents($root . '/lib/Installer/ScheduledAgentInstaller.php');
        $upgrade = file_get_contents($root . '/install/updates/0.8.0/updater.php');

        self::assertStringContainsString('(new ScheduledAgentInstaller())->install()', $installer);
        self::assertStringContainsString('(new ScheduledAgentInstaller())->uninstall()', $installer);
        self::assertStringContainsString("CAgent::GetList(\n            ['ID' => 'ASC'],\n            ['MODULE_ID' => self::MODULE_ID, '=NAME' => PriceUpdateAgent::INVOCATION]", $lifecycle);
        self::assertStringNotContainsString("'NAME' => PriceUpdateAgent::INVOCATION", $lifecycle);
        self::assertSame(1, substr_count($lifecycle, 'CAgent::Delete('));
        self::assertStringContainsString("'N',\n                3600", $lifecycle);
        self::assertStringContainsString("3600,\n                '',\n                'N'", $lifecycle);
        self::assertStringContainsString('CAgent::RemoveAgent(PriceUpdateAgent::INVOCATION, self::MODULE_ID)', $lifecycle);
        self::assertStringContainsString('(new ScheduledAgentInstaller())->install()', $upgrade);
        self::assertStringContainsString("is_file(__DIR__ . '/include.php')", $upgrade);
        self::assertStringNotContainsString('CAgent::', $upgrade);
        self::assertStringContainsString("'VERSION' => '0.17.0'", file_get_contents($root . '/install/version.php'));
    }
}
