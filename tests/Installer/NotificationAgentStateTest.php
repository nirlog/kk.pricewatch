<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Installer;

use DateTimeImmutable;
use KK\PriceWatch\Installer\NotificationAgentState;
use PHPUnit\Framework\TestCase;

final class NotificationAgentStateTest extends TestCase
{
    public function testDatesAreNormalizedToNullableStrings(): void
    {
        $state = NotificationAgentState::fromAgentRecord([
            'ACTIVE' => 'Y', 'AGENT_INTERVAL' => '3600',
            'LAST_EXEC' => new DateTimeImmutable('2026-09-21 09:00:55'), 'NEXT_EXEC' => null,
        ]);

        self::assertTrue($state->active);
        self::assertSame(3600, $state->intervalSeconds);
        self::assertSame('21.09.2026 09:00:55', $state->lastExecution);
        self::assertNull($state->nextExecution);
    }
}
