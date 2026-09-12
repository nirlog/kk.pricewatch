<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Scheduling;

use PHPUnit\Framework\TestCase;

final class SchedulingSourceTest extends TestCase
{
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
        self::assertStringContainsString('PriceUpdateAgent::INVOCATION', $installer);
        self::assertStringContainsString("'N',\n                3600", $installer);
        self::assertStringContainsString("3600,\n                '',\n                'N'", $installer);
        self::assertStringContainsString('CAgent::RemoveAgent(PriceUpdateAgent::INVOCATION', $installer);
        self::assertStringContainsString("'VERSION' => '0.8.0'", file_get_contents($root . '/install/version.php'));
    }
}
